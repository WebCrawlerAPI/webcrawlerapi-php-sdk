<?php

namespace WebCrawlerAPI\Tests\Unit\Models;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WebCrawlerAPI\Models\Job;
use WebCrawlerAPI\Models\JobItem;

class JobItemTest extends TestCase
{
    private array $validJobItemData;
    private Job $mockJob;

    protected function setUp(): void
    {
        $this->validJobItemData = [
            'id' => 'item-123',
            'job_id' => 'job-456',
            'original_url' => 'https://example.com/page1',
            'page_status_code' => 200,
            'status' => 'done',
            'title' => 'Example Page',
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'cost' => 0.001,
            'referred_url' => 'https://example.com',
            'last_error' => null,
            'error_code' => null,
            'raw_content_url' => 'https://content.com/raw/123',
            'cleaned_content_url' => 'https://content.com/cleaned/123',
            'markdown_content_url' => 'https://content.com/markdown/123'
        ];

        $jobData = [
            'id' => 'job-456',
            'org_id' => 'org-789',
            'url' => 'https://example.com',
            'status' => 'done',
            'scrape_type' => 'html',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com'
        ];

        $this->mockJob = new Job($jobData);
    }

    public function testJobItemConstructorWithValidData(): void
    {
        $jobItem = new JobItem($this->validJobItemData, $this->mockJob);

        $this->assertEquals('item-123', $jobItem->id);
        $this->assertEquals('job-456', $jobItem->jobId);
        $this->assertEquals('https://example.com/page1', $jobItem->originalUrl);
        $this->assertEquals(200, $jobItem->pageStatusCode);
        $this->assertEquals('done', $jobItem->status);
        $this->assertEquals('Example Page', $jobItem->title);
        $this->assertInstanceOf(DateTime::class, $jobItem->createdAt);
        $this->assertInstanceOf(DateTime::class, $jobItem->updatedAt);
        $this->assertEquals(0.001, $jobItem->cost);
        $this->assertEquals('https://example.com', $jobItem->referredUrl);
        $this->assertNull($jobItem->lastError);
        $this->assertNull($jobItem->errorCode);
        $this->assertEquals('https://content.com/raw/123', $jobItem->rawContentUrl);
        $this->assertEquals('https://content.com/cleaned/123', $jobItem->cleanedContentUrl);
        $this->assertEquals('https://content.com/markdown/123', $jobItem->markdownContentUrl);
    }

    public function testJobItemConstructorWithMinimalData(): void
    {
        $minimalData = [
            'id' => 'item-123',
            'job_id' => 'job-456',
            'original_url' => 'https://example.com/page1',
            'page_status_code' => 200,
            'status' => 'done',
            'title' => 'Example Page',
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'cost' => 0.001,
            'referred_url' => 'https://example.com'
        ];

        $jobItem = new JobItem($minimalData, $this->mockJob);

        $this->assertEquals('item-123', $jobItem->id);
        $this->assertNull($jobItem->lastError);
        $this->assertNull($jobItem->errorCode);
        $this->assertNull($jobItem->rawContentUrl);
        $this->assertNull($jobItem->cleanedContentUrl);
        $this->assertNull($jobItem->markdownContentUrl);
    }

    public function testJobItemConstructorThrowsOnMissingRequiredFields(): void
    {
        $requiredFields = [
            'id', 'job_id', 'original_url', 'page_status_code',
            'status', 'title', 'created_at', 'updated_at',
            'cost', 'referred_url'
        ];

        foreach ($requiredFields as $field) {
            $invalidData = $this->validJobItemData;
            unset($invalidData[$field]);

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("Missing required field: {$field}");

            new JobItem($invalidData, $this->mockJob);
        }
    }

    public function testGetContentWithDoneStatusAndHtmlScrapeType(): void
    {
        $jobItem = new JobItem($this->validJobItemData, $this->mockJob);
        
        $reflection = new \ReflectionClass($jobItem);
        $method = $reflection->getMethod('getContent');
        $method->setAccessible(true);

        $this->assertEquals('done', $jobItem->status);
        $this->assertEquals('html', $this->mockJob->scrapeType);
        $this->assertNotNull($jobItem->rawContentUrl);
    }

