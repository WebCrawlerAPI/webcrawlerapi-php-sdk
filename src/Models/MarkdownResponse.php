<?php

namespace WebCrawlerAPI\Models;

class MarkdownResponse
{
    public function __construct(
        public readonly bool $success,
        /** Cleaned article markdown (main content only). */
        public readonly ?string $markdown = null
    ) {
    }
}
