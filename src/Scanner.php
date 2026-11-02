<?php

declare(strict_types=1);

namespace HashBraceScanner;

use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final class Scanner
{
    private const int MAX_SAMPLES_PER_CATEGORY = 25;

    /**
     * @var array<string, int>
     */
    private array $hits = [];

    /**
     * @var array<string, list<array{file: string, line: int, text: string}>>
     */
    private array $samples = [];

    private int $filesScanned = 0;

    private int $filesUnparsable = 0;

    private int $filesWithHits = 0;

    private int $annotationLike = 0;

    public function __construct()
    {
        foreach (Category::cases() as $category) {
            $this->hits[$category->value] = 0;
            $this->samples[$category->value] = [];
        }
    }

    /**
     * @param (\Closure(int $scanned, int $filesWithHits, array<string, int> $hits): void)|null $onProgress
     */
    public function scanDirectory(string $root, ?\Closure $onProgress = null): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->scanFile($file->getPathname());

                if ($onProgress !== null) {
                    $onProgress($this->filesScanned, $this->filesWithHits, $this->hits);
                }
            }
        }
    }

    public function scanFile(string $path): void
    {
        $code = @file_get_contents($path);
        if ($code === false) {
            return;
        }

        $this->filesScanned++;

        if (! str_contains($code, '#{')) {
            return;
        }

        try {
            $tokens = PhpToken::tokenize($code);
        } catch (Throwable) {
            $this->filesUnparsable++;

            return;
        }

        $fileHadHit = false;

        foreach ($tokens as $token) {
            if ($token->id !== T_COMMENT || ! str_starts_with($token->text, '#{')) {
                continue;
            }

            $fileHadHit = true;
            $category = Classifier::classify($token->text);
            $this->hits[$category->value]++;

            if (Classifier::isAnnotationLike($token->text)) {
                $this->annotationLike++;
            }

            if (\count($this->samples[$category->value]) < self::MAX_SAMPLES_PER_CATEGORY) {
                $this->samples[$category->value][] = [
                    'file' => $path,
                    'line' => $token->line,
                    'text' => trim($token->text),
                ];
            }
        }

        if ($fileHadHit) {
            $this->filesWithHits++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $breaking = $this->hits[Category::Unterminated->value]
            + $this->hits[Category::TrailingCode->value];

        return [
            'stats' => [
                'files_scanned' => $this->filesScanned,
                'files_unparsable' => $this->filesUnparsable,
                'files_with_hits' => $this->filesWithHits,
                'hits' => $this->hits,
                'breaking_hits' => $breaking,
                'annotation_like' => $this->annotationLike,
            ],
            'samples' => $this->samples,
        ];
    }

    /**
     * @param array{
     *   stats: array<string, mixed>,
     *   samples: array<string, list<array{file: string, line: int, text: string}>>} $other
     */
    public function merge(array $other): void
    {
        $this->filesScanned += $other['stats']['files_scanned'];
        $this->filesUnparsable += $other['stats']['files_unparsable'];
        $this->filesWithHits += $other['stats']['files_with_hits'];
        $this->annotationLike += $other['stats']['annotation_like'];

        foreach ($other['stats']['hits'] as $category => $count) {
            $this->hits[$category] = ($this->hits[$category] ?? 0) + $count;
        }

        foreach ($other['samples'] as $category => $samples) {
            foreach ($samples as $sample) {
                if (\count($this->samples[$category]) < self::MAX_SAMPLES_PER_CATEGORY) {
                    $this->samples[$category][] = $sample;
                }
            }
        }
    }
}
