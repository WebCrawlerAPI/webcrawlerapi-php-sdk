<?php

namespace WebCrawlerAPI\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use WebCrawlerAPI\Models\CrawlResponse;

class CrawlResponseTest extends TestCase
{
    public function testCrawlResponseConstructor(): void
    {
        $response = new CrawlResponse('job-123');

        $this->assertEquals('job-123', $response->id);
    }

    public function testCrawlResponseWithEmptyId(): void
    {
        $response = new CrawlResponse('');

        $this->assertEquals('', $response->id);
    }

    public function testCrawlResponseIdIsReadonly(): void
    {
        $response = new CrawlResponse('job-123');

        $reflection = new \ReflectionClass($response);
        $property = $reflection->getProperty('id');

        $this->assertTrue($property->isReadOnly());
    }

    public function testCrawlResponseWithLongId(): void
    {
        $longId = str_repeat('a', 1000);
        $response = new CrawlResponse($longId);

        $this->assertEquals($longId, $response->id);
    }

    public function testCrawlResponseWithSpecialCharacters(): void
    {
        $specialId = 'job-123_test@example.com#456';
        $response = new CrawlResponse($specialId);

        $this->assertEquals($specialId, $response->id);
    }

    public function testCrawlResponseIsImmutable(): void
    {
        $response = new CrawlResponse('job-123');
        
        $this->expectException(\Error::class);
        $response->id = 'new-id';
    }
}