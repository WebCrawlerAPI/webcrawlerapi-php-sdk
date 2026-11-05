<?php

namespace WebCrawlerAPI\Models;

class BatchScrapeErrors
{
    /**
     * @param BatchScrapeInvalidUrl[] $errors Failed URLs (url, errorCode, errorMessage)
     * @param string[] $robotsBlocked URLs blocked by robots.txt
     */
    public function __construct(
        public readonly array $errors = [],
        public readonly array $robotsBlocked = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            errors: array_map(
                static fn(array $e) => BatchScrapeInvalidUrl::fromArray($e),
                $data['errors'] ?? []
            ),
            robotsBlocked: array_values($data['robots_blocked'] ?? []),
        );
    }
}
