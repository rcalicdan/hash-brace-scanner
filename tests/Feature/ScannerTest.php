<?php

declare(strict_types=1);

use HashBraceScanner\Scanner;

describe('single files', function () {
    it('counts hits by category', function (string $fixture, string $category) {
        $stats = scanFixture($fixture)['stats'];

        expect($stats['hits'][$category])->toBe(1)
            ->and($stats['files_with_hits'])->toBe(1)
        ;
    })->with([
        'annotation' => ['annotation.php', 'clean'],
        'nested braces' => ['nested_braces.php', 'clean'],
        'trailing code' => ['trailing_code.php', 'trailing_code'],
        'ruby interpolation' => ['ruby_interpolation.php', 'trailing_code'],
        'unterminated' => ['unterminated.php', 'unterminated'],
    ]);

    it('ignores #{ that is not the start of a real comment', function (string $fixture) {
        $stats = scanFixture($fixture)['stats'];

        expect($stats['files_with_hits'])->toBe(0)
            ->and($stats['breaking_hits'])->toBe(0)
            ->and(array_sum($stats['hits']))->toBe(0)
        ;
    })->with([
        'inside strings' => 'in_string.php',
        'inside a heredoc' => 'in_heredoc.php',
        'inside a docblock' => 'in_docblock.php',
        'inside a block comment' => 'in_block_comment.php',
        'not at start of a comment' => 'not_at_comment_start.php',
        'inside inline HTML' => 'inline_html.php',
    ]);

    it('flags annotation-shaped comments', function () {
        expect(scanFixture('annotation.php')['stats']['annotation_like'])->toBe(1)
            ->and(scanFixture('trailing_code.php')['stats']['annotation_like'])->toBe(0)
        ;
    });

    it('records a sample with file, line and text', function () {
        $sample = scanFixture('trailing_code.php')['samples']['trailing_code'][0];

        expect($sample['line'])->toBe(2)
            ->and($sample['text'])->toBe('#{ old } $y = 2;')
            ->and($sample['file'])->toEndWith('trailing_code.php')
        ;
    });
});

describe('directory scan', function () {
    it('aggregates every php file and skips other extensions', function () {
        $scanner = new Scanner();
        $scanner->scanDirectory(__DIR__ . '/../Fixtures');
        $stats = $scanner->report()['stats'];

        expect($stats['files_scanned'])->toBe(11)
            ->and($stats['files_with_hits'])->toBe(5)
            ->and($stats['hits'])->toBe(['unterminated' => 1, 'trailing_code' => 2, 'clean' => 2])
            ->and($stats['breaking_hits'])->toBe(3)
            ->and($stats['annotation_like'])->toBe(2)
        ;
    });
});
