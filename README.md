# CitOmni PDF

PDF in and out for CitOmni applications: checking and reading PDFs the app receives, and generating and assembling PDFs the app sends.

`citomni/pdf` is a provider package for PHP 8.5+. It contributes the service `pdfReader` (input, never returns a PDF) and the CLI command `pdf:doctor`; the service `pdfWriter` (output, always returns a PDF) arrives with the first generation engine. The package orchestrates mature tools and libraries behind an explicit, low-overhead API instead of writing its own layout engine.

Untrusted PDFs are only parsed by a backend with enforced limits: a separate process with time, memory, and file-size limits, or the package's own budgeted pure-PHP reader. The upload check below reads two small slices and parses nothing. Shared hosting without process support is a first-class target, so nothing in the package needs a process to install or boot.

---

## Status

This version ships the provider wiring and the first gate for uploads. The other parts follow in separate releases.

| Part | Service | State |
|---|---|---|
| Header and end-marker check for uploads | `pdfReader->looksLikePdf()` | Shipped |
| Capability report and deploy check | `pdfReader->capabilities()`, `pdf:doctor` | Shipped |
| Structure inspection in pure PHP: version, pages, page boxes, encryption, metadata | `pdfReader->inspect()` | Next |
| HTML to PDF through dompdf | `pdfWriter->fromHtml()` | Planned |
| Rasterization, text, and attachments through poppler and qpdf | `pdfReader` | Planned, needs processes |
| Merge, split, overlay, decrypt through qpdf | `pdfWriter` | Planned, needs processes |

`capabilities()` and `pdf:doctor` already report every operation, so an app can check for an operation before it calls it.

---

## Requirements

- PHP 8.5+
- `citomni/kernel` 1.0.2.8+
- Optional: `citomni/cli` to run `pdf:doctor`

---

## Installation

```bash
composer require citomni/pdf
```

Enable the provider in the host app's `/config/providers.php`:

```php
<?php
declare(strict_types=1);

return [
	\CitOmni\Pdf\Boot\Registry::class,
];
```

---

## Quick start

Reject an upload that failed or is not shaped like a PDF before storing or parsing it:

```php
$upload = $_FILES['voucher'];

if ($upload['error'] !== \UPLOAD_ERR_OK || !\is_uploaded_file($upload['tmp_name']) || !$this->app->pdfReader->looksLikePdf($upload['tmp_name'])) {
	// Reject the upload: it failed, or it is not shaped like a PDF.
}
```

Check the upload error first: PHP leaves `tmp_name` empty when an upload fails, and `looksLikePdf()` throws for a path that is not a readable file.

Check what the host can do:

```bash
php bin/citomni pdf:doctor
```

---

## Public API

### `pdfReader->looksLikePdf(string $path): bool`

The cheap first gate for uploads. It reads the first 8 bytes of the file and, when they hold a header, the last 1,024 bytes. Reads are unbuffered, so nothing more is read. One rule applies:

- The header `%PDF-1.x` or `%PDF-2.x` starts at byte 0. There is no offset tolerance, so junk before the header (a BOM, a mail header, an HTTP response) fails.
- `%%EOF` occurs in the last 1,024 bytes. Incremental updates pass, because the newest end marker is last; long trailing junk fails.

A match says the file is shaped like a PDF, nothing more. A file that passes may still be damaged, locked, or hostile.

A path that is not a readable regular file throws `\RuntimeException`; that is the caller's mistake, not a property of the file.

For bytes already in memory, apply the same rule with `\CitOmni\Pdf\Util\PdfSignature`:

```php
$ok = \CitOmni\Pdf\Util\PdfSignature::matches($firstBytes, $lastBytes);
```

### `pdfReader->capabilities(): array`

Reports what the host can do. It never runs a backend on a file; in this version no backend ships, so it starts no process at all.

```php
[
	'process'    => false,  // proc_open exists; false on shared hosting without processes
	'backends'   => [],     // every backend this version ships, with availability and version
	'operations' => [       // every operation, mapped to the backend that would run it, or null
		'inspect'     => null,
		'rasterize'   => null,
		'text'        => null,
		'attachments' => null,
		'html'        => null,
		'typst'       => null,
		'assemble'    => null,
		'decrypt'     => null,
	],
]
```

