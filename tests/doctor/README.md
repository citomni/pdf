# Doctor suite

Isolated checks for the deploy check: the entries of `\CitOmni\Pdf\Boot\Registry`, the check of `\CitOmni\Pdf\Support\Capabilities` against cfg `pdf.doctor.require`, and the exit codes and output of `\CitOmni\Pdf\Command\PdfDoctorCommand`. No files are written.

The code runs unchanged on the kernel doubles in `tests/Support/Kernel/`. The app double holds only the cfg that a check gives it, without the Registry baseline, so the checks of `Capabilities` and pdf:doctor state the cfg they read. The command runs on the `BaseCommand` double, which keeps the lines the command writes and parses argv with the real `ArgvParser` and `HelpFormatter` of citomni/kernel. The suite therefore needs `vendor/` from `composer install` in the package root; without it, the suite stops before the first check and fails.

## Run

```
php tests/doctor/run.php
```

Expected result:

```
14 passed, 0 failed
```

`PASS` lines go to stdout, `FAIL` lines to stderr. The last line holds the totals, and the exit code is 1 when a check fails.

## Checks

| Check | Covers |
|---|---|
| Registry maps pdfReader, pdf:doctor and the pdf cfg baseline | `MAP_COMMON`, `CFG_COMMON`, `COMMANDS_CLI`; no mode-specific maps, cfg or routes |
| check() passes with nothing required, also without any backend | `ok`, empty `required` and `missing`, the plain report |
| check() reports a required operation without a backend as missing | Cfg order kept, duplicates removed |
| check() accepts PdfOperation cases as well as values | `[PdfOperation::Inspect, 'inspect', PdfOperation::Html]` becomes `['inspect', 'html']` |
| check() reports an invalid pdf.doctor.require with PdfConfigException | A string, a map, an unknown operation, an integer entry, a nested list; the path, and the message with the value and the known operations |
| check() reports another key under pdf.doctor, so a misspelled key cannot pass | `required` next to `require` |
| check() reports pdf or pdf.doctor that is not a map with PdfConfigException | The messages for `pdf` as a list and as null, and for `pdf.doctor` as a list and as a string |
| check() leaves a missing pdf, pdf.doctor or pdf.doctor.require to the kernel Cfg | `\OutOfBoundsException` for each missing node |
| pdf:doctor exits with SUCCESS and prints every section when nothing is required | Exit code 0, section titles, `-` for an operation without a backend, `OK` last |
| pdf:doctor exits with FAILURE and names the missing operations on stderr | Exit code 1, `missing` rows, the message with one and with two operations |
| pdf:doctor --json and -j print one JSON document with ok and the check result | Both option forms, both outcomes, one stdout line, no stderr, `{"ok": ..., "result": {"report", "required", "missing"}}`, an empty backend map as `{}` |
| pdf:doctor lets an invalid cfg value surface as PdfConfigException and prints nothing | No catch in the command, with and without `--json` |
| pdf:doctor answers an unknown option with USAGE before it reads cfg | Exit code 2 and a message on stderr, with invalid cfg that would throw if it were read |
| pdf:doctor declares a signature that the kernel accepts | `ArgvParser::validateSignature()` returns no errors, as the kernel checks in dev mode |

`php tests/run.php` runs every suite of the package.
