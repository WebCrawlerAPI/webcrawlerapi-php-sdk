# WebCrawler API PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webcrawlerapi/webcrawlerapi-php-sdk.svg?style=flat-square)](https://packagist.org/packages/webcrawlerapi/sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/webcrawlerapi/webcrawlerapi-php-sdk.svg?style=flat-square)](https://packagist.org/packages/webcrawlerapi/sdk)
[![License](https://img.shields.io/packagist/l/webcrawlerapi/webcrawlerapi-php-sdk.svg?style=flat-square)](https://packagist.org/packages/webcrawlerapi/sdk)

A PHP SDK for interacting with the WebCrawlerAPI - a powerful web crawling and scraping service.

## In order to use the API you have to get an API key from [WebCrawlerAPI](https://dash.webcrawlerapi.com/access)

## Requirements

- PHP 8.0 or higher
- Composer
- `ext-json` PHP extension
- Guzzle HTTP Client 7.0 or higher

## Installation

You can install the package via composer:

```bash
composer require webcrawlerapi/webcrawlerapi-php-sdk
```

## Usage

```php
use WebCrawlerAPI\WebCrawlerAPI;

// Initialize the client
$crawler = new WebCrawlerAPI('your_api_key');

// Synchronous crawling (blocks until completion)
$job = $crawler->crawl(
    url: 'https://example.com',
    scrapeType: 'markdown',
    itemsLimit: 10,
    webhookUrl: 'https://yourserver.com/webhook',
    allowSubdomains: false,
    maxPolls: 100  // Optional: maximum number of status checks
);
echo "Job completed with status: {$job->status}\n";

// Access job items and their content
foreach ($job->jobItems as $item) {
    echo "Page title: {$item->title}\n";
    echo "Original URL: {$item->originalUrl}\n";
    echo "Item status: {$item->status}\n";
    
    // Get the content based on job's scrape_type
    // Returns null if item is not in "done" status
    $content = $item->getContent();
    if ($content) {
        echo "Content length: " . strlen($content) . "\n";
        echo "Content preview: " . substr($content, 0, 200) . "...\n";
    } else {
        echo "Content not available or item not done\n";
    }
}

// Access job items and their parent job
foreach ($job->jobItems as $item) {
    echo "Item URL: {$item->originalUrl}\n";
    echo "Parent job status: {$item->job->status}\n";
    echo "Parent job URL: {$item->job->url}\n";
}

// Or use asynchronous crawling
$response = $crawler->crawlAsync(
    url: 'https://example.com',
    scrapeType: 'markdown',
    itemsLimit: 10,
    webhookUrl: 'https://yourserver.com/webhook',
    allowSubdomains: false
);

// Get the job ID from the response
$jobId = $response->id;
echo "Crawling job started with ID: {$jobId}\n";

// Check job status and get results
$job = $crawler->getJob($jobId);
echo "Job status: {$job->status}\n";

// Access job details
echo "Crawled URL: {$job->url}\n";
echo "Created at: {$job->createdAt->format('Y-m-d H:i:s')}\n";
echo "Number of items: " . count($job->jobItems) . "\n";

// Cancel a running job if needed
$cancelResponse = $crawler->cancelJob($jobId);
echo "Cancellation response: " . json_encode($cancelResponse) . "\n";
```

## API Methods

### crawl()
Starts a new crawling job and waits for its completion. This method will continuously poll the job status until:
- The job reaches a terminal state (done, error, or cancelled)
- The maximum number of polls is reached (default: 100)
- The polling interval is determined by the server's `recommendedPullDelayMs` or defaults to 5 seconds

### crawlAsync()
Starts a new crawling job and returns immediately with a job ID. Use this when you want to handle polling and status checks yourself, or when using webhooks.

### getJob()
Retrieves the current status and details of a specific job.

### cancelJob()
Cancels a running job. Any items that are not in progress or already completed will be marked as canceled and will not be charged.

## Parameters

### Crawl Methods (crawl and crawlAsync)
- `url` (required): The seed URL where the crawler starts. Can be any valid URL.
- `scrapeType` (default: "html"): The type of scraping you want to perform. Can be "html", "cleaned", or "markdown".
- `itemsLimit` (default: 10): Crawler will stop when it reaches this limit of pages for this job.
- `webhookUrl` (optional): The URL where the server will send a POST request once the task is completed.
- `allowSubdomains` (default: false): If true, the crawler will also crawl subdomains.
- `whitelistRegexp` (optional): A regular expression to whitelist URLs. Only URLs that match the pattern will be crawled.
- `blacklistRegexp` (optional): A regular expression to blacklist URLs. URLs that match the pattern will be skipped.
- `maxPolls` (optional, crawl only): Maximum number of status checks before returning (default: 100)

### Responses

#### CrawlAsync Response
The `crawlAsync()` method returns a `CrawlResponse` object with:
- `id`: The unique identifier of the created job

#### Job Response
The Job object contains detailed information about the crawling job:

- `id`: The unique identifier of the job
- `orgId`: Your organization identifier
- `url`: The seed URL where the crawler started
- `status`: The status of the job (new, in_progress, done, error)
- `scrapeType`: The type of scraping performed
- `createdAt`: The date when the job was created
- `finishedAt`: The date when the job was finished (if completed)
- `webhookUrl`: The webhook URL for notifications
- `webhookStatus`: The status of the webhook request
- `webhookError`: Any error message if the webhook request failed
- `jobItems`: Array of JobItem objects representing crawled pages
- `recommendedPullDelayMs`: Server-recommended delay between status checks

### JobItem Properties

Each JobItem object represents a crawled page and contains:

- `id`: The unique identifier of the item
- `jobId`: The parent job identifier
- `job`: Reference to the parent Job object
- `originalUrl`: The URL of the page
- `pageStatusCode`: The HTTP status code of the page request
- `status`: The status of the item (new, in_progress, done, error)
- `title`: The page title
- `createdAt`: The date when the item was created
- `cost`: The cost of the item in $
- `referredUrl`: The URL where the page was referred from
- `lastError`: Any error message if the item failed
- `getContent()`: Method to get the page content based on the job's scrapeType (html, cleaned, or markdown). Returns null if the item's status is not "done" or if content is not available. Content is automatically fetched and cached when accessed.
- `rawContentUrl`: URL to the raw content (if available)
- `cleanedContentUrl`: URL to the cleaned content (if scrapeType is "cleaned")
- `markdownContentUrl`: URL to the markdown content (if scrapeType is "markdown")

## License

MIT License