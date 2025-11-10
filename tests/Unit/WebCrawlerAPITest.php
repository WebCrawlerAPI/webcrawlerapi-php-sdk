<?php

namespace WebCrawlerAPI\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use WebCrawlerAPI\Models\CrawlResponse;
use WebCrawlerAPI\Models\Job;
use WebCrawlerAPI\WebCrawlerAPI;

class WebCrawlerAPITest extends TestCase
{
    private WebCrawlerAPI $api;
    private MockHandler $mockHandler;
    private array $requestHistory = [];

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push(Middleware::history($this->requestHistory));

        $this->api = new WebCrawlerAPI('test-api-key');
        
        $reflection = new \ReflectionClass($this->api);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->api, new Client(['handler' => $handlerStack]));
    }

    protected function tearDown(): void
    {
        $this->requestHistory = [];
    }

    public function testConstructorSetsProperties(): void
    {
        $api = new WebCrawlerAPI('api-key', 'https://custom.api.com', 'v2');
        
        $reflection = new \ReflectionClass($api);
        
        $apiKeyProperty = $reflection->getProperty('apiKey');
        $apiKeyProperty->setAccessible(true);
        $this->assertEquals('api-key', $apiKeyProperty->getValue($api));
        
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setAccessible(true);
        $this->assertEquals('https://custom.api.com', $baseUrlProperty->getValue($api));
        
        $versionProperty = $reflection->getProperty('version');
        $versionProperty->setAccessible(true);
        $this->assertEquals('v2', $versionProperty->getValue($api));
    }

    public function testConstructorTrimsTrailingSlashFromBaseUrl(): void
    {
        $api = new WebCrawlerAPI('api-key', 'https://api.com/');
        
        $reflection = new \ReflectionClass($api);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setAccessible(true);
        
        $this->assertEquals('https://api.com', $baseUrlProperty->getValue($api));
    }

    public function testCrawlAsyncSuccess(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'job-123']))
        );

        $response = $this->api->crawlAsync(
            'https://example.com',
            'markdown',
            5,
            'https://webhook.com',
            true,
            '.*article.*',
            '.*ads.*'
        );

        $this->assertInstanceOf(CrawlResponse::class, $response);
        $this->assertEquals('job-123', $response->id);

        $this->assertCount(1, $this->requestHistory);
        $request = $this->requestHistory[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/v1/crawl', $request->getUri()->getPath());

        $body = json_decode($request->getBody()->getContents(), true);
        $expectedBody = [
            'url' => 'https://example.com',
            'scrape_type' => 'markdown',
            'items_limit' => 5,
            'allow_subdomains' => true,
            'webhook_url' => 'https://webhook.com',
            'whitelist_regexp' => '.*article.*',
            'blacklist_regexp' => '.*ads.*'
        ];
        $this->assertEquals($expectedBody, $body);
    }

    public function testCrawlAsyncWithMinimalParameters(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'job-456']))
        );

        $response = $this->api->crawlAsync('https://example.com');

        $this->assertEquals('job-456', $response->id);

        $request = $this->requestHistory[0]['request'];
        $body = json_decode($request->getBody()->getContents(), true);
        $expectedBody = [
            'url' => 'https://example.com',
            'scrape_type' => 'html',
            'items_limit' => 10,
            'allow_subdomains' => false
        ];
        $this->assertEquals($expectedBody, $body);
    }

    public function testCrawlAsyncThrowsOnMissingId(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['status' => 'ok']))
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid API response: missing id field');

        $this->api->crawlAsync('https://example.com');
    }

    public function testGetJobSuccess(): void
    {
        $jobData = [
            'id' => 'job-123',
            'org_id' => 'org-456',
            'url' => 'https://example.com',
            'status' => 'running',
            'scrape_type' => 'html',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com',
            'job_items' => []
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($jobData))
        );

        $job = $this->api->getJob('job-123');

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals('job-123', $job->id);
        $this->assertEquals('running', $job->status);

        $request = $this->requestHistory[0]['request'];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/v1/job/job-123', $request->getUri()->getPath());
    }

    public function testGetJobThrowsOnInvalidResponse(): void
    {
        $this->mockHandler->append(
            new Response(200, [], 'invalid json')
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid API response: expected array');

        $this->api->getJob('job-123');
    }

    public function testCancelJobSuccess(): void
    {
        $cancelData = ['status' => 'cancelled'];
        
        $this->mockHandler->append(
            new Response(200, [], json_encode($cancelData))
        );

        $result = $this->api->cancelJob('job-123');

        $this->assertEquals($cancelData, $result);

        $request = $this->requestHistory[0]['request'];
        $this->assertEquals('PUT', $request->getMethod());
        $this->assertEquals('/v1/job/job-123/cancel', $request->getUri()->getPath());
    }

    public function testCrawlSynchronousSuccess(): void
    {
        $jobData = [
            'id' => 'job-123',
            'org_id' => 'org-456',
            'url' => 'https://example.com',
            'status' => 'done',
            'scrape_type' => 'html',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com',
            'job_items' => []
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'job-123'])),
            new Response(200, [], json_encode($jobData))
        );

        $job = $this->api->crawl('https://example.com');

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals('done', $job->status);
        $this->assertTrue($job->isTerminal());
    }

    public function testCrawlWithPolling(): void
    {
        $runningJobData = [
            'id' => 'job-123',
            'org_id' => 'org-456',
            'url' => 'https://example.com',
            'status' => 'running',
            'scrape_type' => 'html',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com',
            'recommended_pull_delay_ms' => 1000,
            'job_items' => []
        ];

        $doneJobData = array_merge($runningJobData, ['status' => 'done']);

        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'job-123'])),
            new Response(200, [], json_encode($runningJobData)),
            new Response(200, [], json_encode($doneJobData))
        );

        $startTime = time();
        $job = $this->api->crawl('https://example.com');
        $endTime = time();

        $this->assertEquals('done', $job->status);
        $this->assertGreaterThanOrEqual(1, $endTime - $startTime);
    }

    public function testCrawlMaxPollsReached(): void
    {
        $runningJobData = [
            'id' => 'job-123',
            'org_id' => 'org-456',
            'url' => 'https://example.com',
            'status' => 'running',
            'scrape_type' => 'html',
            'items_limit' => 10,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-01T00:01:00Z',
            'webhook_url' => 'https://webhook.com',
            'recommended_pull_delay_ms' => 100,
            'job_items' => []
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'job-123'])),
            new Response(200, [], json_encode($runningJobData)),
            new Response(200, [], json_encode($runningJobData)),
            new Response(200, [], json_encode($runningJobData))
        );

        $job = $this->api->crawl('https://example.com', 'html', 10, null, false, null, null, false, null, 1);

        $this->assertEquals('running', $job->status);
        $this->assertFalse($job->isTerminal());
    }

    public function testHttpExceptionHandling(): void
    {
        $this->mockHandler->append(
            new RequestException('Network error', new Request('POST', '/crawl'))
        );

        $this->expectException(RequestException::class);
        $this->api->crawlAsync('https://example.com');
    }
}