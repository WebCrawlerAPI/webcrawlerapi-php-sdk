# WebCrawler API PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webcrawlerapi/sdk.svg?style=flat-square)](https://packagist.org/packages/webcrawlerapi/sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/webcrawlerapi/sdk.svg?style=flat-square)](https://packagist.org/packages/webcrawlerapi/sdk)
[![License](https://img.shields.io/packagist/l/webcrawlerapi/sdk.svg?style=flat-square)](https://packagist.org/packages/webcrawlerapi/sdk)

A PHP SDK for interacting with the WebCrawlerAPI - a powerful web crawling and scraping service.

> In order to use the API you have to get an API key from [WebCrawlerAPI](https://dash.webcrawlerapi.com/access)

Read documentation at [WebCrawlerAPI Docs](https://webcrawlerapi.com/docs) for more information.

## Requirements

- PHP 8.0 or higher
- Composer
- `ext-json` PHP extension
- Guzzle HTTP Client 7.0 or higher

## Installation

You can install the package via composer:

```bash
composer require webcrawlerapi/sdk
```

## Usage

Scrape a single page and get its content as markdown:

```php
use WebCrawlerAPI\Models\ScrapeRequest;
use WebCrawlerAPI\Models\ScrapeResponseError;
use WebCrawlerAPI\WebCrawlerAPI;

$client = new WebCrawlerAPI('your_api_key');

$result = $client->scrape(new ScrapeRequest(
    url: 'https://example.com',
    outputFormats: ['markdown'],
));

if ($result instanceof ScrapeResponseError) {
    echo "Scrape failed: {$result->errorCode} - {$result->errorMessage}\n";
} else {
    echo $result->pageTitle . "\n";
    echo $result->markdown . "\n";
}
```

`scrape()` blocks until the result is ready. For non-blocking use there are also `scrapeAsync()` and `getScrape()`.

### Scrape parameters

`ScrapeRequest` accepts:

- `url` (required): The page to scrape.
- `outputFormats` (optional): Array of `markdown`, `cleaned`, `html`, `links`.
- `prompt` (optional): AI extraction prompt. Result is returned in `structuredData`.
- `responseSchema` (optional): JSON schema for the structured output of `prompt`.
- `cleanSelectors` (optional): CSS selectors of elements to remove.
- `mainContentOnly` (optional): Return only the main content of the page.
- `respectRobotsTxt` (optional): Respect the site's robots.txt.
- `maxAge` (optional): Maximum age of a cached result in seconds. Use `0` to always fetch fresh.
- `webhookUrl` (optional): URL that receives a POST request once the scrape is done.

### Scrape response

`ScrapeResponse` has `success`, `status`, `markdown`, `cleanedContent`, `rawContent`, `links`, `structuredData`, `pageTitle` and `pageStatusCode`. API errors are returned as a `ScrapeResponseError` with `errorCode` and `errorMessage`.

## Crawling

To crawl a whole site, use `crawl()` or `crawlAsync()`. See the [crawling guide](docs/crawling.md).

## Testing

### Running Tests

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Run unit tests:**
   ```bash
   vendor/bin/phpunit tests/Unit --testdox
   ```

3. **Run integration tests (optional, requires API key):**
   ```bash
   export WEBCRAWLER_API_KEY="your-api-key"
   vendor/bin/phpunit tests/Integration --testdox
   ```

Or use the test runner script: `./run-tests.sh`

## License

MIT License
