<?php

declare(strict_types=1);

namespace HashBraceScanner\Fetch;

use RuntimeException;

final class FetchFailedException extends RuntimeException
{
    public function __construct(
        public readonly FailureReason $reason,
        public readonly string $package,
        string $message,
    ) {
        parent::__construct($message);
    }
}
