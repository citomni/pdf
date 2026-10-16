<?php
declare(strict_types=1);
/*
 * This file is part of the CitOmni framework.
 * Low overhead, high performance, ready for anything.
 *
 * For more information, visit https://github.com/citomni
 *
 * Copyright (c) 2012-present Lars Grove Mortensen
 * SPDX-License-Identifier: MIT
 *
 * For full copyright, trademark, and license information,
 * please see the LICENSE file distributed with this source code.
 */

namespace CitOmni\Pdf\Tests;

use CitOmni\Pdf\Tests\Support\CheckFailed;

/*
 * Shared bootstrap of the suites, not a suite of its own. A suite installs its CLI guard and
 * its error handler, then requires this file.
 *
 * Notes:
 * - Only the command double needs vendor/, for the real ArgvParser and HelpFormatter, so
 *   "composer install" must have run before a suite that runs a command.
 * - Code under test that needs a kernel feature the doubles lack fails loudly.
 *   Extend the double, never the code under test.
 */


// -- 1. Autoloader -----------------------------------------------
// Composer's autoloader is never loaded: The suites replace citomni/kernel with the doubles
// in tests/Support/Kernel/, and Composer could load a real kernel class before its double
// is in place.

\spl_autoload_register(static function (string $class): void {
	// Longest prefix first: CitOmni\Pdf\Tests\ is also under CitOmni\Pdf\.
	static $roots = [
		'CitOmni\\Pdf\\Tests\\'      => __DIR__ . '/',
		'CitOmni\\Pdf\\'             => __DIR__ . '/../src/',
		// Only ArgvParser and HelpFormatter load from here: BaseCommand is aliased to its double
		// below, before any code can ask for it.
		'CitOmni\\Kernel\\Command\\' => __DIR__ . '/../vendor/citomni/kernel/src/Command/',
	];

	foreach ($roots as $prefix => $dir) {
		if (\str_starts_with($class, $prefix)) {
			$file = $dir . \str_replace('\\', '/', \substr($class, \strlen($prefix))) . '.php';
			if (\is_file($file)) {
				require $file;
			}
			return;
		}
	}
});


// -- 2. Kernel doubles -------------------------------------------
// Code under test, and any instanceof check on a kernel class, must see the doubles under
// the citomni/kernel class names.

\class_alias(Support\Kernel\App::class, 'CitOmni\\Kernel\\App');
\class_alias(Support\Kernel\Cfg::class, 'CitOmni\\Kernel\\Cfg');
\class_alias(Support\Kernel\BaseService::class, 'CitOmni\\Kernel\\Service\\BaseService');
\class_alias(Support\Kernel\BaseCommand::class, 'CitOmni\\Kernel\\Command\\BaseCommand');


// -- 3. Case runner ----------------------------------------------

$passed  = 0;
$failed  = 0;
$skipped = 0;


/**
 * Run one case and report it.
 *
 * Behavior:
 * - A PASS line with the case name on stdout when the case returns.
 * - A FAIL line with the case name and the reason on stderr when a check fails or anything
 *   else is thrown. The suite goes on with the next case either way.
 *
 * @param  string  $name  Case name, shown in the report.
 * @param  \Closure  $case  The case.
 * @return void
 */
function test(string $name, \Closure $case): void {
	global $passed, $failed;

	try {
		$case();
		++$passed;
		\fwrite(\STDOUT, "PASS {$name}\n");
	} catch (CheckFailed $e) {
		++$failed;
		\fwrite(\STDERR, "FAIL {$name} - " . oneLine($e->getMessage()) . "\n");
	} catch (\Throwable $e) {
		++$failed;
		\fwrite(\STDERR, "FAIL {$name} - " . oneLine($e::class . ': ' . $e->getMessage() . ' at ' . \basename($e->getFile()) . ':' . $e->getLine()) . "\n");
	}
}


/**
 * Report a case that this platform cannot run.
 *
 * @param  string  $name  Case name.
 * @param  string  $reason  Why it did not run, with what it needs.
 * @return void
 */
function skip(string $name, string $reason): void {
	global $skipped;

	++$skipped;
	\fwrite(\STDOUT, "SKIP {$name}: {$reason}\n");
}


/**
 * Print the totals and end the suite.
 *
 * Behavior:
 * - Exit code 0 when no case failed, 1 otherwise.
 *
 * @return never
 */
function done(): never {
	global $passed, $failed, $skipped;

	\fwrite(\STDOUT, "{$passed} passed, {$failed} failed" . ($skipped > 0 ? ", {$skipped} skipped" : '') . "\n");
	exit($failed === 0 ? 0 : 1);
}


/**
 * Fail the current case unless the condition holds.
 *
 * @param  bool  $condition  Condition that must hold.
 * @param  string  $message  Reason reported when it does not.
 * @return void
 * @throws \CitOmni\Pdf\Tests\Support\CheckFailed  When the condition is false.
 */
function check(bool $condition, string $message): void {
	if (!$condition) {
		throw new CheckFailed($message);
	}
}


/**
 * Fail the current case unless two values are identical.
 *
 * @param  mixed  $actual  Value the code under test produced.
 * @param  mixed  $expected  Value it must be.
 * @param  string  $message  What is compared, reported with both values when they differ.
 * @return void
 * @throws \CitOmni\Pdf\Tests\Support\CheckFailed  When the values are not identical.
 */
function same(mixed $actual, mixed $expected, string $message): void {
	if ($actual !== $expected) {
		throw new CheckFailed($message . ': got ' . export($actual) . ', want ' . export($expected));
	}
}


/**
 * Fail the current case unless the action throws the expected class.
 *
 * @param  string  $class  Expected exception class.
 * @param  \Closure  $action  Code that must throw.
 * @return \Throwable  The thrown exception, for further checks.
 * @throws \CitOmni\Pdf\Tests\Support\CheckFailed  When nothing or something else is thrown.
 */
function throws(string $class, \Closure $action): \Throwable {
	try {
		$action();
	} catch (CheckFailed $e) {
		throw $e;
	} catch (\Throwable $e) {
		check($e instanceof $class, 'Expected ' . $class . ', got ' . $e::class . ': ' . $e->getMessage());

		return $e;
	}

	throw new CheckFailed('Expected ' . $class . ', nothing was thrown');
}


/**
 * Render a value as compact JSON for a failure message, cut at 400 bytes.
 *
 * @param  mixed  $value
 * @return string
 */
function export(mixed $value): string {
	$json = (string)\json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR);

	return \strlen($json) > 400 ? \substr($json, 0, 400) . '...' : $json;
}


/**
 * Collapse a multi-line message to one line.
 *
 * @param  string  $text
 * @return string
 */
function oneLine(string $text): string {
	return \trim((string)\preg_replace('/\s*\R\s*/', ' | ', $text));
}
