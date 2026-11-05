# Batch scrape

Batch scrape sends a list of URLs to the API in one async request. The API scrapes them in the background and you read the results page by page. Use it when you already know the URLs. To discover pages on a site use [crawling](crawling.md). For one page use `scrape()` with a single URL.

See also: [batch scrape guide](https://webcrawlerapi.com/docs/guides/batch-scrape) and [API reference](https://webcrawlerapi.com/docs/api/batch-scrape).

## Quick example

```php
use WebCrawlerAPI\WebCrawlerAPI;

$client = new WebCrawlerAPI('YOUR_API_KEY');

// Creates the batch and polls until it is no longer "scraping"
$batch = $client->scrape([
    'https://example.com/',
    'https://example.com/pricing',
]);

echo "{$batch->status}: {$batch->completed}/{$batch->total} done, {$batch->failed} failed\n";
foreach ($batch->data as $item) {
    echo $item->url . ' ' . ($item->markdownUrl ?? $item->errorCode) . "\n";
}
```

Methods (all on `WebCrawlerAPI`):

| Method | Purpose |
| --- | --- |
| `scrape($request, $maxPolls)` | Create a batch and wait for it (list of URLs) |
| `scrapeBatchAsync($request)` | Create a batch and return immediately |
| `getBatch($batchId, $limit, $cursor)` | Progress and one page of items |
| `getBatchErrors($batchId)` | Failed URLs and robots.txt-blocked URLs |
| `cancelBatch($batchId)` | Cancel a running batch |

## ScrapeRequest options

A batch request is a `ScrapeRequest` whose `url` is an array of strings, or a plain array of URLs (which uses default options).

```php
new ScrapeRequest(
    url: ['https://a.com', 'https://b.com'],
    outputFormats: ['markdown'],
    mainContentOnly: true,
    cleanSelectors: '.cookie-banner',
    maxConcurrency: 5,
    ignoreInvalidUrls: true,
    webhookUrl: 'https://your-server.com/webhook',
    maxAge: 86400,
    respectRobotsTxt: false,
);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `url` | `string\|string[]` | A list of URLs makes it a batch. Max is your plan's job items limit (100 during trial). |
| `outputFormats` | `?array` | `markdown`, `cleaned`, `html`, `links`. API default is `["markdown"]`. |
| `outputFormat` | `?string` | Deprecated. Used only when `outputFormats` is null; sent as a one-item `output_formats`. |
| `mainContentOnly` | `?bool` | Extract only the main content. |
| `cleanSelectors` | `?string` | CSS selectors to clean from the output. |
| `maxConcurrency` | `?int` | Batch only. Max URLs scraped in parallel. `0` or null means no cap. |
| `ignoreInvalidUrls` | `?bool` | Batch only. Skip invalid URLs and report them in `invalidUrls`. API default is `true`; when `false`, any invalid URL fails the request. |
| `webhookUrl` | `?string` | Public http(s) URL called once when the batch finishes. |
| `maxAge` | `?int` | Cache duration in seconds. API default is 7 days; `0` bypasses the cache. |
| `respectRobotsTxt` | `?bool` | Respect each site's robots.txt. API default is `false`. |

`prompt` and `responseSchema` are not supported in batches and are not sent. Options left as null are omitted from the request.

## scrape()

```php
public function scrape(ScrapeRequest|array $request, ?int $maxPolls = null): ScrapeResponse|ScrapeResponseError|BatchScrapeResponse
```

With a list of URLs, creates the batch, then polls `getBatch()` until the status is `completed`, `failed` or `cancelled`. Returns a `BatchScrapeResponse` with the first page of items (API default page size, 100) and the `next` cursor. Fetch more pages with `getBatch()`.

Polling waits 2 seconds between checks and stops after 400 seconds in total. `$maxPolls` is an optional extra cap on the number of polls. If a limit is reached the SDK does not throw: it returns the last known `BatchScrapeResponse`, whose `status` is still `scraping`. Check `$batch->isTerminal()` and keep polling with `getBatch()` if needed. The batch keeps running on the server.

Invalid URLs are not returned by this method. Use `scrapeBatchAsync()` if you need `invalidUrls`.

Throws `WebcrawlerApiException` on API or network errors (for example 401, 402, 400 when there are no valid URLs).

## scrapeBatchAsync()

```php
public function scrapeBatchAsync(ScrapeRequest|array $request): BatchScrapeId
```

Calls `POST /v2/batch/scrape` and returns immediately. Throws `WebcrawlerApiException` (`unknown_error`, "Batch scrape requires a list of URLs") if the request url is not an array, and `invalid_response` if the response has no `id`.

`BatchScrapeId` fields:

- `id` (string): batch id
- `statusUrl` (string): URL of the status endpoint
- `invalidUrls` (`BatchScrapeInvalidUrl[]`): URLs rejected up front, each with `url`, `errorCode`, `errorMessage`

URLs are normalized and deduplicated. Duplicates, malformed URLs, non-public addresses and unresolvable domains land in `invalidUrls` (see [errors](https://webcrawlerapi.com/docs/errors)). A page whose content duplicates another page in the batch fails later as an item, not here.

```php
$started = $client->scrapeBatchAsync(new ScrapeRequest(
    url: ['https://example.com/', 'not a url'],
    webhookUrl: 'https://your-server.com/webhook',
));
echo $started->id . "\n";
foreach ($started->invalidUrls as $bad) {
    echo "{$bad->url}: {$bad->errorCode} {$bad->errorMessage}\n";
}
```

## getBatch()

```php
public function getBatch(string $batchId, ?int $limit = null, ?string $cursor = null): BatchScrapeResponse
```

Calls `GET /v2/batch/scrape/:id`. `$limit` is items per page, 1 to 500 (API default 100). `$cursor` is the `next` value of the previous page. The request is sent with no-cache headers.

`BatchScrapeResponse` fields:

- `id`, `status` (`scraping`, `completed`, `failed`, `cancelled`)
- `total`, `completed`, `failed`, `canceled` (int counters)
- `data` (`BatchScrapeItem[]`): one page of items
- `next` (string): cursor for the next page, empty on the last page
- `isTerminal()`: true when status is `completed`, `failed` or `cancelled`

`BatchScrapeItem` fields: `url`, `status` (for example `done` or `error`), `pageStatusCode`, `title`, `markdownUrl`, `cleanedUrl`, `htmlUrl` (links to the output, only for finished items and requested formats), `fromCache`, `errorCode`, `errorMessage` (set for failed items).

### Pagination

```php
$page = $client->getBatch($batchId, limit: 500);
while (true) {
    foreach ($page->data as $item) {
        // handle $item
    }
    if ($page->next === '') {
        break;
    }
    $page = $client->getBatch($batchId, limit: 500, cursor: $page->next);
}
```

## getBatchErrors()

```php
public function getBatchErrors(string $batchId): BatchScrapeErrors
```

Calls `GET /v2/batch/scrape/:id/errors`. Returns:

- `errors` (`BatchScrapeInvalidUrl[]`): failed URLs with `url`, `errorCode`, `errorMessage`
- `robotsBlocked` (`string[]`): URLs blocked by robots.txt (when `respectRobotsTxt` is `true`)

```php
$errors = $client->getBatchErrors($batchId);
foreach ($errors->errors as $e) {
    echo "{$e->url}: {$e->errorCode}\n";
}
```

To retry failed URLs, submit them in a new batch.

## cancelBatch()

```php
public function cancelBatch(string $batchId): array
```

Calls `DELETE /v2/batch/scrape/:id` and returns the decoded response (for example `['status' => 'canceled']`). Items that did not start are marked `canceled` and are not charged. The API returns 400 if the batch has already finished, which throws `WebcrawlerApiException`.

## Webhook or polling

- Polling: use `scrape()` to block, or `scrapeBatchAsync()` plus `getBatch()` in your own loop. Poll every few seconds, not faster.
- Webhook: pass `webhookUrl`. The API sends one `POST` with the same body as `getBatch()` (first 100 items) when the batch finishes. For larger batches fetch the remaining pages with `cursor`. See [webhooks](https://webcrawlerapi.com/docs/async-requests).

## Errors

All methods throw `WebcrawlerApiException` for API errors (with the HTTP status code) and network failures (`network_error`). Per-URL failures are not exceptions: they appear as items with `status` `error` and `errorCode`, and in `getBatchErrors()`.
