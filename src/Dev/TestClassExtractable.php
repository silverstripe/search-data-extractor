<?php

namespace SilverStripe\SearchDataExtractor\Dev;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

use SilverStripe\SearchDataExtractor\Model\SearchDataExtractableInterface;

class TestClassExtractable extends DataObject implements SearchDataExtractableInterface
{

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'HTMLText'
    ];

    /**
     * @return array
     */
    public function getSearchData()
    {
        return [
            'title' => $this->Title,
            'content' => $this->Content
        ];
    }

    /**
     * To allow these to be surfaced through the API.
     *
     * @param SilverStripe\Security\Member $member
     *
     * @return boolean
     */
    public function canView($member = null)
    {
        return true;
    }

}
