<?php

declare(strict_types=1);

use HashBraceScanner\Fetch\FailureReason;
use HashBraceScanner\Fetch\FetchReport;
use HashBraceScanner\Fetch\PackageResult;

function sampleReport(): FetchReport
{
    $report = new FetchReport(5);
    $report->recordSuccess(new PackageResult('acme/one', '1.0.0'));
    $report->recordSuccess(new PackageResult('acme/two', '2.1.0'));
    $report->recordFailure('acme/three', FailureReason::RateLimited, 'HTTP 403 for acme/three');
    $report->recordFailure('acme/four', FailureReason::RateLimited, 'HTTP 403 for acme/four');
    $report->recordFailure('acme/five', FailureReason::NotFound, 'HTTP 404 for acme/five');

    return $report;
}

it('counts successes and failures', function () {
    $report = sampleReport();

    expect($report->requested)->toBe(5)
        ->and($report->succeeded())->toBe(2)
        ->and($report->failed())->toBe(3)
    ;
});

it('groups failures by reason, sorted by reason name', function () {
    expect(sampleReport()->failuresByReason())->toBe(['not_found' => 1, 'rate_limited' => 2]);
});

it('detects rate limit failures', function () {
    expect(sampleReport()->hasRateLimitFailures())->toBeTrue()
        ->and((new FetchReport(0))->hasRateLimitFailures())->toBeFalse()
    ;
});

it('serialises to the report file shape', function () {
    $array = sampleReport()->toArray();

    expect($array['requested'])->toBe(5)
        ->and($array['succeeded'])->toBe(2)
        ->and($array['failed'])->toBe(3)
        ->and($array['fetched'][0])->toBe(['name' => 'acme/one', 'version' => '1.0.0'])
        ->and($array['failures'][0]['reason'])->toBe('rate_limited')
        ->and($array['failures'][0]['package'])->toBe('acme/three')
    ;
});
