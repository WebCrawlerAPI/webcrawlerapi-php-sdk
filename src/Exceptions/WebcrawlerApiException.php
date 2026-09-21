<?php

namespace WebCrawlerAPI\Exceptions;

use RuntimeException;
use Throwable;

class WebcrawlerApiException extends RuntimeException
{
    private string $errorCode;
    private string $errorMessage;
    private int $statusCode;

    public function __construct(
        string $errorCode,
        string $errorMessage,
        int $statusCode = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($errorMessage, $statusCode, $previous);
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * HTTP status code of the failed response, 0 when no response was received.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
