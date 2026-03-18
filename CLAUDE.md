# CLAUDE.md

This file provides guidance to AI coding agent when working with the WebCrawlerAPI PHP SDK.

## WebCrawlerAPI PHP SDK

This is the PHP SDK for WebCrawlerAPI, providing a clean object-oriented interface for web scraping and crawling operations.

### Core Features

- **Synchronous & Asynchronous Operations**: Support for both blocking and non-blocking scraping/crawling
- **Multiple Output Formats**: Markdown, cleaned HTML, and raw HTML
- **AI-Powered Extraction**: Use prompts to extract specific information from pages
- **Structured Outputs**: JSON Schema-based type-safe responses via `response_schema` parameter
- **Lazy Content Loading**: JobItem content fetched on-demand to optimize memory usage
- **Polling with Backoff**: Automatic polling with server-recommended delays

### Recent Features

#### Structured Outputs (response_schema)

Added support for JSON Schema-based structured responses:
- Define a JSON schema to enforce type-safety on AI responses
- Available on scrape operations via `responseSchema` parameter in `ScrapeRequest`
- Works with the `prompt` parameter
- Response data returned in `structuredData` field

**Example:**
```php
use WebCrawlerAPI\WebCrawlerAPI;
use WebCrawlerAPI\Models\ScrapeRequest;

$client = new WebCrawlerAPI('your_api_key');

$request = new ScrapeRequest(
    url: 'https://example.com',
    prompt: 'Extract product information',
    responseSchema: [
        'type' => 'object',
        'properties' => [
            'product_name' => ['type' => 'string'],
            'price' => ['type' => 'number'],
            'in_stock' => ['type' => 'boolean']
        ],
        'required' => ['product_name', 'price', 'in_stock'],
        'additionalProperties' => false
    ]
);

$result = $client->scrape($request);
print_r($result->structuredData);  // Type-safe structured output
```

#### Links Field
- Added `links` field to `ScrapeResponse` to return list of URLs found on page
- Accessible via `$response->links` property

### Project Structure

```
webcrawlerapi-php/
├── src/
│   ├── WebCrawlerAPI.php        # Main client class
│   └── Models/
│       ├── ScrapeRequest.php    # Scrape request parameters
│       ├── ScrapeResponse.php   # Scrape response with content
│       ├── ScrapeResponseError.php # Error response
│       ├── ScrapeId.php         # Async scrape identifier
│       ├── CrawlResponse.php    # Crawl job identifier
│       ├── Job.php              # Crawl job with items
│       └── JobItem.php          # Individual crawled page
├── composer.json
└── README.md
```

### Testing

#### Comprehensive Test Suite

A comprehensive test suite is available in `../php-test-local/tests/` that tests the SDK against real API endpoints.

**Running Tests:**

```bash
cd ../php-test-local
composer install
composer dump-autoload
php run-tests.php
```

The test suite includes:
- **Scrape Tests** (5 tests): Tests various output formats, prompts, and structured outputs
- **Scrape Async Tests** (2 tests): Tests async scraping with polling
- **Crawl Tests** (4 tests): Tests crawling with filters and options
- **Crawl Async Tests** (1 test): Tests async crawling
- **Markdown Tests** (2 tests): Tests markdown extraction methods
- **Job Tests** (2 tests): Tests job and scrape retrieval

**Total Tests**: 16 tests across 6 modules
**Target**: 100% success rate

**Test Output Example:**
```
╔════════════════════════════════════════════════════════════╗
║    WebCrawlerAPI PHP SDK - Comprehensive Test Suite       ║
╚════════════════════════════════════════════════════════════╝

┌─ Scrape Tests ─────────────────────────
│ ✓ Scrape                                    2345ms
│ ✓ Scrape With Prompt                        3210ms
│ ✓ Scrape With Structured Output             3567ms
│ ✓ Scrape Cleaned Format                     2198ms
│ ✓ Scrape Html Format                        2301ms
└────────────────────────────────────────────────────────────

...

╔════════════════════════════════════════════════════════════╗
║                      Test Summary                          ║
╠════════════════════════════════════════════════════════════╣
║  Total Tests:     16                                       ║
║  Passed:          16 (100%)                                ║
║  Failed:           0                                       ║
║  Duration:       45.23 seconds                             ║
╚════════════════════════════════════════════════════════════╝

✅ All tests passed!
```

