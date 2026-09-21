<?php

namespace WebCrawlerAPI;

final class ErrorCode
{
    public const BLOCKED_BY_ROBOTS_TXT = 'blocked_by_robots_txt';
    public const NETWORK_ERROR = 'network_error';
    public const INVALID_RESPONSE = 'invalid_response';
    public const UNKNOWN_ERROR = 'unknown_error';
    public const TIMEOUT = 'timeout';
}
