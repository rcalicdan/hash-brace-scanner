<?php

declare(strict_types=1);

namespace HashBraceScanner\Fetch;

final readonly class PackageResult
{
    public function __construct(
        public string $name,
        public string $version,
    ) {
    }
}
