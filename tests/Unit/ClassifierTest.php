<?php

declare(strict_types=1);

use HashBraceScanner\Category;
use HashBraceScanner\Classifier;

describe('classify', function () {
    it('classifies a comment by what follows its matching brace', function (string $comment, Category $expected) {
        expect(Classifier::classify($comment))->toBe($expected);
    })->with([
        'clean annotation' => ['#{@var int}', Category::Clean],
        'clean with padding' => ['#{ @var int }  ', Category::Clean],
        'nested braces' => ['#{@param array{id: int, name: string}}', Category::Clean],
        'trailing code' => ['#{ old } $x = 1;', Category::TrailingCode],
        'ruby-style interpolation' => ['#{name} is here', Category::TrailingCode],
        'unterminated' => ['#{ no closing brace', Category::Unterminated],
        'unbalanced nested' => ['#{@param array{id: int}', Category::Unterminated],
    ]);
});

describe('isAnnotationLike', function () {
    it('detects comments shaped like the proposed syntax', function (string $comment, bool $expected) {
        expect(Classifier::isAnnotationLike($comment))->toBe($expected);
    })->with([
        'tag directly after brace' => ['#{@var int}', true],
        'tag after a space' => ['#{ @var int}', true],
        'plain text' => ['#{ just text }', false],
    ]);
});
