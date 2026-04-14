<?php

namespace WebCrawlerAPI\Models;

class ScrapeRequest
{
    public function __construct(
        public readonly string $url,
        /** @deprecated Use $outputFormats instead */
        public readonly ?string $outputFormat = null,
        public readonly ?string $webhookUrl = null,
        public readonly ?string $cleanSelectors = null,
        public readonly ?array $actions = null,
        public readonly ?string $prompt = null,
        public readonly ?array $responseSchema = null,
        public readonly ?bool $respectRobotsTxt = null,
        public readonly ?bool $mainContentOnly = null,
        public readonly ?int $maxAge = null,
        /** @var ('markdown'|'cleaned'|'html'|'links')[]|null */
        public readonly ?array $outputFormats = null,
    ) {
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
        if ($this->actions !== null) {
            $payload['actions'] = array_values($this->actions);
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
