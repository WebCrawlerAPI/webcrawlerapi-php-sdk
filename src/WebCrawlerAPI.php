<?php

namespace WebCrawlerAPI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use WebCrawlerAPI\Models\Job;
use WebCrawlerAPI\Models\CrawlResponse;
use WebCrawlerAPI\Models\JobMarkdownResponse;
use WebCrawlerAPI\Models\ScrapeId;
use WebCrawlerAPI\Models\ScrapeRequest;
use WebCrawlerAPI\Models\ScrapeResponse;
use WebCrawlerAPI\Models\ScrapeResponseError;

class WebCrawlerAPI
{
    private const INITIAL_PULL_DELAY_MS = 2000;
    private const SCRAPE_POLL_DELAY_SECONDS = 2;
    private const SCRAPE_VERSION = 'v2';
    private const SCRAPE_BASE_VERSIONED = '/v2/scrape';
    private string $apiKey;
    private string $baseUrl;
    private string $version;
    private Client $client;
    private Client $scrapeClient;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.webcrawlerapi.com',
        string $version = 'v1'
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->version = $version;
        $commonHeaders = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => $commonHeaders,
        ]);

        $this->scrapeClient = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => $commonHeaders,
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function crawlAsync(
        string $url,
        /** @deprecated Use $outputFormats instead */
        string $scrapeType = 'markdown',
        int $itemsLimit = 10,
        ?string $webhookUrl = null,
        ?string $whitelistRegexp = null,
        ?string $blacklistRegexp = null,
        bool $mainContentOnly = false,
        ?int $maxDepth = null,
        ?int $maxAge = null,
        ?array $outputFormats = null,
        ?array $actions = null,
        ?bool $respectRobotsTxt = null
    ): CrawlResponse {
        // output_formats takes precedence; fall back to converting scrapeType for backward compat
        $resolvedOutputFormats = $outputFormats ?? [$scrapeType];

        $payload = [
            'url' => $url,
            'output_formats' => $resolvedOutputFormats,
            'items_limit' => $itemsLimit,
        ];

        if ($webhookUrl !== null) {
            $payload['webhook_url'] = $webhookUrl;
        }
        if ($maxAge !== null) {
            $payload['max_age'] = $maxAge;
        }
        if ($whitelistRegexp !== null) {
            $payload['whitelist_regexp'] = $whitelistRegexp;
        }
        if ($blacklistRegexp !== null) {
            $payload['blacklist_regexp'] = $blacklistRegexp;
        }
        if ($mainContentOnly) {
            $payload['main_content_only'] = $mainContentOnly;
        }
        if ($maxDepth !== null) {
            $payload['max_depth'] = $maxDepth;
        }
        if ($actions !== null) {
            $payload['actions'] = array_values($actions);
        }
        if ($respectRobotsTxt !== null) {
            $payload['respect_robots_txt'] = $respectRobotsTxt;
        }

        $response = $this->client->post("/{$this->version}/crawl", [
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        if (!isset($data['id'])) {
            throw new \RuntimeException('Invalid API response: missing id field');
        }
        return new CrawlResponse($data['id']);
    }

    /**
     * @throws GuzzleException
     */
    public function getJob(string $jobId): Job
    {
        $response = $this->client->get("/{$this->version}/job/{$jobId}");
        $data = json_decode($response->getBody()->getContents(), true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid API response: expected array');
        }
        return new Job($data);
    }

    /**
     * @throws GuzzleException
     */
    public function cancelJob(string $jobId): array
    {
        $response = $this->client->put("/{$this->version}/job/{$jobId}/cancel");
        $data = json_decode($response->getBody()->getContents(), true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid API response: expected array');
        }
        return $data;
    }

    /**
     * @throws GuzzleException
     */
    public function crawl(
        string $url,
        /** @deprecated Use $outputFormats instead */
        string $scrapeType = 'markdown',
        int $itemsLimit = 10,
        ?string $webhookUrl = null,
        ?string $whitelistRegexp = null,
        ?string $blacklistRegexp = null,
        bool $mainContentOnly = false,
        ?int $maxDepth = null,
        ?int $maxAge = null,
        int $maxPolls = 100,
        ?array $outputFormats = null,
        ?array $actions = null,
        ?bool $respectRobotsTxt = null
    ): Job {
        $response = $this->crawlAsync(
            $url,
            $scrapeType,
            $itemsLimit,
            $webhookUrl,
            $whitelistRegexp,
            $blacklistRegexp,
            $mainContentOnly,
            $maxDepth,
            $maxAge,
            $outputFormats,
            $actions,
            $respectRobotsTxt
        );

        $delayMs = self::INITIAL_PULL_DELAY_MS;
        for ($i = 0; $i < $maxPolls; $i++) {
            // Wait first, then poll (matching JS SDK behaviour)
            usleep($delayMs * 1000);
            $timestamp = (int)(microtime(true) * 1000);
            $job = $this->getJob("{$response->id}?t={$timestamp}");

            if ($job->isTerminal()) {
                return $job;
            }

            if ($job->recommendedPullDelayMs > 0) {
                $delayMs = $job->recommendedPullDelayMs;
            }
        }

        throw new \RuntimeException('Crawling took too long, please retry or increase the number of polling retries');
    }

    /**
     * @throws GuzzleException
     */
    public function getJobMarkdown(string $jobId): JobMarkdownResponse
    {
        $response = $this->client->get("/{$this->version}/job/{$jobId}/markdown");
        $data = json_decode($response->getBody()->getContents(), true);
        if (!is_array($data) || !isset($data['content_url'])) {
            throw new \RuntimeException('Invalid API response: missing content_url field');
        }
        return new JobMarkdownResponse($data['content_url']);
    }

    /**
     * @throws GuzzleException
     */
    public function getJobMarkdownContent(string $jobId): string
    {
        $response = $this->client->get("/{$this->version}/job/{$jobId}/markdown/content");
        return $response->getBody()->getContents();
    }

    /**
     * @throws GuzzleException
     */
    public function crawlRawMarkdown(
        string $url,
        int $itemsLimit = 10,
        ?string $webhookUrl = null,
        ?string $whitelistRegexp = null,
        ?string $blacklistRegexp = null,
        bool $mainContentOnly = false,
        ?int $maxDepth = null,
        ?int $maxAge = null,
        int $maxPolls = 100
    ): string {
        $job = $this->crawl(
            $url,
            'markdown',
            $itemsLimit,
            $webhookUrl,
            $whitelistRegexp,
            $blacklistRegexp,
            $mainContentOnly,
            $maxDepth,
            $maxAge,
            $maxPolls
        );

        if ($job->status !== 'done') {
            throw new \RuntimeException("Job finished with status {$job->status}");
        }

        return $this->getJobMarkdownContent($job->id);
    }

    /**
     * @throws GuzzleException
     */
    public function scrapeAsync(ScrapeRequest $request): ScrapeId
    {
        $response = $this->scrapeClient->post(self::SCRAPE_BASE_VERSIONED . '?async=true', [
            'json' => $request->toPayload(),
            'headers' => [
                'User-Agent' => 'WebcrawlerAPI-PHP-Client',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        if (!isset($data['id'])) {
            throw new \RuntimeException('Invalid API response: missing id field');
        }

        return new ScrapeId($data['id']);
    }

    /**
     * @throws GuzzleException
     */
    public function getScrape(string $scrapeId): ScrapeResponse|ScrapeResponseError
    {
        $response = $this->scrapeClient->get(self::SCRAPE_BASE_VERSIONED . "/{$scrapeId}", [
            'headers' => [
                'User-Agent' => 'WebcrawlerAPI-PHP-Client',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid API response: expected array');
        }

        $status = $data['status'] ?? null;

        if ($status === 'done') {
            return new ScrapeResponse(
                success: (bool)($data['success'] ?? false),
                status: $status,
                markdown: $data['markdown'] ?? null,
                cleanedContent: $data['cleaned_content'] ?? null,
                rawContent: $data['raw_content'] ?? null,
                pageStatusCode: (int)($data['page_status_code'] ?? 0),
                pageTitle: $data['page_title'] ?? null,
                structuredData: $data['structured_data'] ?? null,
                links: $data['links'] ?? null
            );
        }

        if ($status === 'error' || isset($data['error_code'])) {
            return new ScrapeResponseError(
                success: (bool)($data['success'] ?? false),
                errorCode: $data['error_code'] ?? 'unknown_error',
                errorMessage: $data['error_message'] ?? $data['error'] ?? 'Unknown error',
                status: $status
            );
        }

        // in_progress or any other non-terminal status — return minimal ScrapeResponse (not an error)
        return new ScrapeResponse(
            success: false,
            status: $status,
            pageStatusCode: 0
        );
    }

    /**
     * Scrapes a URL synchronously using the /v2/scrape endpoint (blocks until result is ready).
     *
     * @throws GuzzleException
     */
    public function scrape(ScrapeRequest $request): ScrapeResponse|ScrapeResponseError
    {
        $response = $this->scrapeClient->post(self::SCRAPE_BASE_VERSIONED, [
            'json' => $request->toPayload(),
            'headers' => [
                'User-Agent' => 'WebcrawlerAPI-PHP-Client',
            ],
            'http_errors' => false,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400 || isset($data['error_code'])) {
            return new ScrapeResponseError(
                success: false,
                errorCode: $data['error_code'] ?? 'unknown_error',
                errorMessage: $data['error_message'] ?? 'Unknown error',
                status: $data['status'] ?? null
            );
        }

        return new ScrapeResponse(
            success: (bool)($data['success'] ?? false),
            status: $data['status'] ?? null,
            markdown: $data['markdown'] ?? null,
            cleanedContent: $data['cleaned_content'] ?? null,
            rawContent: $data['raw_content'] ?? null,
            pageStatusCode: (int)($data['page_status_code'] ?? 0),
            pageTitle: $data['page_title'] ?? null,
            structuredData: $data['structured_data'] ?? null,
            links: $data['links'] ?? null
        );
    }
}
