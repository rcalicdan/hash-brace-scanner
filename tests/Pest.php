<?php

declare(strict_types=1);

use HashBraceScanner\Fetch\FailureReason;
use HashBraceScanner\Fetch\FetchFailedException;
use HashBraceScanner\Scanner;

/**
 * Scan a single fixture file and return the report.
 *
 * @return array<string, mixed>
 */
function scanFixture(string $name): array
{
    $scanner = new Scanner();
    $scanner->scanFile(__DIR__ . '/Fixtures/' . $name);

    return $scanner->report();
}

/**
 * Run a callable and return the FailureReason it throws, or null if it doesn't throw one.
 */
function failureReason(Closure $fn): ?FailureReason
{
    try {
        $fn();
    } catch (FetchFailedException $e) {
        return $e->reason;
    }

    return null;
}

/**
 * Create a fresh temporary directory and return its path.
 */
function makeTempDir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hbs-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    return $dir;
}

function removeDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}
