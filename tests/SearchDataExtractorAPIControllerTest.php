<?php

namespace SilverStripe\SearchDataExtractor\Tests;

use \ReflectionMethod;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\BasicAuth;

use SilverStripe\SearchDataExtractor\Control\SearchDataExtractorAPIController;
use SilverStripe\SearchDataExtractor\Dev\TestClassExtractable;
use SilverStripe\SearchDataExtractor\Dev\TestClassNotExtractable;

class SearchDataExtractorAPIControllerTest extends FunctionalTest
{

    /**
     * The API endpoint used for the search-data-extractor module, which is
     * defined in the routes.yml.
     *
     * @var string
     */
    const API_ENDPOINT = '/api/v1/search-data-extractor';

    /**
     * To store the original value of the ignore_cli static
     *
     * @var boolean
     */
    private $originalIgnore;

    /**
     * @var boolean
     */
    protected static $fixture_file = 'fixtures/SearchDataExtractorAPIControllerTest.yml';

    /**
     * Using this to store the original value used for BasicAuth::$ignore_cli so
     * we can reset after tests.
     */
    public function setUp()
    {
        $this->originalIgnore = Config::inst()->get(BasicAuth::class, 'ignore_cli');

        parent::setUp();
    }

    /**
     * Using this to restore the original BasicAuth::$ignore_cli value.
     */
    public function tearDown()
    {
        // reset value
        Config::inst()->set(BasicAuth::class, 'ignore_cli', $this->originalIgnore);

        parent::tearDown();
    }

    public function testInitAuthenticationFail()
    {
        // do not bypass basic auth for this test
        Config::inst()->set(BasicAuth::class, 'ignore_cli', false);

        $result = $this->get(self::API_ENDPOINT);

        $this->assertEquals(HTTPResponse::class, get_class($result));
        $this->assertEquals(401, $result->getStatusCode());
        $this->assertEquals('Please enter a username and password.', $result->getBody());
    }

    public function testInitAuthenticationSuccess()
    {
        // do not bypass basic auth for this test
        Config::inst()->set(BasicAuth::class, 'ignore_cli', false);

        $result = $this->get(self::API_ENDPOINT, null, [
            'PHP_AUTH_USER' => 'api-member',
            'PHP_AUTH_PW' => 'api-member-pw'
        ]);

        $this->assertEquals(HTTPResponse::class, get_class($result));
        $this->assertEquals(400, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeader('content-type'));

        $body = json_decode($result->getBody());

        $this->assertTrue(isset($body->error));
        $this->assertTrue(isset($body->error->status));
        $this->assertTrue(isset($body->error->message));
        $this->assertEquals(400, $body->error->status);
        $this->assertEquals('Sorry you need to specify a class.', $body->error->message);
    }

    public function testValidateAllowedClassFail()
    {
        $result = $this->get(self::API_ENDPOINT . '?class=' . TestClassNotExtractable::class);

        $this->assertEquals(HTTPResponse::class, get_class($result));
        $this->assertEquals(403, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeader('content-type'));

        $body = json_decode($result->getBody());

        $this->assertTrue(isset($body->error));
        $this->assertTrue(isset($body->error->status));
        $this->assertTrue(isset($body->error->message));
        $this->assertEquals(403, $body->error->status);
        $this->assertEquals('Sorry that class is not valid.', $body->error->message);
    }

    public function testValidateAllowedClassSuccess()
    {
        $result = $this->get(self::API_ENDPOINT . '?class=' . TestClassExtractable::class);

        $this->assertEquals(HTTPResponse::class, get_class($result));
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeader('content-type'));

        $body = json_decode($result->getBody());

        $this->assertTrue(isset($body->request));
        $this->assertTrue(isset($body->request->after));
        $this->assertTrue(isset($body->items));
    }

    public function testGetDataPagination()
    {
        $originalPagination = Config::inst()->get(
            SearchDataExtractorAPIController::class,
            'pagination_limit'
        );

        Config::inst()->set(
            SearchDataExtractorAPIController::class,
            'pagination_limit',
            1
        );

        // first page of items
        $result = $this->get(self::API_ENDPOINT . '?class=' . TestClassExtractable::class);

        $body = json_decode($result->getBody());

        $this->assertTrue(isset($body->items));
        $this->assertTrue(is_array($body->items));
        $this->assertEquals(1, count($body->items));
        $this->assertEquals('One', $body->items[0]->title);
        $this->assertEquals('Content for One', $body->items[0]->content);

        // second page of items
        $result = $this->get(
            self::API_ENDPOINT .
            '?class=' .
            TestClassExtractable::class .
            '&after=' .
            TestClassExtractable::class . '_1'
        );

        $body = json_decode($result->getBody());

        $this->assertTrue(isset($body->items));
        $this->assertTrue(is_array($body->items));
        $this->assertEquals(1, count($body->items));
        $this->assertEquals('Two', $body->items[0]->title);
        $this->assertEquals('Content for Two', $body->items[0]->content);

        // reset pagination length
        Config::inst()->set(
            SearchDataExtractorAPIController::class,
            'pagination_limit',
            $originalPagination
        );
    }

}
