<?php

declare(strict_types=1);

namespace HashBraceScanner\Fetch;

enum FailureReason: string
{
    case RateLimited = 'rate_limited';
    case NotFound = 'not_found';
    case HttpError = 'http_error';
    case NoDist = 'no_dist';
    case BadZip = 'bad_zip';
    case Timeout = 'timeout';
    case Network = 'network';
    case Unknown = 'unknown';
}