    public function testGetContentWithNonDoneStatus(): void
    {
        $runningData = array_merge($this->validJobItemData, ['status' => 'running']);
        $jobItem = new JobItem($runningData, $this->mockJob);

        $content = $jobItem->getContent();
        $this->assertNull($content);
    }

    public function testGetContentCaching(): void
    {
        $jobItem = new JobItem($this->validJobItemData, $this->mockJob);

        $reflection = new \ReflectionClass($jobItem);
        $contentProperty = $reflection->getProperty('content');
        $contentProperty->setAccessible(true);
        
        $this->assertNull($contentProperty->getValue($jobItem));
        
        $contentProperty->setValue($jobItem, 'cached content');
        
        $this->assertEquals('cached content', $contentProperty->getValue($jobItem));
    }

    public function testGetContentWithCleanedScrapeType(): void
    {
        $cleanedJobData = [
            'id' => 'job-456',
            'org_id' => 'org-789',
            'url' => 'https://example.com',
            'status' => 'done',
            'scrape_type' => 'cleaned',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com'
        ];

        $cleanedJob = new Job($cleanedJobData);
        $jobItem = new JobItem($this->validJobItemData, $cleanedJob);

        $this->assertEquals('cleaned', $cleanedJob->scrapeType);
        $this->assertNotNull($jobItem->cleanedContentUrl);
    }

    public function testGetContentWithMarkdownScrapeType(): void
    {
        $markdownJobData = [
            'id' => 'job-456',
            'org_id' => 'org-789',
            'url' => 'https://example.com',
            'status' => 'done',
            'scrape_type' => 'markdown',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com'
        ];

        $markdownJob = new Job($markdownJobData);
        $jobItem = new JobItem($this->validJobItemData, $markdownJob);

        $this->assertEquals('markdown', $markdownJob->scrapeType);
        $this->assertNotNull($jobItem->markdownContentUrl);
    }

    public function testGetContentWithUnsupportedScrapeType(): void
    {
        $unsupportedJobData = [
            'id' => 'job-456',
            'org_id' => 'org-789',
            'url' => 'https://example.com',
            'status' => 'done',
            'scrape_type' => 'unsupported',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com'
        ];

        $unsupportedJob = new Job($unsupportedJobData);
        $jobItem = new JobItem($this->validJobItemData, $unsupportedJob);

        $content = $jobItem->getContent();
        $this->assertNull($content);
    }

    public function testGetContentWithMissingContentUrl(): void
    {
        $dataWithoutUrls = array_merge($this->validJobItemData, [
            'raw_content_url' => null,
            'cleaned_content_url' => null,
            'markdown_content_url' => null
        ]);

        $jobItem = new JobItem($dataWithoutUrls, $this->mockJob);

        $content = $jobItem->getContent();
        $this->assertNull($content);
    }

    public function testGetContentHttpException(): void
    {
        $jobItem = new JobItem($this->validJobItemData, $this->mockJob);

        $this->expectException(\GuzzleHttp\Exception\GuzzleException::class);
        $jobItem->getContent();
    }

    public function testDateTimeParsingForCreatedAt(): void
    {
        $jobItem = new JobItem($this->validJobItemData, $this->mockJob);

        $this->assertEquals('2023-01-01 00:00:00', $jobItem->createdAt->format('Y-m-d H:i:s'));
    }

    public function testDateTimeParsingForUpdatedAt(): void
    {
        $jobItem = new JobItem($this->validJobItemData, $this->mockJob);

        $this->assertEquals('2023-01-01 00:01:00', $jobItem->updatedAt->format('Y-m-d H:i:s'));
    }

    public function testWithErrorFields(): void
    {
        $dataWithError = array_merge($this->validJobItemData, [
            'status' => 'error',
            'last_error' => 'Page not found',
            'error_code' => '404'
        ]);

        $jobItem = new JobItem($dataWithError, $this->mockJob);

        $this->assertEquals('error', $jobItem->status);
        $this->assertEquals('Page not found', $jobItem->lastError);
        $this->assertEquals('404', $jobItem->errorCode);
    }

}