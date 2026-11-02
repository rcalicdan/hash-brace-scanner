Here is the updated `README.md` with the honest limitations section added back in to provide full transparency on the tool's scope.

```markdown
# hash-brace-scanner

An AST scanner measuring the backward-compatibility impact of introducing a bounded `#{ ... }` token in PHP.

This tool scans PHP codebases for existing `#{` comments to determine if converting them into bounded tokens would break existing code.

## Requirements

* **PHP 8.4+**
* Extensions: `tokenizer`, `zip`, `curl`

## Installation

```bash
composer install
cp .env.example .env
```

Open `.env` to set your configuration. **A GitHub Personal Access Token (`GITHUB_TOKEN`) is highly recommended** to bypass API rate limits when downloading packages.

```dotenv
GITHUB_TOKEN=ghp_yourTokenHere123456789
FETCH_COUNT=500
FETCH_CONCURRENCY=8
FETCH_WORKERS=4
FETCH_TARGET="corpus"
FETCH_REPORT="reports/fetch-report.json"
```

## Usage

### 1. Fetching a Corpus

Downloads the latest releases of the most popular Packagist packages concurrently.

```bash
php bin/fetch-corpus
```
*Note: You can override `.env` variables via CLI flags:*  
`php bin/fetch-corpus 1000 --concurrency=12 --workers=8`

* **`--concurrency`**: Controls the number of simultaneous network I/O downloads (HTTP fetching).
* **`--workers`**: Controls the number of parallel CPU background processes used for extracting ZIP files.

### 2. Scanning

Scans directories for `#{` comments utilizing all available CPU cores.

```bash
php bin/scan corpus/ > reports/report.json
```

*Note: You can control the number of parallel CPU workers and specify an output file directly:*  
`php bin/scan corpus/ --concurrency=14 --output=reports/report.json`

*You can also point the scanner at any custom codebase:*  
`php bin/scan /path/to/legacy/app`

## What it Reports

Each real `T_COMMENT` starting with `#{` (strings, heredocs, and inline HTML are automatically ignored by the tokenizer) is classified:

| Category        | Meaning under a bounded token                               |
|-----------------|-------------------------------------------------------------|
| `unterminated`  | No matching `}` on the line: would become a parse error     |
| `trailing_code` | Text after the matching `}`: the tail would become live code |
| `clean`         | Closes with nothing after: safely becomes metadata           |

`breaking_hits = unterminated + trailing_code` is the headline metric determining the syntax's safety.

## Limitations

* **"Clean Code" Bias:** The top Packagist packages are generally well-maintained, PSR-compliant library code. This may understate the collision rate in legacy enterprise applications or CMS plugins.
* **Latest Releases Only:** The fetcher only downloads the single newest release of each package.
* **Single-line Scope Constraints:** In current PHP, `#` comments terminate strictly at the newline. Therefore, multi-line `#{ ... }` blocks do not exist in the wild today. 
* **Quoted Braces:** Literal curly braces inside quoted text *within* an existing comment are currently counted as structural braces by the scanner.
* **Windows Path Limits:** On Windows, deeply nested vendor packages can exceed the 260-character path limit when extracted. Use a short `--target` path such as `C:\c` if you encounter `bad_zip` errors.

## IDE Configuration

To prevent your IDE (VS Code / PhpStorm) from freezing while attempting to index hundreds of thousands of third-party PHP files, this repository includes `.vscode/settings.json` and `.idea/` configurations that automatically mark the `corpus/` directory as excluded.
```