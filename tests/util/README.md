# Util suite

Isolated checks for the pure building blocks: the header and end-marker rule of `\CitOmni\Pdf\Util\PdfSignature` and the operation values of `\CitOmni\Pdf\Enum\PdfOperation`. No files are read or written, and no kernel double is used.

## Run

```
php tests/util/run.php
```

Expected result:

```
7 passed, 0 failed
```

`PASS` lines go to stdout, `FAIL` lines to stderr. The last line holds the totals, and the exit code is 1 when a check fails.

## Checks

| Check | Covers |
|---|---|
| PdfSignature accepts %PDF-1.x and %PDF-2.x at byte 0 | `hasHeader()` on valid headers, also with the binary comment line after them |
| PdfSignature rejects a header with an offset, a BOM, a wrong major version or wrong case | No offset tolerance; `%PDF-3.0`, `%PDF-0.9`, `%pdf-1.7`, a missing minor digit, PostScript |
| PdfSignature reads the header version | `headerVersion()`, and null without a valid header |
| PdfSignature finds the end marker anywhere in the tail | `hasEof()`, a cut-off marker, an empty tail |
| PdfSignature::matches() needs both the header and the end marker | Header and marker together |
| PdfSignature slice sizes cover a full header and a 1 KiB tail | `HEAD_BYTES` is the length of `%PDF-x.y`; `TAIL_BYTES` is 1,024 |
| PdfOperation values are stable and in a fixed order | The case values that cfg and the JSON of pdf:doctor rely on |

`php tests/run.php` runs every suite of the package.
