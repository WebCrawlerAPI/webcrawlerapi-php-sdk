<?php

namespace WebCrawlerAPI\Models;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
    public int $cost;
    public string $referredUrl;
    public string $lastError;
    public ?string $rawContentUrl;
    public ?string $cleanedContentUrl;
    public ?string $markdownContentUrl;
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
        $this->lastError = $data['last_error'];
        $this->rawContentUrl = $data['raw_content_url'] ?? null;
        $this->cleanedContentUrl = $data['cleaned_content_url'] ?? null;
        $this->markdownContentUrl = $data['markdown_content_url'] ?? null;
        $this->job = $job;
    }

    private function validateData(array $data): void
    {
        $requiredFields = [
            'id', 'job_id', 'original_url', 'page_status_code',
            'status', 'title', 'created_at', 'updated_at',
            'cost', 'referred_url', 'last_error'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    /**
     * @throws GuzzleException
     */
    public function getContent(): ?string
    {
        if ($this->status !== 'done') {
            return null;
        }

        if ($this->content !== null) {
            return $this->content;
        }

        $contentUrl = match ($this->job->scrapeType) {
            'html' => $this->rawContentUrl,
            'cleaned' => $this->cleanedContentUrl,
            'markdown' => $this->markdownContentUrl,
            default => null,
        };

        if (!$contentUrl) {
            return null;
        }

        $client = new Client();
        $response = $client->get($contentUrl);
        $this->content = $response->getBody()->getContents();

        return $this->content;
    }
} 