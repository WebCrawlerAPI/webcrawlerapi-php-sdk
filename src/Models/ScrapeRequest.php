<?php

namespace WebCrawlerAPI\Models;

class ScrapeRequest
{
    public function __construct(
        /** @var string|string[] A single URL, or a list of URLs to scrape as a batch */
        public readonly string|array $url,
        /** @deprecated Use $outputFormats instead */
        public readonly ?string $outputFormat = null,
        public readonly ?string $webhookUrl = null,
        public readonly ?string $cleanSelectors = null,
        public readonly ?string $prompt = null,
        public readonly ?array $responseSchema = null,
        public readonly ?bool $respectRobotsTxt = null,
        public readonly ?bool $mainContentOnly = null,
        public readonly ?int $maxAge = null,
        /** @var ('markdown'|'cleaned'|'html'|'links')[]|null */
        public readonly ?array $outputFormats = null,
        /** Batch only: max URLs scraped in parallel (0 or null: no cap) */
        public readonly ?int $maxConcurrency = null,
        /** Batch only: skip invalid URLs and report them in invalidUrls (API default: true) */
        public readonly ?bool $ignoreInvalidUrls = null,
    ) {
    }

    public function isBatch(): bool
    {
        return is_array($this->url);
    }

    /**
     * Payload for POST /v2/batch/scrape. prompt and responseSchema are not supported in batches.
     */
    public function toBatchPayload(): array
    {
        $payload = ['urls' => array_values((array)$this->url)];

        if ($this->outputFormats !== null) {
            $payload['output_formats'] = $this->outputFormats;
        } elseif ($this->outputFormat !== null) {
            $payload['output_formats'] = [$this->outputFormat];
        }
        if ($this->webhookUrl !== null) {
            $payload['webhook_url'] = $this->webhookUrl;
        }
        if ($this->cleanSelectors !== null) {
            $payload['clean_selectors'] = $this->cleanSelectors;
        }
        if ($this->respectRobotsTxt !== null) {
            $payload['respect_robots_txt'] = $this->respectRobotsTxt;
        }
        if ($this->mainContentOnly !== null) {
            $payload['main_content_only'] = $this->mainContentOnly;
        }
        if ($this->maxAge !== null) {
            $payload['max_age'] = $this->maxAge;
        }
        if ($this->maxConcurrency !== null) {
            $payload['max_concurrency'] = $this->maxConcurrency;
        }
        if ($this->ignoreInvalidUrls !== null) {
            $payload['ignore_invalid_urls'] = $this->ignoreInvalidUrls;
        }

        return $payload;
    }

    public function toPayload(): array
    {
        $payload = [
            'url' => $this->url,
        ];

        if ($this->outputFormats !== null) {
            $payload['output_formats'] = $this->outputFormats;
        } elseif ($this->outputFormat !== null) {
            $payload['output_format'] = $this->outputFormat;
        }
        if ($this->webhookUrl !== null) {
            $payload['webhook_url'] = $this->webhookUrl;
        }
        if ($this->cleanSelectors !== null) {
            $payload['clean_selectors'] = $this->cleanSelectors;
        }
        if ($this->prompt !== null) {
            $payload['prompt'] = $this->prompt;
        }
        if ($this->responseSchema !== null) {
            $payload['response_schema'] = $this->responseSchema;
        }
        if ($this->respectRobotsTxt !== null) {
            $payload['respect_robots_txt'] = $this->respectRobotsTxt;
        }
        if ($this->mainContentOnly !== null) {
            $payload['main_content_only'] = $this->mainContentOnly;
        }
        if ($this->maxAge !== null) {
            $payload['max_age'] = $this->maxAge;
        }

        return $payload;
    }
}
