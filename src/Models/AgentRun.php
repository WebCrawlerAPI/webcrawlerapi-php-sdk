<?php

namespace WebCrawlerAPI\Models;

use DateTime;
use InvalidArgumentException;

class AgentRun
{
    public const TERMINAL_STATUSES = ['done', 'error', 'canceled'];

    public string $id;
    public string $status;
    public string $prompt;
    public string $model;
    public mixed $data;
    public ?string $errorReason;
    public float $balanceUsedUsd;
    public ?float $maxSpendUsd;
    /** @var string[]|null */
    public ?array $urls;
    public ?DateTime $createdAt;
    public ?DateTime $updatedAt;
    public bool $success;

    public function __construct(array $data)
    {
        foreach (['id', 'status'] as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $this->id = $data['id'];
        $this->status = $data['status'];
        $this->prompt = $data['prompt'] ?? '';
        $this->model = $data['model'] ?? '';
        $this->data = $data['data'] ?? null;
        $this->errorReason = $data['error_reason'] ?? null;
        $this->balanceUsedUsd = (float)($data['balance_used_usd'] ?? 0);
        $this->maxSpendUsd = isset($data['max_spend_usd']) ? (float)$data['max_spend_usd'] : null;
        $this->urls = $data['urls'] ?? null;
        $this->createdAt = !empty($data['created_at']) ? new DateTime($data['created_at']) : null;
        $this->updatedAt = !empty($data['updated_at']) ? new DateTime($data['updated_at']) : null;
        $this->success = (bool)($data['success'] ?? false);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
