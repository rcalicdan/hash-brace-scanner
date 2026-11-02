<?php

declare(strict_types=1);

namespace HashBraceScanner\Fetch;

/**
 * Picks the newest release's dist URL out of a Packagist p2 metadata document.
 */
final class DistResolver
{
    /**
     * @param array<string, mixed> $metadata Decoded https://repo.packagist.org/p2/<name>.json
     */
    public static function resolve(string $name, array $metadata): PackageDist
    {
        $versions = $metadata['packages'][$name] ?? [];
        $latest = $versions[0] ?? null; // newest release first, full entry
        $url = \is_array($latest) ? ($latest['dist']['url'] ?? null) : null;

        if (! \is_string($url) || $url === '') {
            throw new FetchFailedException(FailureReason::NoDist, $name, "No dist URL for {$name}");
        }

        return new PackageDist($url, (string) ($latest['version'] ?? 'unknown'));
    }
}
