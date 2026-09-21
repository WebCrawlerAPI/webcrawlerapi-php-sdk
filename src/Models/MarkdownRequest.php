<?php

namespace WebCrawlerAPI\Models;

class MarkdownRequest
{
    public function __construct(
        /** The URL of the webpage to extract the article markdown from. */
        public readonly string $url
    ) {
    }

    public function toPayload(): array
    {
        return ['url' => $this->url];
    }
}
