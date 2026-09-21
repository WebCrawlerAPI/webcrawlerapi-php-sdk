<?php

namespace WebCrawlerAPI;

final class JobStatus
{
    public const NEW = 'new';
    public const IN_PROGRESS = 'in_progress';
    public const DONE = 'done';
    public const ERROR = 'error';
    public const CANCELLED = 'cancelled';
}
