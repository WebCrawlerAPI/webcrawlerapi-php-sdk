<?php

namespace WebCrawlerAPI\Models;

class ScrapeResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $status = null,
        public readonly ?string $markdown = null,
        public readonly ?string $cleanedContent = null,
        public readonly ?string $rawContent = null,
        public readonly int $pageStatusCode = 0,
        public readonly ?string $pageTitle = null,
        public readonly mixed $structuredData = null,
        public readonly ?array $links = null
    ) {
    }
}
