<?php

declare(strict_types=1);

namespace HashBraceScanner\Fetch;

final class FetchReport
{
    /**
     * @var list<PackageResult>
     */
    private array $fetched = [];

    /**
     * @var list<array{package: string, reason: string, message: string}>
     */
    private array $failures = [];

    public function __construct(public readonly int $requested)
    {
    }

    public function recordSuccess(PackageResult $result): void
    {
        $this->fetched[] = $result;
    }

    public function recordFailure(string $package, FailureReason $reason, string $message): void
    {
        $this->failures[] = ['package' => $package, 'reason' => $reason->value, 'message' => $message];
    }

    public function succeeded(): int
    {
        return \count($this->fetched);
    }

    public function failed(): int
    {
        return \count($this->failures);
    }

    /**
     * @return array<string, int>
     */
    public function failuresByReason(): array
    {
        $counts = [];
        foreach ($this->failures as $failure) {
            $counts[$failure['reason']] = ($counts[$failure['reason']] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    public function hasRateLimitFailures(): bool
    {
        return isset($this->failuresByReason()[FailureReason::RateLimited->value]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requested' => $this->requested,
            'succeeded' => $this->succeeded(),
            'failed' => $this->failed(),
            'failures_by_reason' => $this->failuresByReason(),
            'fetched' => array_map(
                static fn (PackageResult $r): array => ['name' => $r->name, 'version' => $r->version],
                $this->fetched
            ),
            'failures' => $this->failures,
        ];
    }
}
