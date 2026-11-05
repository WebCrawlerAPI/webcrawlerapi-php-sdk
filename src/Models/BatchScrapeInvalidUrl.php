<?php

namespace WebCrawlerAPI\Models;

class BatchScrapeInvalidUrl
{
    public function __construct(
        public readonly string $url,
        public readonly string $errorCode,
        public readonly string $errorMessage,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            url: (string)($data['url'] ?? ''),
            errorCode: (string)($data['error_code'] ?? ''),
            errorMessage: (string)($data['error_message'] ?? ''),
        );
    }
}
