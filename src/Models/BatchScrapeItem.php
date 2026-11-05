<?php

namespace WebCrawlerAPI\Models;

class BatchScrapeItem
{
    public function __construct(
        public readonly string $url,
        public readonly string $status,
        public readonly int $pageStatusCode = 0,
        public readonly ?string $title = null,
        public readonly ?string $markdownUrl = null,
        public readonly ?string $cleanedUrl = null,
        public readonly ?string $htmlUrl = null,
        public readonly bool $fromCache = false,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            url: (string)($data['url'] ?? ''),
            status: (string)($data['status'] ?? ''),
            pageStatusCode: (int)($data['page_status_code'] ?? 0),
            title: $data['title'] ?? null,
            markdownUrl: $data['markdown_url'] ?? null,
            cleanedUrl: $data['cleaned_url'] ?? null,
            htmlUrl: $data['html_url'] ?? null,
            fromCache: (bool)($data['from_cache'] ?? false),
            errorCode: $data['error_code'] ?? null,
            errorMessage: $data['error_message'] ?? null,
        );
    }
}
