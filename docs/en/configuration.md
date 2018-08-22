# Search Data Extractor - Configuration

These are some of the configuration options that are provided with the module.

### Disabling HTTPS locally

You can disable the HTTPS check locally for development environments by adding
the `DISABLE_SEARCH_DATA_EXTRACTOR_HTTPS` environment variable and setting to
`true`, e.g.

```
DISABLE_SEARCH_DATA_EXTRACTOR_HTTPS=true
```

### Changing pagination length

You can set the `pagination_limit` static to modify the amount of items that
will be returned through the API per "page" of results.

An example of changing this to `10` in your `config.yml`:

```
SilverStripe\SearchDataExtractor\Control\SearchDataExtractorAPIController:
  pagination_length: 10
```