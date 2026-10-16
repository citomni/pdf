# Reader suite

Isolated checks for the `pdfReader` service, `\CitOmni\Pdf\Service\PdfReader`: `looksLikePdf()` on real files and the shape of `capabilities()`. The service runs unchanged on the kernel doubles in `tests/Support/Kernel/`, with an app double that has no cfg and no services, since neither method reads either.

Files are written below one run directory under `sys_get_temp_dir()`, which the suite removes before it prints its totals.

## Run

```
php tests/reader/run.php
```

Expected result as root, on Windows, or without ext-posix:

```
9 passed, 0 failed, 1 skipped
```

As any other POSIX user with ext-posix, the skipped check runs too: `10 passed, 0 failed`.

`PASS` and `SKIP` lines go to stdout, `FAIL` lines to stderr. The last line holds the totals, and the exit code is 1 when a check fails.

## Checks

| Check | Covers |
|---|---|
| looksLikePdf() accepts a well-formed PDF | A one-page PDF with a classic xref table |
| looksLikePdf() accepts an incremental update, where the newest end marker is last | Two `%%EOF` markers |
| looksLikePdf() rejects junk before the header and a missing header | One space, a UTF-8 BOM, a mail header, JPEG bytes named `.pdf` |
| looksLikePdf() rejects a file without an end marker | A file cut off before `%%EOF` |
| looksLikePdf() searches exactly the last TAIL_BYTES bytes for the end marker | The marker as the first bytes of the tail passes; one byte further out, or more than 1,024 bytes after it, fails |
| looksLikePdf() reads a file rewritten under the same path at its new size | The size comes from the open handle, not from the stat cache |
| looksLikePdf() handles files shorter than the slices | A 14-byte file, a file shorter than the header, an empty file |
| looksLikePdf() throws a RuntimeException for a missing file and a directory | The path check before the file is opened |
| looksLikePdf() throws a RuntimeException for a file it may not read | A file with mode 0000; skipped as root, on Windows and without ext-posix |
| capabilities() reports process support, no backends and every operation without a backend | Report keys, `process` follows `proc_open`, empty `backends`, every operation null in case order |

`php tests/run.php` runs every suite of the package.
