# tests/

Standalone PHP test suites, as defined by the CitOmni testing guide ([`contribute/testing-guide.md`](https://github.com/citomni/docs/blob/main/contribute/testing-guide.md) in citomni/docs). No PHPUnit and no test framework: each suite is a PHP script that runs in its own process.

The suites run in the package root, after `composer install` there, without an app. `.gitattributes` keeps `tests/` out of the installed package.

## Layout

- `tests/run.php` runs every suite: first all `tests/*/run.php`, then all `tests/*/database.php`, each group in alphabetical order. It is the reference runner from the testing guide; a new suite needs no registration. The package has no database suite.
- `tests/<suite>/run.php`: isolated checks.
- `tests/<suite>/README.md`: the command, the expected totals and what the suite covers.
- `tests/bootstrap.php`: the autoloader, the kernel doubles and the case runner that every suite requires. Not a suite.
- `tests/Support/`: classes the suites share, PSR-4 under `CitOmni\Pdf\Tests\Support\`. Not a suite.

## Run

```
php tests/run.php
composer test
php tests/reader/run.php
```

The last line of every suite, and of the runner, holds the totals: `N passed, M failed`, with `, K skipped` when a case cannot run on this platform. The exit code is 1 when a check fails.

## Writing a suite

- The script starts with the CLI guard and an error handler that throws `\ErrorException`. Then it requires `\dirname(__DIR__) . '/bootstrap.php'`.
- One `test('<name>', function (): void { ... })` per case, with `check()`, `same()` and `throws()` inside, and `done()` as the last statement. A case name says what must hold, so the `PASS` line reads as a fact.
- A case that the platform cannot run is reported with `skip('<name>', '<reason>')` in place of `test()`, and the suite README gives the totals with and without it. The testing guide names only the concurrency checks as optional; this platform skip goes beyond it, as the suites of citomni/image and citomni/installer do.
- Suites never load Composer's autoloader. They replace citomni/kernel's `App`, `Cfg`, `BaseService` and `BaseCommand` with the doubles in `tests/Support/Kernel/`, and Composer could load a real kernel class first. The bootstrap loads `CitOmni\Pdf\` from `src/`, `CitOmni\Pdf\Tests\` from `tests/`, and `ArgvParser` and `HelpFormatter` of `CitOmni\Kernel\Command\` from `vendor/`.
- Code under test runs unchanged. When it needs a kernel feature that a double lacks, extend the double.
- The suite's error handler also throws for a diagnostic that code under test silences with `@`, such as a failing `@\fopen()` in `PdfReader`. A case that reaches such a call sees `\ErrorException`, where an app may see the exception that the code throws next.
- There is no app and no configuration to load. Build the app double with exactly the cfg a case reads, the package's own keys included: a case does not get the Registry baseline.
- Declare no named class in a suite script: it breaks PSR-4 for `CitOmni\Pdf\Tests\`, and Composer warns about it on every install. Use an anonymous class, or a class of its own in `tests/Support/`.

## What to test where

- Utils and Enums: isolated, called directly.
- Support classes and cfg checks: isolated, with an app double that holds only the cfg the case reads.
- Services: isolated, on the `BaseService` double, against real files below a run directory that the suite removes before its totals.
- The Registry and pdf:doctor: isolated, on the `BaseCommand` double, which parses argv with the real `ArgvParser`. The demo-provider guide tries Commands and the Registry only inside an app; this package checks them here as well, because the exit codes and the JSON document of pdf:doctor are what a deploy script relies on. Try them in an app too.

## Rules

- A regression test must fail against the code without the fix it protects. Show that before you rely on it.
- Keep the expected totals in each suite README up to date, also those that change with the platform.
- The code conventions apply to tests: the license header, tabs, K&R braces, English comments.
