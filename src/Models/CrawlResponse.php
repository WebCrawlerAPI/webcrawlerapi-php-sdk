<?php

namespace WebCrawlerAPI\Models;

class CrawlResponse
{
    public function __construct(
        public readonly string $id
    ) {}
} 