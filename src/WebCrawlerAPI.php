<?php

namespace WebCrawlerAPI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use WebCrawlerAPI\Models\Job;
use WebCrawlerAPI\Models\CrawlResponse;

class WebCrawlerAPI
{
    private const DEFAULT_POLL_DELAY_SECONDS = 5;
    private string $apiKey;
    private string $baseUrl;
    private string $version;
    private Client $client;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.webcrawlerapi.com',
        string $version = 'v1'
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->version = $version;
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
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
        bool $allowSubdomains = false,
        ?string $whitelistRegexp = null,
        ?string $blacklistRegexp = null,
        bool $mainContentOnly = false
    ): CrawlResponse {
        $payload = [
            'url' => $url,
            'scrape_type' => $scrapeType,
            'items_limit' => $itemsLimit,
            'allow_subdomains' => $allowSubdomains,
            'main_content_only' => $mainContentOnly,
        ];

        if ($webhookUrl !== null) {
            $payload['webhook_url'] = $webhookUrl;
        }
        if ($whitelistRegexp !== null) {
            $payload['whitelist_regexp'] = $whitelistRegexp;
        }
        if ($blacklistRegexp !== null) {
            $payload['blacklist_regexp'] = $blacklistRegexp;
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
        bool $allowSubdomains = false,
        ?string $whitelistRegexp = null,
        ?string $blacklistRegexp = null,
        bool $mainContentOnly = false,
        int $maxPolls = 100
    ): Job {
        $response = $this->crawlAsync(
            $url,
            $scrapeType,
            $itemsLimit,
            $webhookUrl,
            $allowSubdomains,
            $whitelistRegexp,
            $blacklistRegexp,
            $mainContentOnly
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
} 