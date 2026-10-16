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

use CitOmni\Kernel\Command\ArgvParser;
use CitOmni\Pdf\Boot\Registry;
use CitOmni\Pdf\Command\PdfDoctorCommand;
use CitOmni\Pdf\Enum\PdfOperation;
use CitOmni\Pdf\Exception\PdfConfigException;
use CitOmni\Pdf\Service\PdfReader;
use CitOmni\Pdf\Support\Capabilities;
use CitOmni\Pdf\Tests\Support\Kernel\App;

/*
 * Isolated suite for the deploy check: the entries of Registry, the check of
 * Support\Capabilities against cfg pdf.doctor.require, and the exit codes and
 * output of pdf:doctor.
 *
 * The app double holds only cfg pdf.doctor, without the Registry baseline, so
 * every case states the cfg it reads. The command runs on the BaseCommand
 * double, which parses argv with the real ArgvParser of citomni/kernel in
 * vendor/.
 *
 * Usage:
 *   php tests/doctor/run.php
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';

if (!\is_file(\dirname(__DIR__, 2) . '/vendor/citomni/kernel/src/Command/ArgvParser.php')) {
	\fwrite(\STDERR, "citomni/kernel is missing in vendor/: Run composer install in the package root.\n");
	exit(1);
}


// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

/**
 * Create an app double whose cfg holds pdf.doctor.require and nothing else.
 *
 * @param  mixed  $require  Value of pdf.doctor.require.
 * @return App
 */
function app(mixed $require = []): App {
	return new App(['pdf' => ['doctor' => ['require' => $require]]]);
}


/**
 * Create pdf:doctor from its Registry entry, the way the CLI runner does.
 *
 * @param  App  $app
 * @return PdfDoctorCommand
 */
function command(App $app): PdfDoctorCommand {
	$entry = Registry::COMMANDS_CLI['pdf:doctor'];

	return new $entry['command']($app, 'pdf:doctor', $entry['description']);
}


/**
 * Run pdf:doctor with the given tokens.
 *
 * @param  App  $app
 * @param  string  ...$tokens  Tokens after the command name.
 * @return array{exit:int,stdout:list<string>,stderr:list<string>}
 */
function doctor(App $app, string ...$tokens): array {
	$command = command($app);
	$exit = $command->run(['citomni', 'pdf:doctor', ...$tokens]);

	return ['exit' => $exit, 'stdout' => $command->stdoutLines, 'stderr' => $command->stderrLines];
}


// -- 1. Registry -------------------------------------------------

test('Registry maps pdfReader, pdf:doctor and the pdf cfg baseline', function (): void {
	same(Registry::MAP_COMMON, ['pdfReader' => PdfReader::class], 'MAP_COMMON');
	same(Registry::CFG_COMMON, ['pdf' => ['doctor' => ['require' => []]]], 'CFG_COMMON');
	same(\array_keys(Registry::COMMANDS_CLI), ['pdf:doctor'], 'COMMANDS_CLI keys');
	same(Registry::COMMANDS_CLI['pdf:doctor']['command'], PdfDoctorCommand::class, 'pdf:doctor command class');
	check(Registry::COMMANDS_CLI['pdf:doctor']['description'] !== '', 'pdf:doctor has no description');

	foreach (['MAP_HTTP', 'MAP_CLI', 'CFG_HTTP', 'CFG_CLI', 'ROUTES_HTTP'] as $constant) {
		check(!\defined(Registry::class . '::' . $constant), $constant . ' is defined');
	}
});


// -- 2. Support\Capabilities::check() ----------------------------

test('check() passes with nothing required, also without any backend', function (): void {
	$check = (new Capabilities(app()))->check();

	same($check['ok'], true, 'ok');
	same($check['required'], [], 'required');
	same($check['missing'], [], 'missing');
	same($check['report'], (new Capabilities(app()))->report(), 'report');
});

test('check() reports a required operation without a backend as missing', function (): void {
	$check = (new Capabilities(app(['inspect', 'html', 'inspect'])))->check();

	same($check['ok'], false, 'ok');
	same($check['required'], ['inspect', 'html'], 'required, in cfg order, without duplicates');
	same($check['missing'], ['inspect', 'html'], 'missing');
});

test('check() accepts PdfOperation cases as well as values', function (): void {
	$check = (new Capabilities(app([PdfOperation::Inspect, 'inspect', PdfOperation::Html])))->check();

	same($check['required'], ['inspect', 'html'], 'required');
	same($check['missing'], ['inspect', 'html'], 'missing');
});

test('check() reports an invalid pdf.doctor.require with PdfConfigException', function (): void {
	$invalid = [
		'a string'             => 'inspect',
		'a map'                => ['first' => 'inspect'],
		'an unknown operation' => ['render'],
		'an integer entry'     => [1],
		'a nested list'        => [['inspect']],
	];

	foreach ($invalid as $label => $value) {
		$e = throws(PdfConfigException::class, fn() => (new Capabilities(app($value)))->check());
		same($e->path, 'pdf.doctor.require', 'Path for ' . $label);
	}

	$e = throws(PdfConfigException::class, fn() => (new Capabilities(app(['inspect', 'render'])))->check());
	same($e->getMessage(), 'Cfg pdf.doctor.require contains an unknown operation: "render". Known operations: inspect, rasterize, text, attachments, html, typst, assemble, decrypt.', 'Message');
});

