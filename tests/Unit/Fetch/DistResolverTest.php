<?php

declare(strict_types=1);

use HashBraceScanner\Fetch\DistResolver;
use HashBraceScanner\Fetch\FailureReason;
use HashBraceScanner\Fetch\PackageDist;

function packagistMetadata(string $name, array $versions): array
{
    return ['packages' => [$name => $versions]];
}

describe('resolve', function () {
    it('returns the newest release dist url and version', function () {
        $dist = DistResolver::resolve('acme/lib', packagistMetadata('acme/lib', [
            ['version' => '2.0.0', 'dist' => ['url' => 'https://example.test/2.0.0.zip']],
            ['version' => '1.0.0', 'dist' => ['url' => 'https://example.test/1.0.0.zip']],
        ]));

        expect($dist->url)->toBe('https://example.test/2.0.0.zip')
            ->and($dist->version)->toBe('2.0.0')
        ;
    });

    it('falls back to "unknown" when the version is missing', function () {
        $dist = DistResolver::resolve('acme/lib', packagistMetadata('acme/lib', [
            ['dist' => ['url' => 'https://example.test/x.zip']],
        ]));

        expect($dist->version)->toBe('unknown');
    });

    it('reports NoDist when the failure is structural', function (array $metadata) {
        expect(failureReason(fn () => DistResolver::resolve('acme/lib', $metadata)))
            ->toBe(FailureReason::NoDist)
        ;
    })->with([
        'package not in metadata' => [['packages' => []]],
        'empty versions list' => [packagistMetadata('acme/lib', [])],
        'no dist key' => [packagistMetadata('acme/lib', [['version' => '1.0.0']])],
        'empty dist url' => [packagistMetadata('acme/lib', [['version' => '1.0.0', 'dist' => ['url' => '']]])],
        'completely empty' => [[]],
    ]);
});

describe('PackageDist::isGitHubApi', function () {
    it('identifies urls that count against the GitHub API rate limit', function (string $url, bool $expected) {
        expect((new PackageDist($url, '1.0.0'))->isGitHubApi())->toBe($expected);
    })->with([
        'api zipball' => ['https://api.github.com/repos/acme/lib/zipball/abc123', true],
        'codeload' => ['https://codeload.github.com/acme/lib/legacy.zip/abc123', false],
        'other host' => ['https://example.test/lib.zip', false],
    ]);
});