### SDK Usage Patterns

#### Synchronous Scraping
```php
use WebCrawlerAPI\WebCrawlerAPI;
use WebCrawlerAPI\Models\ScrapeRequest;

$client = new WebCrawlerAPI('your_api_key');

$request = new ScrapeRequest(
    url: 'https://example.com',
    outputFormat: 'markdown'
);

$result = $client->scrape($request);
echo $result->markdown;
```

#### Asynchronous Scraping
```php
$scrapeId = $client->scrapeAsync($request);

// Poll for completion
do {
    $result = $client->getScrape($scrapeId->id);
    if ($result instanceof ScrapeResponse && $result->success) {
        break;
    }
    sleep(2);
} while (true);
```

#### Synchronous Crawling
```php
$job = $client->crawl(
    url: 'https://example.com',
    scrapeType: 'markdown',
    itemsLimit: 10,
    maxDepth: 2
);

foreach ($job->items as $item) {
    echo $item->url . "\n";
    $content = $item->getContent(); // Lazy loading
}
```

#### AI-Powered Extraction with Structured Output
```php
$request = new ScrapeRequest(
    url: 'https://example.com/product',
    prompt: 'Extract product details',
    responseSchema: [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'price' => ['type' => 'number'],
            'available' => ['type' => 'boolean']
        ],
        'required' => ['name', 'price', 'available']
    ]
);

$result = $client->scrape($request);
// Result is guaranteed to match the schema
$productName = $result->structuredData['name'];
$price = $result->structuredData['price'];
$available = $result->structuredData['available'];
```

### Development Commands

**SDK Development:**
```bash
cd webcrawlerapi-php
composer install          # Install dependencies
composer dump-autoload    # Regenerate autoloader
```

**Running Tests:**
```bash
cd ../php-test-local
composer install
composer dump-autoload
php run-tests.php
```

### Key Technologies

- **PHP 8.0+** - Required for modern syntax (constructor property promotion, match expressions)
- **Guzzle HTTP 7.0+** - HTTP client for API communication
- **ext-json** - JSON encoding/decoding

### API Integration

- **Base URL**: `https://api.webcrawlerapi.com`
- **Authentication**: Bearer token in Authorization header
- **Scrape Endpoint**: `/v2/scrape` (latest version)
- **Crawl Endpoint**: `/v1/crawl`
- **Job Endpoint**: `/v1/job/{id}`

### Error Handling

The SDK uses structured error responses:
- `ScrapeResponseError` for scrape failures with error codes and messages
- `GuzzleException` for HTTP communication errors
- `RuntimeException` for invalid API responses
- `InvalidArgumentException` for missing required fields

### Model Validation

All models implement strict validation:
- Required field checking in constructors
- Proper null handling for optional fields
- Type casting for numeric and boolean fields
- DateTime parsing for timestamp fields

### Best Practices

1. **Use maxAge parameter**: Set `maxAge: 0` in tests to bypass cache
2. **Error handling**: Always check if result is `ScrapeResponse` or `ScrapeResponseError`
3. **Lazy loading**: Use `JobItem->getContent()` only when needed to save bandwidth
4. **Polling delays**: Use server-recommended delays from `Job->recommendedPullDelayMs`
5. **Terminal state checking**: Use `Job->isTerminal()` to check completion
6. **Structured outputs**: Define complete JSON schemas with required fields for reliable extraction

### Contributing

When adding new features:
1. Update the SDK models in `src/Models/`
2. Update the client in `src/WebCrawlerAPI.php`
3. Add tests to `../php-test-local/tests/`
4. Update this CLAUDE.md file
5. Run the test suite to verify

### Support

For issues or questions:
- SDK Repository: `sdk/php/webcrawlerapi-php/`
- Test Suite: `sdk/php/php-test-local/`
- API Documentation: See main project docs
