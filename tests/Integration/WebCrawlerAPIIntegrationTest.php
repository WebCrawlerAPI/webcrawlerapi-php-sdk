<?php

namespace WebCrawlerAPI\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WebCrawlerAPI\Models\CrawlResponse;
use WebCrawlerAPI\Models\Job;
use WebCrawlerAPI\WebCrawlerAPI;

class WebCrawlerAPIIntegrationTest extends TestCase
{
    private WebCrawlerAPI $api;
    private string $testApiKey;

    protected function setUp(): void
    {
        $this->testApiKey = getenv('WEBCRAWLER_API_KEY') ?: 'test-key';
        $this->api = new WebCrawlerAPI($this->testApiKey);
    }

    public function testCrawlAsyncIntegration(): void
    {
        if ($this->testApiKey === 'test-key') {
            $this->markTestSkipped('Integration tests require a valid API key set in WEBCRAWLER_API_KEY environment variable');
        }

        $response = $this->api->crawlAsync(
            'https://httpbin.org/html',
            'html',
            1
        );

        $this->assertInstanceOf(CrawlResponse::class, $response);
        $this->assertNotEmpty($response->id);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $response->id);
    }

    public function testGetJobIntegration(): void
    {
        if ($this->testApiKey === 'test-key') {
            $this->markTestSkipped('Integration tests require a valid API key set in WEBCRAWLER_API_KEY environment variable');
        }

        $crawlResponse = $this->api->crawlAsync('https://httpbin.org/html', 'html', 1);
        $job = $this->api->getJob($crawlResponse->id);

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($crawlResponse->id, $job->id);
        $this->assertContains($job->status, ['pending', 'running', 'done', 'error', 'cancelled', 'new', 'in_progress']);
        $this->assertEquals('https://httpbin.org/html', $job->url);
        $this->assertEquals('html', $job->scrapeType);
        $this->assertEquals(1, $job->itemsLimit);
    }

    public function testSynchronousCrawlIntegration(): void
    {
        if ($this->testApiKey === 'test-key') {
            $this->markTestSkipped('Integration tests require a valid API key set in WEBCRAWLER_API_KEY environment variable');
        }

        $job = $this->api->crawl(
            'https://httpbin.org/html',
            'html',
            1,
            null,
            false,
            null,
            null,
            false,
            null,
            20
        );

        $this->assertInstanceOf(Job::class, $job);
        $this->assertTrue($job->isTerminal());
        $this->assertContains($job->status, ['done', 'error', 'cancelled']);
        
        if ($job->status === 'done' && !empty($job->jobItems)) {
            $jobItem = $job->jobItems[0];
            $this->assertEquals('done', $jobItem->status);
            $this->assertEquals(200, $jobItem->pageStatusCode);
            $this->assertIsString($jobItem->title); // Title can be empty string
        } else {
            $this->assertTrue(in_array($job->status, ['done', 'error', 'cancelled']));
        }
    }

    public function testCancelJobIntegration(): void
    {
        if ($this->testApiKey === 'test-key') {
            $this->markTestSkipped('Integration tests require a valid API key set in WEBCRAWLER_API_KEY environment variable');
        }

        $crawlResponse = $this->api->crawlAsync('https://httpbin.org/delay/10', 'html', 1);
        
        sleep(1);
        
        $result = $this->api->cancelJob($crawlResponse->id);
        
        $this->assertIsArray($result);
    }

    public function testJobItemContentIntegration(): void
    {
        if ($this->testApiKey === 'test-key') {
            $this->markTestSkipped('Integration tests require a valid API key set in WEBCRAWLER_API_KEY environment variable');
        }

        $job = $this->api->crawl(
            'https://httpbin.org/html',
            'html',
            1,
            null,
            false,
            null,
            null,
            false,
            null,
            30
        );

        $this->assertInstanceOf(Job::class, $job);

        if ($job->status === 'done' && !empty($job->jobItems)) {
            $jobItem = $job->jobItems[0];

            if ($jobItem->status === 'done') {
                $content = $jobItem->getContent();

                if ($content !== null) {
                    $this->assertIsString($content);
                    $this->assertNotEmpty($content);
                    $this->assertStringContainsString('<html', $content);
                } else {
                    $this->assertNull($content);
                }
            } else {
                $this->assertNotEquals('done', $jobItem->status);
            }
        } else {
            $this->assertNotEquals('done', $job->status);
        }
    }

    public function testApiErrorHandling(): void
    {
        $invalidApi = new WebCrawlerAPI('invalid-key');

        $this->expectException(\GuzzleHttp\Exception\ClientException::class);
        $invalidApi->crawlAsync('https://example.com');
    }

    public function testInvalidJobIdHandling(): void
    {
        if ($this->testApiKey === 'test-key') {
            $this->markTestSkipped('Integration tests require a valid API key set in WEBCRAWLER_API_KEY environment variable');
        }

        $this->expectException(\GuzzleHttp\Exception\ClientException::class);
        $this->api->getJob('non-existent-job-id');
    }

    public function testCrawlWithCustomParameters(): void
    {
        if ($this->testApiKey === 'test-key') {
            $this->markTestSkipped('Integration tests require a valid API key set in WEBCRAWLER_API_KEY environment variable');
        }

        $job = $this->api->crawl(
            'https://httpbin.org/',
            'markdown',
            2,
            null,
            false,
            '.*html.*',
            '.*status.*',
            false,
            null,
            20
        );

        $this->assertInstanceOf(Job::class, $job);
        $this->assertStringStartsWith('https://httpbin.org', $job->url);
        $this->assertEquals('markdown', $job->scrapeType);
        $this->assertEquals(2, $job->itemsLimit);
        $this->assertEquals('.*html.*', $job->whitelistRegexp);
        $this->assertEquals('.*status.*', $job->blacklistRegexp);
        $this->assertIsBool($job->allowSubdomains ?? false);
    }
}