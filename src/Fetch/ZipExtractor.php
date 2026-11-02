<?php

declare(strict_types=1);

namespace HashBraceScanner\Fetch;

use ZipArchive;

final class ZipExtractor
{
    public static function extract(string $package, string $zipPath, string $destination): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new FetchFailedException(FailureReason::BadZip, $package, "Not a valid zip: {$package}");
        }

        $ok = $zip->extractTo($destination);
        $zip->close();

        if (! $ok) {
            throw new FetchFailedException(FailureReason::BadZip, $package, "Could not extract zip: {$package}");
        }
    }
}
