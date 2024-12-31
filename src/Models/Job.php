<?php

namespace WebCrawlerAPI\Models;

use DateTime;
use InvalidArgumentException;

class Job
{
    private const TERMINAL_STATUSES = ['done', 'error', 'cancelled'];

    public string $id;
    public string $orgId;
    public string $url;
    public string $status;
    public string $scrapeType;
    public string $whitelistRegexp;
    public string $blacklistRegexp;
    public bool $allowSubdomains;
    public int $itemsLimit;
    public DateTime $createdAt;
    public DateTime $updatedAt;
    public string $webhookUrl;
    public ?int $recommendedPullDelayMs;
    public ?DateTime $finishedAt;
    public ?string $webhookStatus;
    public ?string $webhookError;
    /** @var JobItem[] */
    public array $jobItems;

    public function __construct(array $data)
    {
        $this->validateData($data);
        
        $this->id = $data['id'];
        $this->orgId = $data['org_id'];
        $this->url = $data['url'];
        $this->status = $data['status'];
        $this->scrapeType = $data['scrape_type'];
        $this->whitelistRegexp = $data['whitelist_regexp'];
        $this->blacklistRegexp = $data['blacklist_regexp'];
        $this->allowSubdomains = $data['allow_subdomains'];
        $this->itemsLimit = $data['items_limit'];
        $this->createdAt = new DateTime($data['created_at']);
        $this->updatedAt = new DateTime($data['updated_at']);
        $this->webhookUrl = $data['webhook_url'];
        $this->recommendedPullDelayMs = $data['recommended_pull_delay_ms'] ?? null;
        
        $this->finishedAt = isset($data['finished_at']) && $data['finished_at'] 
            ? new DateTime($data['finished_at']) 
            : null;
            
        $this->webhookStatus = $data['webhook_status'] ?? null;
        $this->webhookError = $data['webhook_error'] ?? null;
        
        $this->jobItems = array_map(
            fn($item) => new JobItem($item, $this),
            $data['job_items'] ?? []
        );
    }

    private function validateData(array $data): void
    {
        $requiredFields = [
            'id', 'org_id', 'url', 'status', 'scrape_type',
            'whitelist_regexp', 'blacklist_regexp', 'allow_subdomains',
            'items_limit', 'created_at', 'updated_at', 'webhook_url'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
} 