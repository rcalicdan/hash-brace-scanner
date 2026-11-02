<?php

declare(strict_types=1);

namespace HashBraceScanner\Fetch;

final readonly class PackageDist
{
    public function __construct(
        public string $url,
        public string $version,
    ) {
    }

    /**
     * Zipballs served from api.github.com count against the GitHub API rate limit.
     */
    public function isGitHubApi(): bool
    {
        return str_starts_with($this->url, 'https://api.github.com/');
    }
}
