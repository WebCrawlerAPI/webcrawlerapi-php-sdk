<?php

namespace WebCrawlerAPI\Models;

class BatchScrapeId
{
    /**
     * @param BatchScrapeInvalidUrl[] $invalidUrls URLs rejected during validation (invalid, duplicate, blocked)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $statusUrl = '',
        public readonly array $invalidUrls = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)$data['id'],
            statusUrl: (string)($data['status_url'] ?? ''),
            invalidUrls: array_map(
                static fn(array $u) => BatchScrapeInvalidUrl::fromArray($u),
                $data['invalid_urls'] ?? []
            ),
        );
    }
}