The operation names are the values of `\CitOmni\Pdf\Enum\PdfOperation` and are stable. An operation whose method a version does not ship yet is always `null`.

---

## `pdf:doctor`

Reports whether PHP may start processes, the backends this version ships, the backend behind every operation, and the operations the app requires. Run it in deploys.

```bash
php bin/citomni pdf:doctor
php bin/citomni pdf:doctor --json
```

| Option | Effect |
|---|---|
| `-j`, `--json` | Print one JSON document with `ok` and the check result |

Exit codes:

| Code | Meaning |
|---|---|
| 0 | Every operation in `pdf.doctor.require` is available; with nothing required, always |
| 1 | A required operation is not available, or the run ended with an uncaught error such as invalid cfg. After an error, stdout is empty, also with `--json` |
| 2 | Invalid options or arguments, from the kernel's argument parsing |

With `--json`, the document is one line in the CitOmni envelope. `ok` is true exactly when the exit code is 0, and `backends` is a JSON object, also when it is empty:

```json
{"ok":false,"result":{"report":{"process":true,"backends":{},"operations":{"inspect":null,"rasterize":null,"text":null,"attachments":null,"html":null,"typst":null,"assemble":null,"decrypt":null}},"required":["inspect"],"missing":["inspect"]}}
```

With nothing required, a host without any backend passes, so a shared host stays green. Invalid cfg is not caught: `pdf` or `pdf.doctor` that is not a map, `pdf.doctor` with another key than `require`, or `require` that is not a list of operations, gives `\CitOmni\Pdf\Exception\PdfConfigException` with the cfg path in its message, so a misspelled key fails instead of passing silently. A missing `pdf`, `pdf.doctor` or `pdf.doctor.require`, for example after an override with an empty array, gives `\OutOfBoundsException` from the kernel's `Cfg`.

The checks live in `\CitOmni\Pdf\Support\Capabilities`, so an admin page can show the same result on a host without a shell:

```php
$check = (new \CitOmni\Pdf\Support\Capabilities($this->app))->check();
// $check = ['ok' => bool, 'report' => [...], 'required' => [...], 'missing' => [...]]
```

---

## Configuration

The package ships this baseline in `Registry::CFG_COMMON`:

```php
'pdf' => [
	'doctor' => [
		'require' => [],  // Operations that pdf:doctor must find available, as PdfOperation values or cases; an empty list requires nothing.
	],
],
```

Override it in the app's configuration like any other provider cfg. `require` takes operation values or `\CitOmni\Pdf\Enum\PdfOperation` cases, and it is the only key under `pdf.doctor`.

---

## Tests

The suites are plain PHP scripts with their own bootstrap and kernel doubles, as defined by the [CitOmni testing guide](https://github.com/citomni/docs/blob/main/contribute/testing-guide.md). No suite loads Composer's autoloader, but run `composer install` in the package root first: the doctor suite parses argv with the real `ArgvParser` of citomni/kernel from `vendor/`.

```bash
php tests/run.php
php tests/reader/run.php    # one suite alone
```

| Suite | Covers |
|---|---|
| `tests/util` | `PdfSignature` and the `PdfOperation` vocabulary |
| `tests/reader` | `looksLikePdf()` on real files and the shape of `capabilities()` |
| `tests/doctor` | The Registry entries, `Support\Capabilities`, and `pdf:doctor` |

Each suite has a README with its checks and expected totals; [`tests/README.md`](tests/README.md) describes the layout and how to write a suite.

---

## Coding conventions

- PHP 8.5+
- PSR-1 / PSR-4
- PascalCase classes
- camelCase methods and variables
- UPPER_SNAKE_CASE constants
- K&R braces
- Tabs for indentation
- PHPDoc and inline comments in English
- Fail fast unless a failure is genuinely recoverable

See [`CONVENTIONS.md`](./CONVENTIONS.md).

---

## License

citomni/pdf is released under the MIT License.
See [`LICENSE`](./LICENSE) and [`NOTICE`](./NOTICE).

## Trademarks

See [`TRADEMARKS.md`](./TRADEMARKS.md) for the CitOmni trademark notice
applicable to this package.
