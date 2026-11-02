# hash-brace-scanner

Measures the backward-compatibility impact of introducing a bounded
`#{ ... }` token in PHP, by scanning real-world code for existing comments
that start with `#{`.

Today `#{` is an ordinary single-line comment. If the engine made it a
bounded token, existing comments would change meaning. This tool counts them.

## What it reports

Each real `T_COMMENT` starting with `#{` (strings, heredocs and inline HTML
are excluded by using the tokenizer) is classified:

| Category        | Meaning under a bounded token                               |
|-----------------|-------------------------------------------------------------|
| `unterminated`  | No matching `}` on the line: would become a parse error     |
| `trailing_code` | Text after the matching `}`: the tail would become live code |
| `clean`         | Closes with nothing after: comment becomes a doc-comment    |

`breaking_hits = unterminated + trailing_code` is the headline number.
`annotation_like` counts comments already shaped like `#{@tag ...}`.

## Usage

```bash
composer install
vendor/bin/pest                        # run the tests
php bin/fetch-corpus 500               # download top 500 Packagist packages to corpus/
php bin/scan corpus/ > reports/report.json
php bin/scan ~/projects/my-app src/    # scan any directories
```

Requires PHP 8.4+ with the `tokenizer`, `zip` and `curl` extensions. Classes are
loaded through Composer's PSR-4 autoloader (`HashBraceScanner\` maps to `src/`).

### Fetching a corpus

`bin/fetch-corpus` downloads packages concurrently with
[Hibla](https://github.com/hiblaphp) (`hiblaphp/http-client` and
`Promise::mapSettled()`), so one failing package never cancels the others.

```bash
php bin/fetch-corpus 1000 --concurrency=8
php bin/fetch-corpus 200 --target=D:\corpus --report=reports\run1.json
```

| Option          | Default                     | Meaning                              |
|-----------------|-----------------------------|--------------------------------------|
| `[count]`       | `500`                       | How many top packages to fetch       |
| `--concurrency` | `8`                         | Downloads in flight at once          |
| `--target`      | `corpus/`                   | Where packages are extracted         |
| `--report`      | `reports/fetch-report.json` | Per-package results and failures     |

**Set `GITHUB_TOKEN`.** Packagist's zip URLs usually point at `api.github.com`,
whose unauthenticated rate limit is low. Without a token, large runs will
report `rate_limited` failures. Use a personal access token locally
(`$env:GITHUB_TOKEN = "..."` in PowerShell) and `secrets.GITHUB_TOKEN` in Actions
(already wired up in the workflow).

`reports/fetch-report.json` lists every package fetched (with version) and every
failure with its reason (`rate_limited`, `not_found`, `no_dist`, `bad_zip`,
`timeout`, `network`, ...), so the corpus size you quote is verifiable.

## Running online

Run the **Corpus scan** workflow from the repository's Actions tab. It runs
the Pest suite, downloads the corpus, scans it, writes both summaries to the
run summary and uploads `report.json` and `fetch-report.json` as artifacts.

## Layout

```
bin/scan                      CLI: scan directories, print JSON report
bin/fetch-corpus              CLI: download top Packagist packages
src/Category.php              Enum of BC-impact categories
src/Classifier.php            Brace-balancing classification of one comment
src/Scanner.php               Tokenizes files and aggregates results
src/Fetch/Fetcher.php         Hibla-based concurrent download (Promise::mapSettled)
src/Fetch/DistResolver.php    Picks the newest release URL from Packagist metadata
src/Fetch/ZipExtractor.php    Extracts a downloaded zip
src/Fetch/FetchReport.php     Aggregates successes and failures by reason
tests/Unit/                   Classifier and fetch-unit tests (Pest datasets)
tests/Feature/                Scanner tests against real fixture files
tests/Fixtures/               One small PHP file per case, including the
                              "#{ that must NOT be counted" cases
```

The HTTP path in `Fetcher` is not covered by the offline tests; the logic around
it (`DistResolver`, `ZipExtractor`, `FetchReport`) is.

## Limitations

- Popular packages are well-maintained library code, so results likely
  understate the rate in applications, plugins and legacy projects.
- Only the latest release of each package is scanned.
- Single-line only: `#` comments end at the newline, so multi-line `#{ }`
  blocks are not a case today.
- Braces inside quoted text within a comment are counted as braces.
- Hibla is in beta: `composer.json` sets `minimum-stability` to `beta`.
- On Windows, deeply nested packages can exceed the 260-character path limit
  when extracted; use a short `--target` such as `C:\c` if you see `bad_zip`.
