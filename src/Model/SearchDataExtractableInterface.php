<?php

namespace SilverStripe\SearchDataExtractor\Model;

/**
 * This interface is used to provide the {@see SearchDataExtractorAPIController}
 * a list of models that can be retrieved through the API.
 */
interface SearchDataExtractableInterface
{

    /**
     * The method used by the {@link SearchDataExtractorAPIController} to
     * extract the required data from the model(s) that are being requested
     * through the API.
     *
     * This will be mapped to the custom schema required by the search system
     * retrieving the data through the API, for each applicable model.
     *
     * A simple example if applied to the Page model:
     *
     * ```php
     * return [
     *     'title' => $this->Title,
     *     'body' => ShortcodeParser::get_active()->parse($this->Content),
     *     'lastEdited' => $this->LastEdited
     * ];
     * ```
     *
     * @return array
     */
    public function getSearchData();

}
