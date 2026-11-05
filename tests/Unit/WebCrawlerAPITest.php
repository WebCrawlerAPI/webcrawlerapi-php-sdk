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
use WebCrawlerAPI\Models\BatchScrapeResponse;
use WebCrawlerAPI\Models\CrawlResponse;
use WebCrawlerAPI\Exceptions\WebcrawlerApiException;
use WebCrawlerAPI\Models\Job;
use WebCrawlerAPI\Models\MarkdownRequest;
use WebCrawlerAPI\Models\ScrapeRequest;
use WebCrawlerAPI\Models\ScrapeResponse;
use WebCrawlerAPI\Models\ScrapeResponseError;
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

        $scrapeClientProperty = $reflection->getProperty('scrapeClient');
        $scrapeClientProperty->setAccessible(true);
        $scrapeClientProperty->setValue($this->api, new Client(['handler' => $handlerStack]));
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
            '.*article.*',
            '.*ads.*',
            true
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
            'output_formats' => ['markdown'],
            'items_limit' => 5,
            'webhook_url' => 'https://webhook.com',
            'whitelist_regexp' => '.*article.*',
            'blacklist_regexp' => '.*ads.*',
            'main_content_only' => true
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
            'output_formats' => ['markdown'],
            'items_limit' => 10
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
            new Response(200, [], json_encode($runningJobData))
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Crawling took too long');

        $this->api->crawl('https://example.com', 'html', 10, null, null, null, false, null, null, 1);
    }

    public function testHttpExceptionHandling(): void
    {
        $this->mockHandler->append(
            new RequestException('Network error', new Request('POST', '/crawl'))
        );

        $this->expectException(\WebCrawlerAPI\Exceptions\WebcrawlerApiException::class);
        $this->api->crawlAsync('https://example.com');
    }

    public function testMarkdownSuccess(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['success' => true, 'markdown' => '# Title']))
        );

        $result = $this->api->markdown(new MarkdownRequest('https://example.com/article'));

        $this->assertTrue($result->success);
        $this->assertSame('# Title', $result->markdown);

        $request = $this->requestHistory[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/markdown', $request->getUri()->getPath());
        $this->assertEquals(
            ['url' => 'https://example.com/article'],
            json_decode((string)$request->getBody(), true)
        );
    }

    public function testMarkdownWithoutMarkdownField(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['success' => false])));

        $result = $this->api->markdown(new MarkdownRequest('https://example.com'));

        $this->assertFalse($result->success);
        $this->assertNull($result->markdown);
    }

    public function testMarkdownMapsApiError(): void
    {
        $this->mockHandler->append(
            new Response(402, [], json_encode([
                'success' => false,
                'error_code' => 'insufficient_balance',
                'error_message' => 'Insufficient account balance',
            ]))
        );

        try {
            $this->api->markdown(new MarkdownRequest('https://example.com'));
            $this->fail('Expected WebcrawlerApiException');
        } catch (WebcrawlerApiException $e) {
            $this->assertSame('insufficient_balance', $e->getErrorCode());
            $this->assertSame('Insufficient account balance', $e->getErrorMessage());
            $this->assertSame(402, $e->getStatusCode());
        }
    }

    public function testMarkdownUnparseableErrorBody(): void
    {
        $this->mockHandler->append(new Response(500, [], 'oops'));

        try {
            $this->api->markdown(new MarkdownRequest('https://example.com'));
            $this->fail('Expected WebcrawlerApiException');
        } catch (WebcrawlerApiException $e) {
            $this->assertSame('unknown_error', $e->getErrorCode());
            $this->assertSame(500, $e->getStatusCode());
        }
    }

    public function testMarkdownThrowsOnInvalidResponse(): void
    {
        $this->mockHandler->append(new Response(200, [], 'not json'));

        try {
            $this->api->markdown(new MarkdownRequest('https://example.com'));
            $this->fail('Expected WebcrawlerApiException');
        } catch (WebcrawlerApiException $e) {
            $this->assertSame('invalid_response', $e->getErrorCode());
        }
    }

    public function testScrapeStartsAsyncAndPollsUntilDone(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'scrape-1'])),
            new Response(200, [], json_encode([
                'status' => 'in_progress',
                'recommended_pull_delay_ms' => 1,
            ])),
            new Response(200, [], json_encode([
                'status' => 'done',
                'success' => true,
                'markdown' => '# Hello',
                'page_title' => 'Hello',
            ]))
        );

        $result = $this->api->scrape(new ScrapeRequest(url: 'https://example.com'));

        $this->assertInstanceOf(ScrapeResponse::class, $result);
        $this->assertSame('done', $result->status);
        $this->assertSame('# Hello', $result->markdown);
        $this->assertCount(3, $this->requestHistory);

        $start = $this->requestHistory[0]['request'];
        $this->assertSame('POST', $start->getMethod());
        $this->assertSame('/v2/scrape', $start->getUri()->getPath());
        $this->assertSame('async=true', $start->getUri()->getQuery());

        $poll = $this->requestHistory[1]['request'];
        $this->assertSame('GET', $poll->getMethod());
        $this->assertSame('/v2/scrape/scrape-1', $poll->getUri()->getPath());
    }

    public function testScrapeReturnsErrorResponseFromPolling(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'scrape-2'])),
            new Response(200, [], json_encode([
                'status' => 'error',
                'error_code' => 'webpage_non_success',
                'error_message' => 'Page returned 404',
            ]))
        );

        $result = $this->api->scrape(new ScrapeRequest(url: 'https://example.com/missing'));

        $this->assertInstanceOf(ScrapeResponseError::class, $result);
        $this->assertSame('webpage_non_success', $result->errorCode);
        $this->assertSame('Page returned 404', $result->errorMessage);
    }

    public function testScrapeStopsAtMaxPolls(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'scrape-3'])),
            new Response(200, [], json_encode(['status' => 'in_progress', 'recommended_pull_delay_ms' => 1])),
            new Response(200, [], json_encode(['status' => 'in_progress', 'recommended_pull_delay_ms' => 1]))
        );

        $result = $this->api->scrape(new ScrapeRequest(url: 'https://example.com'), maxPolls: 2);

        $this->assertInstanceOf(ScrapeResponse::class, $result);
        $this->assertSame('in_progress', $result->status);
        $this->assertCount(3, $this->requestHistory);
    }

    public function testScrapeWithUrlListCreatesBatchAndReturnsFirstPage(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'b-1', 'status_url' => 'https://x/v2/batch/scrape/b-1', 'invalid_urls' => []])),
            new Response(200, [], json_encode([
                'id' => 'b-1', 'status' => 'completed', 'total' => 2, 'completed' => 1, 'failed' => 1, 'canceled' => 0,
                'data' => [
                    ['url' => 'https://a.com/', 'status' => 'done', 'page_status_code' => 200, 'markdown_url' => 'https://d/a.md'],
                    ['url' => 'https://b.com/', 'status' => 'error', 'error_code' => 'webpage_non_success', 'error_message' => 'bad'],
                ],
                'next' => 'cur',
            ]))
        );

        $result = $this->api->scrape(new ScrapeRequest(
            url: ['https://a.com/', 'https://b.com/'],
            outputFormats: ['markdown'],
            maxConcurrency: 5,
            ignoreInvalidUrls: false,
        ));

        $this->assertInstanceOf(BatchScrapeResponse::class, $result);
        $this->assertSame('completed', $result->status);
        $this->assertSame('cur', $result->next);
        $this->assertCount(2, $result->data);
        $this->assertSame('https://d/a.md', $result->data[0]->markdownUrl);
        $this->assertSame('webpage_non_success', $result->data[1]->errorCode);

        $post = $this->requestHistory[0]['request'];
        $this->assertSame('POST', $post->getMethod());
        $this->assertSame('/v2/batch/scrape', $post->getUri()->getPath());
        $this->assertSame(
            ['urls' => ['https://a.com/', 'https://b.com/'], 'output_formats' => ['markdown'], 'max_concurrency' => 5, 'ignore_invalid_urls' => false],
            json_decode((string)$post->getBody(), true)
        );
    }

    public function testScrapeAcceptsPlainArrayOfUrls(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'b-2'])),
            new Response(200, [], json_encode(['id' => 'b-2', 'status' => 'failed', 'data' => []]))
        );

        $result = $this->api->scrape(['https://a.com/']);

        $this->assertInstanceOf(BatchScrapeResponse::class, $result);
        $this->assertSame('failed', $result->status);
        $body = json_decode((string)$this->requestHistory[0]['request']->getBody(), true);
        $this->assertSame(['urls' => ['https://a.com/']], $body);
    }

    public function testScrapeBatchStopsAtMaxPolls(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['id' => 'b-3'])),
            new Response(200, [], json_encode(['id' => 'b-3', 'status' => 'scraping', 'total' => 2, 'data' => []]))
        );

        $result = $this->api->scrape(['https://a.com/', 'https://b.com/'], maxPolls: 1);

        $this->assertSame('scraping', $result->status);
        $this->assertFalse($result->isTerminal());
        $this->assertCount(2, $this->requestHistory);
    }

    public function testScrapeBatchAsyncReturnsIdAndInvalidUrls(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'b-4',
            'status_url' => 'https://x/v2/batch/scrape/b-4',
            'invalid_urls' => [['url' => 'not a url', 'error_code' => 'invalid_request', 'error_message' => 'invalid url']],
        ])));

        $res = $this->api->scrapeBatchAsync(new ScrapeRequest(url: ['https://a.com/', 'not a url'], webhookUrl: 'https://hook.example/h'));

        $this->assertSame('b-4', $res->id);
        $this->assertSame('invalid_request', $res->invalidUrls[0]->errorCode);
        $body = json_decode((string)$this->requestHistory[0]['request']->getBody(), true);
        $this->assertSame('https://hook.example/h', $body['webhook_url']);
    }

    public function testGetBatchSendsLimitAndCursor(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['id' => 'b-5', 'status' => 'scraping', 'data' => []])));

        $this->api->getBatch('b-5', 50, 'abc');

        $uri = $this->requestHistory[0]['request']->getUri();
        $this->assertSame('/v2/batch/scrape/b-5', $uri->getPath());
        $this->assertSame('limit=50&cursor=abc', $uri->getQuery());
    }

    public function testGetBatchErrorsAndCancelBatch(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'errors' => [['url' => 'https://b.com/', 'error_code' => 'webpage_non_success', 'error_message' => 'bad']],
                'robots_blocked' => ['https://c.com/'],
            ])),
            new Response(200, [], json_encode(['status' => 'canceled']))
        );

        $errors = $this->api->getBatchErrors('b-6');
        $this->assertSame('https://b.com/', $errors->errors[0]->url);
        $this->assertSame(['https://c.com/'], $errors->robotsBlocked);
        $this->assertSame('/v2/batch/scrape/b-6/errors', $this->requestHistory[0]['request']->getUri()->getPath());

        $this->assertSame(['status' => 'canceled'], $this->api->cancelBatch('b-6'));
        $this->assertSame('DELETE', $this->requestHistory[1]['request']->getMethod());
    }

    public function testMarkdownWrapsNetworkError(): void
    {
        $this->mockHandler->append(
            new RequestException('Connection refused', new Request('POST', '/markdown'))
        );

        try {
            $this->api->markdown(new MarkdownRequest('https://example.com'));
            $this->fail('Expected WebcrawlerApiException');
        } catch (WebcrawlerApiException $e) {
            $this->assertSame('network_error', $e->getErrorCode());
            $this->assertSame(0, $e->getStatusCode());
        }
    }
}
