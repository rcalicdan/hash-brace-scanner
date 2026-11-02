<?php

declare(strict_types=1);

namespace HashBraceScanner\Fetch;

use Closure;
use Hibla\HttpClient\Exceptions\NetworkException;
use Hibla\HttpClient\Exceptions\TimeoutException;
use Hibla\HttpClient\Http;
use Hibla\HttpClient\Interfaces\HttpClientInterface;
use Hibla\Parallel\ProcessPool;
use Hibla\Promise\Promise;
use RuntimeException;
use Throwable;

use function Hibla\async;
use function Hibla\await;

/**
 * Downloads the latest release of popular Packagist packages concurrently.
 * Uses Hibla HTTP Client for async network I/O and Hibla Parallel ProcessPool
 * to offload CPU-bound zip decompression across CPU cores.
 */
final class Fetcher
{
    private const string POPULAR_URL = 'https://packagist.org/explore/popular.json';
    private const string METADATA_URL = 'https://repo.packagist.org/p2/';
    private const int PER_PAGE = 100;
    private const int PAGE_CONCURRENCY = 4;

    private HttpClientInterface $http;
    private ProcessPool $pool;

    public function __construct(
        private readonly string $targetDir,
        private readonly int $concurrency = 8,
        private readonly ?string $githubToken = null,
        int $workerCount = 4
    ) {
        if ($concurrency < 1) {
            throw new RuntimeException('Concurrency must be at least 1.');
        }

        $this->http = Http::client()
            ->withUserAgent('hash-brace-scanner (BC research)')
            ->connectTimeout(10)
            ->timeout(60)
            ->redirects(true, 10)
            ->retry(maxRetries: 3, baseDelay: 1.0);

        $this->pool = new ProcessPool(size: $workerCount);
    }

    /**
     * @return list<string>
     */
    public function popularPackageNames(int $count): array
    {
        $pages = (int) ceil($count / self::PER_PAGE);

        $results = await(Promise::mapSettled(
            range(1, $pages),
            fn(int $page) => $this->http->get(self::POPULAR_URL, ['per_page' => self::PER_PAGE, 'page' => $page]),
            concurrency: self::PAGE_CONCURRENCY,
        ));

        $names = [];
        foreach ($results as $result) {
            if (!$result->isFulfilled() || !$result->value->successful()) {
                continue;
            }
            foreach ($result->value->json('packages', []) as $package) {
                $names[] = $package['name'];
            }
        }

        return \array_slice($names, 0, $count);
    }

    /**
     * @param list<string> $names
     * @param (Closure(int, int, string): void)|null $onProgress
     */
    public function fetchAll(array $names, ?Closure $onProgress = null): FetchReport
    {
        $names = array_values($names);
        $total = \count($names);
        $done = 0;
        $report = new FetchReport($total);

        $tick = function (string $name) use (&$done, $total, $onProgress): void {
            $done++;
            if ($onProgress !== null) {
                $onProgress($done, $total, $name);
            }
        };

        $results = await(Promise::mapSettled(
            $names,
            fn(string $name) => $this->fetchOne($name, $tick),
            concurrency: $this->concurrency,
        ));

        foreach ($results as $index => $result) {
            if ($result->isFulfilled()) {
                $report->recordSuccess($result->value);
                continue;
            }

            [$reason, $message] = self::classify($result->reason);
            $report->recordFailure($names[$index], $reason, $message);
        }

        return $report;
    }

    /**
     * @return Promise<PackageResult>
     */
    private function fetchOne(string $name, Closure $onDone): Promise
    {
        return async(function () use ($name, $onDone): PackageResult {
            try {
                $packageDir = $this->targetDir . DIRECTORY_SEPARATOR . str_replace('/', '__', $name);

                if (is_dir($packageDir)) {
                    return new PackageResult($name, 'cached');
                }

                $meta = await($this->http->get(self::METADATA_URL . $name . '.json'));
                self::assertOk($name, $meta->status());

                $dist = DistResolver::resolve($name, $meta->json() ?? []);

                $tmp = tempnam(sys_get_temp_dir(), 'hbs');

                try {
                    $client = ($dist->isGitHubApi() && $this->githubToken !== null)
                        ? $this->http->withToken($this->githubToken)
                        : $this->http;

                    $download = await($client->download($dist->url, $tmp));
                    self::assertOk($name, (int) $download['status']);

                    await($this->pool->run(static function () use ($name, $tmp, $packageDir): void {
                        ZipExtractor::extract($name, $tmp, $packageDir);
                    }));
                } finally {
                    @unlink($tmp);
                }

                return new PackageResult($name, $dist->version);
            } finally {
                $onDone($name);
            }
        });
    }

    private static function assertOk(string $package, int $status): void
    {
        if ($status === 200) {
            return;
        }

        $reason = match (true) {
            $status === 404 => FailureReason::NotFound,
            $status === 403, $status === 429 => FailureReason::RateLimited,
            default => FailureReason::HttpError,
        };

        throw new FetchFailedException($reason, $package, "HTTP {$status} for {$package}");
    }

    /**
     * @return array{0: FailureReason, 1: string}
     */
    private static function classify(mixed $error): array
    {
        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;

        return match (true) {
            $error instanceof FetchFailedException => [$error->reason, $message],
            $error instanceof TimeoutException => [FailureReason::Timeout, $message],
            $error instanceof NetworkException => [FailureReason::Network, $message],
            default => [FailureReason::Unknown, $message],
        };
    }
}
