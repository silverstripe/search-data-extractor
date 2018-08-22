<?php

namespace SilverStripe\SearchDataExtractor\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\BasicAuth;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

use SilverStripe\SearchDataExtractor\Model\SearchDataExtractableInterface;

class SearchDataExtractorAPIController extends Controller implements PermissionProvider
{

    /**
     * The permission code to access this API.
     *
     * @var string
     */
    const API_PERMISSION_CODE = 'SEARCH_DATA_EXTRACTOR_ACCESS';

    /**
     * The environment variable to set locally to disable https checking.
     *
     * @var string
     */
    const ENV_DISABLE_HTTPS = 'DISABLE_SEARCH_DATA_EXTRACTOR_HTTPS';

    /**
     * This is used to define any core models that need to be exposed through
     * the API, e.g. SilverStripe\Assets\File, as we cannot implement the
     * interface directly to these classes.
     *
     * The `DataExtension` applied to the core model must implement the
     * `SearchDataExtractableInterface` to be valid.
     *
     * @var array
     */
    private static $exposed_core_models = [];

    /**
     * The limit of results to display per "page" of results.
     *
     * @var integer
     */
    private static $pagination_limit = 50;

    /**
     * The title of the pop up for when the Basic Authentication prompt is
     * displayed.
     *
     * @var string
     */
    private static $realm = 'Search Data Extractor API';

    /**
     * Using this to run authentication and HTTPS checks first.
     *
     * If credentials are valid, login the user so we can perform access
     * checks later when retrieving data.
     *
     * @return SilverStripe\Control\HTTPResponse|null
     */
    public function init()
    {
        parent::init();

        $request = $this->getRequest();

        if (!$this->validateProtocol($request)) {
            return $this->error(400, 'Please use https');
        }

        $member = $this->authenticateRequest($request);

        // login user if applicable
        if ($member instanceof Member) {
            Security::setCurrentUser($member);
        }
    }

    /**
     * Entry point into the API that retrieves the data to surface as JSON.
     *
     * @param HTTPRequest $request
     *
     * @return string
     */
    public function index(HTTPRequest $request)
    {
        $this->response
            ->addHeader('Content-Type', 'application/json')
            ->setStatusCode(200);

        $classType = $request->getVar('class');

        if (!$classType) {
            return $this->error(400, 'Sorry you need to specify a class.');
        }

        if (!$this->validateAllowedClass($classType)) {
            return $this->error(403, 'Sorry that class is not valid.');
        }

        $offset = $this->getOffsetID($request, $classType);

        return json_encode($this->getData($request, $classType, $offset));
    }

    /**
     * Helper to ensure the request is over https.
     *
     * We allow an environment variable to be set to disable https checks
     * locally if in dev mode.
     *
     * @param SS_HTTPRequest $request
     *
     * @return boolean
     */
    protected function validateProtocol(HTTPRequest $request)
    {
        $isHttpsDisabled = Environment::getEnv(self::ENV_DISABLE_HTTPS);

        return (
            (Director::isDev() && $isHttpsDisabled === true) ||
            Director::is_https($request)
        );
    }

    /**
     * Helper to authenticate the request using Basic Authentication.
     *
     * @param HTTPRequest $request
     *
     * @return Member|boolean
     *
     * @throws HTTPResponse_Exception
     */
    protected function authenticateRequest(HTTPRequest $request)
    {
        $realm = $this->config()->get('realm');

        return BasicAuth::requireLogin($request, $realm, self::API_PERMISSION_CODE);
    }

    /**
     * Helper to ensure the provided class can actually be surfaced through
     * the API.
     *
     * @param string $classType
     *
     * @return boolean
     */
    protected function validateAllowedClass($classType = '')
    {
        $allowedClasses = [];
        $coreModels = $this->config()->exposed_core_models;

        // get list of implementors of the interface
        $implementors = ClassInfo::implementorsOf(SearchDataExtractableInterface::class);

        // use implementorsOf to dynamically add the extra classes
        foreach ($implementors as $lowerCaseClass => $pascalCaseClass) {
            // if this is a dataextension we check against the core models to see if applicable
            if (is_subclass_of($pascalCaseClass, DataExtension::class)) {
                // determine which core model this extension is for
                foreach ($coreModels as $coreClass) {
                    $instance = Injector::inst()->get($coreClass);

                    // check this core model to see if the dataextension with required
                    // interface has been applied
                    if ($instance::has_extension($pascalCaseClass)) {
                        $allowedClasses[strtolower($coreClass)] = $coreClass;
                    }
                }
            } else {
                $allowedClasses[$lowerCaseClass] = $pascalCaseClass;
            }
        }

        // check if user has access to that class
        return in_array($classType, $allowedClasses);
    }

    /**
     * Helper to get the pagination offset ID.
     *
     * @param HTTPRequest $request
     * @param string $classType
     *
     * @return int
     */
    protected function getOffsetID(HTTPRequest $request, $classType = '')
    {
        $afterFilter = $request->getVar('after');

        // we assume if not set, we start from the beginning
        if (!$afterFilter) {
            return 0;
        }

        // get the offset by taking number after class, e.g. SiteTree_0 becomes 0
        $offsetParts = explode('_', $afterFilter);

        // ensure the string is formatted as expected, otherwise set to 0
        return (int) (count($offsetParts) == 2) ? $offsetParts[1] : 0;
    }

    /**
     * Extract the data from the DB to surface via the API.
     *
     * The request can contain an "after" GET parameter to be used for getting
     * another set of items from the paginated set of data.
     *
     * @param HTTPRequest $request
     * @param string $classType
     * @param int $offsetId
     *
     * @return array
     */
    protected function getData(HTTPRequest $request, $classType = '', $offsetId = 0)
    {
        // store original reading mode to reset to after
        $originalMode = Versioned::get_reading_mode();

        // update to live reading mode to ensure only published content retrieved
        Versioned::set_reading_mode('Stage.Live');

        // sort by ID as this is how our pagination is working
        $objects = DataObject::get($classType)
            ->sort('ID')
            ->filter('ID:GreaterThan', $offsetId)
            ->limit($this->config()->pagination_limit);

        // revert reading mode
        Versioned::set_reading_mode($originalMode);

        // create list of objects with structured data formatted using a
        // customised schema
        $items = [];

        foreach ($objects as $object) {
            // check if user has access to this objects data
            if (!$object->hasMethod('canView') || $object->canView()) {
                array_push($items, $object->getSearchData());
            }
        }

        $data = [
            'request' => [
                'after' => sprintf('%s_%s', $classType, $offsetId)
            ],
            'items' => $items
        ];

        // allow implementors to modify this schema if required
        $this->extend('updateData', $data, $request);

        return $data;
    }

    /**
     * Helper to return an error response, ensuring the content-type is
     * 'application/json'.
     *
     * @param string $code
     * @param string $message
     *
     * @return SilverStripe\Control\HTTPResponse
     */
    protected function error($code = '', $message = '')
    {
        $this->response
            ->addHeader('Content-Type', 'application/json')
            ->setStatusCode($code)
            ->setBody(json_encode([
                'error' => [
                    'status' => $code,
                    'message' => $message
                ]
            ]));

        return $this->response;
    }

    /**
     * Setting up a permission code for the API.
     *
     * @return array
     */
    public function providePermissions()
    {
        return [
            self::API_PERMISSION_CODE => 'Ability to access the Search Data Extractor API'
        ];
    }

}
