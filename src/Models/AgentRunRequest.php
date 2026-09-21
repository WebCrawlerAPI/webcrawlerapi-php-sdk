<?php

namespace WebCrawlerAPI\Models;

class AgentRunRequest
{
    public function __construct(
        /** Natural-language task for the agent to complete. */
        public readonly string $prompt,
        /** Maximum spend for this run in USD. Required by the API. */
        public readonly float $maxSpendUsd,
        /** @var string[]|null Optional seed URLs the agent can use while running the task. */
        public readonly ?array $urls = null,
        /** If true, the agent only uses the provided seed URLs. */
        public readonly ?bool $seedUrlsOnly = null,
        /** Optional JSON schema for structured agent output. */
        public readonly ?array $outputSchema = null,
        /** Model to use for the run. Omit to use the API default. */
        public readonly ?string $model = null,
    ) {
    }

    public function toPayload(): array
    {
        $payload = [
            'prompt' => $this->prompt,
            'max_spend_usd' => $this->maxSpendUsd,
        ];

        if ($this->urls !== null) {
            $payload['urls'] = array_values($this->urls);
        }
        if ($this->seedUrlsOnly !== null) {
            $payload['seed_urls_only'] = $this->seedUrlsOnly;
        }
        if ($this->outputSchema !== null) {
            $payload['output_schema'] = $this->outputSchema;
        }
        if ($this->model !== null) {
            $payload['model'] = $this->model;
        }

        return $payload;
    }
}
