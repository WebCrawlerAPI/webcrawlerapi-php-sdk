<?php

namespace WebCrawlerAPI\Tests\Unit\Models;

use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WebCrawlerAPI\Models\Job;
use WebCrawlerAPI\Models\JobItem;

class JobTest extends TestCase
{
    private array $validJobData;

    protected function setUp(): void
    {
        $this->validJobData = [
            'id' => 'job-123',
            'org_id' => 'org-456',
            'url' => 'https://example.com',
            'status' => 'running',
            'scrape_type' => 'html',
            'whitelist_regexp' => '.*article.*',
            'blacklist_regexp' => '.*ads.*',
            'allow_subdomains' => true,
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com',
            'recommended_pull_delay_ms' => 5000,
            'finished_at' => '2023-01-01T00:05:00Z',
            'webhook_status' => 'sent',
            'webhook_error' => null,
            'job_items' => [
                [
                    'id' => 'item-1',
                    'job_id' => 'job-123',
                    'original_url' => 'https://example.com/page1',
                    'page_status_code' => 200,
                    'status' => 'done',
                    'title' => 'Page 1',
                    'created_at' => '2023-01-01T00:00:00Z',
                    'updated_at' => '2023-01-01T00:01:00Z',
                    'cost' => 0.001,
                    'referred_url' => 'https://example.com',
                    'raw_content_url' => 'https://content.com/raw/1'
                ]
            ]
        ];
    }

    public function testJobConstructorWithValidData(): void
    {
        $job = new Job($this->validJobData);

        $this->assertEquals('job-123', $job->id);
        $this->assertEquals('org-456', $job->orgId);
        $this->assertEquals('https://example.com', $job->url);
        $this->assertEquals('running', $job->status);
        $this->assertEquals('html', $job->scrapeType);
        $this->assertEquals('.*article.*', $job->whitelistRegexp);
        $this->assertEquals('.*ads.*', $job->blacklistRegexp);
        $this->assertTrue($job->allowSubdomains);
        $this->assertEquals(10, $job->itemsLimit);
        $this->assertInstanceOf(DateTime::class, $job->createdAt);
        $this->assertInstanceOf(DateTime::class, $job->updatedAt);
        $this->assertEquals('https://webhook.com', $job->webhookUrl);
        $this->assertEquals(5000, $job->recommendedPullDelayMs);
        $this->assertInstanceOf(DateTime::class, $job->finishedAt);
        $this->assertEquals('sent', $job->webhookStatus);
        $this->assertNull($job->webhookError);
        $this->assertCount(1, $job->jobItems);
        $this->assertInstanceOf(JobItem::class, $job->jobItems[0]);
    }

    public function testJobConstructorWithMinimalData(): void
    {
        $minimalData = [
            'id' => 'job-123',
            'org_id' => 'org-456',
            'url' => 'https://example.com',
            'status' => 'running',
            'scrape_type' => 'html',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com'
        ];

        $job = new Job($minimalData);

        $this->assertEquals('job-123', $job->id);
        $this->assertNull($job->whitelistRegexp);
        $this->assertNull($job->blacklistRegexp);
        $this->assertNull($job->allowSubdomains);
        $this->assertNull($job->recommendedPullDelayMs);
        $this->assertNull($job->finishedAt);
        $this->assertNull($job->webhookStatus);
        $this->assertNull($job->webhookError);
        $this->assertEmpty($job->jobItems);
    }

    public function testJobConstructorThrowsOnMissingRequiredFields(): void
    {
        $requiredFields = [
            'id', 'org_id', 'url', 'status', 'scrape_type',
            'items_limit', 'created_at', 'updated_at', 'webhook_url'
        ];

        foreach ($requiredFields as $field) {
            $invalidData = $this->validJobData;
            unset($invalidData[$field]);

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Missing required field: {$field}");

            new Job($invalidData);
        }
    }

    public function testIsTerminalWithDoneStatus(): void
    {
        $data = array_merge($this->validJobData, ['status' => 'done']);
        $job = new Job($data);

        $this->assertTrue($job->isTerminal());
    }

    public function testIsTerminalWithErrorStatus(): void
    {
        $data = array_merge($this->validJobData, ['status' => 'error']);
        $job = new Job($data);

        $this->assertTrue($job->isTerminal());
    }

    public function testIsTerminalWithCancelledStatus(): void
    {
        $data = array_merge($this->validJobData, ['status' => 'cancelled']);
        $job = new Job($data);

        $this->assertTrue($job->isTerminal());
    }

    public function testIsTerminalWithRunningStatus(): void
    {
        $data = array_merge($this->validJobData, ['status' => 'running']);
        $job = new Job($data);

        $this->assertFalse($job->isTerminal());
    }

    public function testIsTerminalWithPendingStatus(): void
    {
        $data = array_merge($this->validJobData, ['status' => 'pending']);
        $job = new Job($data);

        $this->assertFalse($job->isTerminal());
    }

    public function testDateTimeParsingForCreatedAt(): void
    {
        $job = new Job($this->validJobData);

        $this->assertEquals('2023-01-01 00:00:00', $job->createdAt->format('Y-m-d H:i:s'));
    }

    public function testDateTimeParsingForUpdatedAt(): void
    {
        $job = new Job($this->validJobData);

        $this->assertEquals('2023-01-01 00:01:00', $job->updatedAt->format('Y-m-d H:i:s'));
    }

    public function testFinishedAtWithNullValue(): void
    {
        $data = array_merge($this->validJobData, ['finished_at' => null]);
        $job = new Job($data);

        $this->assertNull($job->finishedAt);
    }

    public function testFinishedAtWithEmptyString(): void
    {
        $data = array_merge($this->validJobData, ['finished_at' => '']);
        $job = new Job($data);

        $this->assertNull($job->finishedAt);
    }

    public function testJobItemsCreation(): void
    {
        $job = new Job($this->validJobData);

        $this->assertCount(1, $job->jobItems);
        $jobItem = $job->jobItems[0];
        $this->assertEquals('item-1', $jobItem->id);
        $this->assertEquals('job-123', $jobItem->jobId);
        $this->assertEquals('Page 1', $jobItem->title);
    }

    public function testJobItemsWithEmptyArray(): void
    {
        $data = array_merge($this->validJobData, ['job_items' => []]);
        $job = new Job($data);

        $this->assertEmpty($job->jobItems);
    }

    public function testJobItemsWithMissingField(): void
    {
        $data = $this->validJobData;
        unset($data['job_items']);
        $job = new Job($data);

        $this->assertEmpty($job->jobItems);
    }
}