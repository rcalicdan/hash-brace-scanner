<?php

declare(strict_types=1);

namespace HashBraceScanner;

use ZipArchive;

/**
 * Downloads the latest release of the most popular Packagist packages.
 */
final class Fetcher
{
    private const PER_PAGE = 100;

    /**
     * @var resource
     */
    private $context;

    public function __construct(private readonly string $targetDir)
    {
        $this->context = stream_context_create(['http' => [
            'header' => "User-Agent: hash-brace-scanner (BC research)\r\n",
            'timeout' => 30,
            'follow_location' => 1,
        ]]);
    }

    /**
     * @return list<string>
     */
    public function popularPackageNames(int $count): array
    {
        $names = [];

        for ($page = 1; \count($names) < $count; $page++) {
            $url = 'https://packagist.org/explore/popular.json?per_page=' . self::PER_PAGE . "&page={$page}";
            $json = $this->get($url);
            $data = $json === null ? null : json_decode($json, true);

            if (empty($data['packages'])) {
                break;
            }

            foreach ($data['packages'] as $package) {
                $names[] = $package['name'];
            }
        }

        return \array_slice($names, 0, $count);
    }

    public function download(string $name): bool
    {
        $meta = $this->get("https://repo.packagist.org/p2/{$name}.json");
        if ($meta === null) {
            return false;
        }

        $versions = json_decode($meta, true)['packages'][$name] ?? [];
        $distUrl = $versions[0]['dist']['url'] ?? null;
        if ($distUrl === null) {
            return false;
        }

        $zipData = $this->get($distUrl);
        if ($zipData === null) {
            return false;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pkg');
        file_put_contents($tmp, $zipData);

        $zip = new ZipArchive();
        $ok = $zip->open($tmp) === true
            && $zip->extractTo($this->targetDir . '/' . str_replace('/', '__', $name));
        $zip->close();
        unlink($tmp);

        return $ok;
    }

    private function get(string $url): ?string
    {
        $result = @file_get_contents($url, false, $this->context);

        return $result === false ? null : $result;
    }
}