test('check() reports another key under pdf.doctor, so a misspelled key cannot pass', function (): void {
	$e = throws(PdfConfigException::class, fn() => (new Capabilities(new App(['pdf' => ['doctor' => ['require' => [], 'required' => ['inspect']]]])))->check());

	same($e->path, 'pdf.doctor', 'Path');
	same($e->getMessage(), 'Cfg pdf.doctor has an unknown key: required. The only key is require.', 'Message');
});

test('check() reports pdf or pdf.doctor that is not a map with PdfConfigException', function (): void {
	$cases = [
		'Cfg pdf must be a map, got a list.'                                => ['pdf' => ['inspect']],
		'Cfg pdf must be a map, got null.'                                  => ['pdf' => null],
		'Cfg pdf.doctor must be a map with the key require, got a list.'    => ['pdf' => ['doctor' => ['inspect']]],
		'Cfg pdf.doctor must be a map with the key require, got string.'    => ['pdf' => ['doctor' => 'inspect']],
	];

	foreach ($cases as $message => $cfg) {
		$e = throws(PdfConfigException::class, fn() => (new Capabilities(new App($cfg)))->check());
		same($e->getMessage(), $message, 'Message for ' . export($cfg));
	}
});

test('check() leaves a missing pdf, pdf.doctor or pdf.doctor.require to the kernel Cfg', function (): void {
	foreach ([[], ['pdf' => []], ['pdf' => ['doctor' => []]]] as $cfg) {
		throws(\OutOfBoundsException::class, fn() => (new Capabilities(new App($cfg)))->check());
	}
});


// -- 3. pdf:doctor -----------------------------------------------

test('pdf:doctor exits with SUCCESS and prints every section when nothing is required', function (): void {
	$r = doctor(app());

	same($r['exit'], PdfDoctorCommand::SUCCESS, 'Exit code');
	same($r['stderr'], [], 'stderr');
	same($r['stdout'][0], 'citomni/pdf', 'First line');
	check(\in_array('  Processes  ' . (\function_exists('proc_open') ? 'yes' : 'no (proc_open is not available)'), $r['stdout'], true), 'No Processes row');

	foreach (['Backends', 'Operations', 'Required by pdf.doctor.require'] as $title) {
		check(\in_array($title, $r['stdout'], true), 'No section ' . $title);
	}

	check(\in_array('  inspect      -', $r['stdout'], true), 'An operation without a backend is not shown as "-"');
	same(\end($r['stdout']), 'OK', 'Last line');
});

test('pdf:doctor exits with FAILURE and names the missing operations on stderr', function (): void {
	$r = doctor(app(['inspect']));

	same($r['exit'], PdfDoctorCommand::FAILURE, 'Exit code');
	check(\in_array('  inspect  missing', $r['stdout'], true), 'The required operation is not marked missing');
	same($r['stderr'], ['FAILED: 1 required operation is not available: inspect'], 'stderr with one operation');

	$r = doctor(app(['inspect', 'html']));
	same($r['stderr'], ['FAILED: 2 required operations are not available: inspect, html'], 'stderr with two operations');
});

test('pdf:doctor --json and -j print one JSON document with ok and the check result', function (): void {
	foreach (['--json', '-j'] as $flag) {
		foreach ([[[], true, PdfDoctorCommand::SUCCESS], [['inspect'], false, PdfDoctorCommand::FAILURE]] as [$require, $ok, $exit]) {
			$r = doctor(app($require), $flag);
			$context = $flag . ' with ' . export($require);
			$check = (new Capabilities(app($require)))->check();

			same($r['exit'], $exit, $context . ': exit code');
			same($r['stderr'], [], $context . ': stderr');
			same(\count($r['stdout']), 1, $context . ': stdout lines');
			same(\json_decode($r['stdout'][0], true, 16, \JSON_THROW_ON_ERROR), ['ok' => $ok, 'result' => ['report' => $check['report'], 'required' => $check['required'], 'missing' => $check['missing']]], $context . ': document');
			check(\str_contains($r['stdout'][0], '"backends":{}'), $context . ': an empty backend map is not a JSON object');
		}
	}
});

test('pdf:doctor lets an invalid cfg value surface as PdfConfigException and prints nothing', function (): void {
	foreach ([[], ['--json']] as $tokens) {
		$command = command(app('inspect'));

		throws(PdfConfigException::class, fn() => $command->run(['citomni', 'pdf:doctor', ...$tokens]));
		same([$command->stdoutLines, $command->stderrLines], [[], []], 'Output with ' . export($tokens));
	}
});

test('pdf:doctor answers an unknown option with USAGE before it reads cfg', function (): void {
	// The cfg is invalid, so a check that ran would throw instead of returning USAGE.
	$r = doctor(app('inspect'), '--jsn');

	same($r['exit'], PdfDoctorCommand::USAGE, 'Exit code');
	same($r['stdout'], [], 'stdout');
	check($r['stderr'] !== [], 'No usage error on stderr');
});

test('pdf:doctor declares a signature that the kernel accepts', function (): void {
	$command = command(app());
	$signature = (new \ReflectionMethod($command, 'signature'))->invoke($command);

	same(ArgvParser::validateSignature($signature), [], 'Signature errors');
});

done();
