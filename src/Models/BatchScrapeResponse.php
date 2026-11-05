<?php

namespace WebCrawlerAPI\Models;

class BatchScrapeResponse
{
    /**
     * @param string $status scraping, completed, failed or cancelled
     * @param BatchScrapeItem[] $data One page of items
     * @param string $next Cursor for the next page, empty on the last page
     */
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly int $total = 0,
        public readonly int $completed = 0,
        public readonly int $failed = 0,
        public readonly int $canceled = 0,
        public readonly array $data = [],
        public readonly string $next = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            status: (string)($data['status'] ?? ''),
            total: (int)($data['total'] ?? 0),
            completed: (int)($data['completed'] ?? 0),
            failed: (int)($data['failed'] ?? 0),
            canceled: (int)($data['canceled'] ?? 0),
            data: array_map(
                static fn(array $i) => BatchScrapeItem::fromArray($i),
                $data['data'] ?? []
            ),
            next: (string)($data['next'] ?? ''),
        );
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }
}
