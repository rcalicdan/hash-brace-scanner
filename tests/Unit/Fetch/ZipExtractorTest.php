<?php

declare(strict_types=1);

use HashBraceScanner\Fetch\FailureReason;
use HashBraceScanner\Fetch\ZipExtractor;

it('extracts a zip into the destination directory', function () {
    $dir = makeTempDir();
    $zipPath = $dir . '/package.zip';

    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('lib-abc123/src/Foo.php', "<?php\n// hello\n");
    $zip->close();

    ZipExtractor::extract('acme/lib', $zipPath, $dir . '/out');

    expect(is_file($dir . '/out/lib-abc123/src/Foo.php'))->toBeTrue()
        ->and(file_get_contents($dir . '/out/lib-abc123/src/Foo.php'))->toBe("<?php\n// hello\n")
    ;

    removeDir($dir);
});

it('reports BadZip for a file that is not a zip', function () {
    $dir = makeTempDir();
    file_put_contents($dir . '/not-a-zip.zip', '{"message":"API rate limit exceeded"}');

    expect(failureReason(fn () => ZipExtractor::extract('acme/lib', $dir . '/not-a-zip.zip', $dir . '/out')))
        ->toBe(FailureReason::BadZip)
    ;

    removeDir($dir);
});

it('reports BadZip for a missing file', function () {
    $dir = makeTempDir();

    expect(failureReason(fn () => ZipExtractor::extract('acme/lib', $dir . '/missing.zip', $dir . '/out')))
        ->toBe(FailureReason::BadZip)
    ;

    removeDir($dir);
});
