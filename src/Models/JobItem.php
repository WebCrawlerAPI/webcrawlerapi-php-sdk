<?php

namespace WebCrawlerAPI\Models;

use DateTime;
use InvalidArgumentException;

class JobItem
{
    public string $id;
    public string $jobId;
    public string $originalUrl;
    public int $pageStatusCode;
    public string $status;
    public string $title;
    public DateTime $createdAt;
    public DateTime $updatedAt;
    public float $cost;
    public string $referredUrl;
    public ?int $depth;
    public ?string $lastError;
    public ?string $errorCode;
    public ?string $rawContentUrl;
    public ?string $cleanedContentUrl;
    public ?string $markdownContentUrl;
    public ?string $link;
    private Job $job;
    private ?string $content = null;

    public function __construct(array $data, Job $job)
    {
        $this->validateData($data);
        
        $this->id = $data['id'];
        $this->jobId = $data['job_id'];
        $this->originalUrl = $data['original_url'];
        $this->pageStatusCode = $data['page_status_code'];
        $this->status = $data['status'];
        $this->title = $data['title'];
        $this->createdAt = new DateTime($data['created_at']);
        $this->updatedAt = new DateTime($data['updated_at']);
        $this->cost = $data['cost'];
        $this->referredUrl = $data['referred_url'];
        $this->depth = $data['depth'] ?? null;
        $this->lastError = $data['last_error'] ?? null;
        $this->errorCode = $data['error_code'] ?? null;
        $this->rawContentUrl = $data['raw_content_url'] ?? null;
        $this->cleanedContentUrl = $data['cleaned_content_url'] ?? null;
        $this->markdownContentUrl = $data['markdown_content_url'] ?? null;
        $this->link = $data['link'] ?? null;
        $this->job = $job;
    }

    private function validateData(array $data): void
    {
        $requiredFields = [
            'id', 'job_id', 'original_url', 'page_status_code',
            'status', 'title', 'created_at', 'updated_at',
            'cost', 'referred_url'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    private function resolveContentUrl(): ?string
    {
        // Prefer output_formats if present and non-empty, using priority: markdown > cleaned > html
        if (!empty($this->job->outputFormats)) {
            $priority = ['markdown', 'cleaned', 'html'];
            foreach ($priority as $fmt) {
                if (in_array($fmt, $this->job->outputFormats, true)) {
                    return match ($fmt) {
                        'markdown' => $this->markdownContentUrl,
                        'cleaned'  => $this->cleanedContentUrl,
                        'html'     => $this->rawContentUrl,
                        default    => null,
                    };
                }
            }
            return null;
        }

        // Fall back to scrape_type for backward compatibility
        return match ($this->job->scrapeType) {
            'html'     => $this->rawContentUrl,
            'cleaned'  => $this->cleanedContentUrl,
            'markdown' => $this->markdownContentUrl,
            default    => null,
        };
    }

    private function fetchUrl(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'header' => "Accept: */*\r\nAccept-Encoding: identity",
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ]);
        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new \RuntimeException("Failed to fetch content from URL: {$url}");
        }
        return $content;
    }

    /**
     * Returns the content based on the job's output format (respects output_formats priority,
     * falls back to scrape_type). Returns null if the job or item is not done.
     */
    public function getContent(): ?string
    {
        if ($this->job->status !== 'done' || $this->status !== 'done') {
            return null;
        }

        if ($this->content !== null) {
            return $this->content;
        }

        $contentUrl = $this->resolveContentUrl();

        if (!$contentUrl) {
            return null;
        }

        $this->content = $this->fetchUrl($contentUrl);
        return $this->content;
    }

    /**
     * @throws GuzzleException
     */
    public function getMarkdown(): ?string
    {
        if (!$this->markdownContentUrl) {
            return null;
        }
        return $this->fetchUrl($this->markdownContentUrl);
    }

    /**
     * @throws GuzzleException
     */
    public function getCleaned(): ?string
    {
        if (!$this->cleanedContentUrl) {
            return null;
        }
        return $this->fetchUrl($this->cleanedContentUrl);
    }

    /**
     * @throws GuzzleException
     */
    public function getHTML(): ?string
    {
        if (!$this->rawContentUrl) {
            return null;
        }
        return $this->fetchUrl($this->rawContentUrl);
    }
}