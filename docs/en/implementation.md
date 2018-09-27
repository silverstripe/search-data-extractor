# Search Data Extractor - Implementation guide

The following outlines how you can use this module to expose objects through the
API.

## SearchDataExtractableInterface

The module provides an interface (`SearchDataExtractableInterface`) which is
used to determine the classes available to expose through the API.

The interface also defines the `getSearchData` method which is used to define
the "schema" to be used for the associated object.

You can expose an object by implementing this interface, or for core classes,
you can create a `DataExtension` which implements this interface.

## Examples

There are some unit tests you can use to get an idea of how to use this module,
but the following are some other basic examples you can use as a guide:

### Implementing interface directly

This example shows how you can expose the `Page` object through the API, with
a basic "schema".

`Page.php`

```
<?php

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\View\Parsers\ShortcodeParser;
use SilverStripe\SearchDataExtractor\Model\SearchDataExtractableInterface;

class Page extends SiteTree implements SearchDataExtractableInterface
{
    ...

    public function getSearchData()
    {
        return [
            'title' => $this->Title,
            'content' => ShortcodeParser::get_active()->parse($this->Content),
            'lastEdited' => $this->LastEdited
        ];
    }
}

```

After adding this to your object, it can now be exposed via:

`api/v1/search-data-extractor?class=Page`

### Implementing interface through DataExtension

This example shows how you can expose the `File` object through the API via
a `DataExtension`, with a basic "schema".

`FileSearchExposeExtension.php`

```
<?php

use SilverStripe\ORM\DataExtension;
use SilverStripe\SearchDataExtractor\Model\SearchDataExtractableInterface;

class FileSearchExposeExtension extends DataExtension implements SearchDataExtractableInterface
{
    ...

    public function getSearchData()
    {
        return [
            'title' => $this->owner->Title,
            'url' => $this->owner->getAbsoluteURL(),
            'extension' => $this->owner->getExtension()
        ];
    }
}

```

Then attach the extension and add to the `exposed_core_models` static:

`config.yml`

```
SilverStripe\Assets\File:
  extensions:
    - FileSearchExposeExtension

SilverStripe\SearchDataExtractor\Control\SearchDataExtractorAPIController:
  exposed_core_models:
    - SilverStripe\Assets\File
```

After adding this extension to your object, it can now be exposed via:

`api/v1/search-data-extractor?class=SilverStripe\Assets\File`

### Pagination

Records will be paginated based on the page size defined in `SearchDataExtractorAPIController::$pagination_limit`.
They're sorted by database identifier (`ID` column) in ascending order. To retrieve further pages,
you need to pass the database identifier of the current result into the `after` request parameter.
It needs to be prefixed with the type you're retrieving.

Example for records after database identifier `99` of type `Page`:

`api/v1/search-data-extractor?class=Page&after=Page_99`
