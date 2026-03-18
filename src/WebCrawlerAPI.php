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
    private const DEFAULT_POLL_DELAY_SECONDS = 5;
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
        string $scrapeType = 'html',
        int $itemsLimit = 10,
        ?string $webhookUrl = null,
        ?string $whitelistRegexp = null,
        ?string $blacklistRegexp = null,
        bool $mainContentOnly = false,
        ?int $maxDepth = null,
        ?int $maxAge = null
    ): CrawlResponse {
        $payload = [
            'url' => $url,
            'scrape_type' => $scrapeType,
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
        string $scrapeType = 'html',
        int $itemsLimit = 10,
        ?string $webhookUrl = null,
        ?string $whitelistRegexp = null,
        ?string $blacklistRegexp = null,
        bool $mainContentOnly = false,
        ?int $maxDepth = null,
        ?int $maxAge = null,
        int $maxPolls = 100
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
            $maxAge
        );


        $polls = 0;
        while ($polls < $maxPolls) {
            $job = $this->getJob($response->id);

            if ($job->isTerminal()) {
                return $job;
            }

            $delaySeconds = $job->recommendedPullDelayMs
                ? (int)($job->recommendedPullDelayMs / 1000)
                : self::DEFAULT_POLL_DELAY_SECONDS;

            sleep($delaySeconds);
            $polls++;
        }

        return $job;
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

        if (isset($data['error_code'])) {
            return new ScrapeResponseError(
                success: $data['success'] ?? false,
                errorCode: $data['error_code'],
                errorMessage: $data['error_message'] ?? '' ,
                status: $data['status'] ?? null
            );
        }

        if (isset($data['status']) && $data['status'] !== 'done' && $data['status'] !== 'error') {
            return new ScrapeResponseError(
                success: false,
                errorCode: 'in_progress',
                errorMessage: 'Scrape in progress',
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

    /**
     * @throws GuzzleException
     */
    public function scrape(ScrapeRequest $request, int $maxPolls = 100): ScrapeResponse|ScrapeResponseError
    {
        $scrapeId = $this->scrapeAsync($request);

        $polls = 0;
        $result = null;
        while ($polls < $maxPolls) {
            $result = $this->getScrape($scrapeId->id);

            if ($result instanceof ScrapeResponseError && $result->status !== 'in_progress') {
                return $result;
            }

            if ($result instanceof ScrapeResponse && ($result->status === 'done' || $result->success)) {
                return $result;
            }

            sleep(self::SCRAPE_POLL_DELAY_SECONDS);
            $polls++;
        }

        return $result ?? new ScrapeResponseError(false, 'timeout', 'Scrape polling timed out', null);
    }
}
