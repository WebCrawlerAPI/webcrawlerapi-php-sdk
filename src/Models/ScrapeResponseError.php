<?php

namespace WebCrawlerAPI\Models;

class ScrapeResponseError
{
    public function __construct(
        public readonly bool $success,
        public readonly string $errorCode,
        public readonly string $errorMessage,
        public readonly ?string $status = null
    ) {
    }
}
