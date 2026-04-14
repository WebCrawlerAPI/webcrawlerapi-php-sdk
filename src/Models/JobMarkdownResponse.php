<?php

namespace WebCrawlerAPI\Models;

class JobMarkdownResponse
{
    public string $contentUrl;

    public function __construct(string $contentUrl)
    {
        $this->contentUrl = $contentUrl;
    }
}
