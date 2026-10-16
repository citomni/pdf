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

namespace CitOmni\Pdf\Support;

use CitOmni\Kernel\App;
use CitOmni\Kernel\Cfg;
use CitOmni\Pdf\Enum\PdfOperation;
use CitOmni\Pdf\Exception\PdfConfigException;


/**
 * Capabilities: Support class with the capability report and the deploy check of citomni/pdf.
 *
 * Keeps what a host can do, and whether that covers what the app requires, in one place, so
 * every caller gets the same answer. Presenting the result belongs to the caller.
 *
 * Rules:
 * - Keep the report's shape: process, backends, and operations with every PdfOperation value
 *   as a key, so code written against it keeps working when backends are added.
 * - Never run a backend on a file to build the report or the check.
 * - Check cfg pdf where it is read, and report a wrong value with PdfConfigException.
 */
class Capabilities {

	/**
	 * Keep the app whose cfg holds pdf.doctor.
	 *
	 * @param  App  $app
	 */
	public function __construct(private readonly App $app) {
	}


	/**
	 * Report what this host can do.
	 *
	 * Behavior:
	 * - process is true when PHP may start processes.
	 * - backends lists every backend that the package ships, with its availability and version.
	 * - operations maps every PdfOperation value, in case order, to the backend that would run
	 *   it on this host, or to null.
	 *
	 * @return array{process:bool,backends:array<string,array{available:bool,version:?string}>,operations:array<string,?string>}
	 */
	public function report(): array {
		$operations = [];

		foreach (PdfOperation::cases() as $operation) {
			// No backend ships yet, so no operation can run.
			$operations[$operation->value] = null;
		}

		return [
			// A function listed in disable_functions is not defined at all, so this also covers
			// hosts that switched proc_open off.
			'process'    => \function_exists('proc_open'),
			'backends'   => [],
			'operations' => $operations,
		];
	}


	/**
	 * Check the report against the operations that cfg pdf.doctor.require demands.
	 *
	 * Behavior:
	 * - Reads and checks cfg pdf.doctor before it builds the report.
	 * - ok is true when every required operation has a backend. With nothing required, a host
	 *   without any backend passes.
	 * - required lists the operations in cfg order, without duplicates; missing lists those
	 *   of them without a backend.
	 *
	 * Typical usage:
	 *   $check = (new Capabilities($this->app))->check();
	 *
	 * @return array{ok:bool,report:array{process:bool,backends:array<string,array{available:bool,version:?string}>,operations:array<string,?string>},required:list<string>,missing:list<string>}  The report is that of report().
	 * @throws \CitOmni\Pdf\Exception\PdfConfigException  When cfg pdf or pdf.doctor is not a map, pdf.doctor holds another key than require, or require is not a list of PdfOperation values or cases.
	 * @throws \OutOfBoundsException  When cfg pdf, pdf.doctor or pdf.doctor.require is missing.
	 */
	public function check(): array {
		$required = $this->requiredOperations();
		$report   = $this->report();
		$missing  = [];

		foreach ($required as $operation) {
			if ($report['operations'][$operation] === null) {
				$missing[] = $operation;
			}
		}

		return [
			'ok'       => $missing === [],
			'report'   => $report,
			'required' => $required,
			'missing'  => $missing,
		];
	}


	/**
	 * Read and check cfg pdf.doctor.
	 *
	 * @return list<string>  PdfOperation values in cfg order, without duplicates.
	 * @throws \CitOmni\Pdf\Exception\PdfConfigException  When cfg pdf or pdf.doctor is not a map, pdf.doctor holds another key than require, or require is not a list of PdfOperation values or cases.
	 * @throws \OutOfBoundsException  When cfg pdf, pdf.doctor or pdf.doctor.require is missing.
	 */
	private function requiredOperations(): array {
		// Assumes that Cfg returns a non-empty list as a plain array and every other array as a
		// node, so a plain array here is a list and a node may hold a list or a map.
		$pdf = $this->app->cfg->pdf;

		if (!$pdf instanceof Cfg) {
			throw new PdfConfigException('pdf', 'must be a map, got ' . self::describe($pdf) . '.');
		}

		$doctor = $pdf->doctor;

		if (!$doctor instanceof Cfg) {
			throw new PdfConfigException('pdf.doctor', 'must be a map with the key require, got ' . self::describe($doctor) . '.');
		}

		// A misspelled key must fail: a check that silently requires nothing passes every deploy.
		$unknown = \array_diff(\array_keys($doctor->toArray()), ['require']);

		if ($unknown !== []) {
			throw new PdfConfigException('pdf.doctor', 'has an unknown key: ' . \implode(', ', $unknown) . '. The only key is require.');
		}

		$value = $doctor->require;

		if ($value instanceof Cfg) {
			$value = $value->toArray();
		}

		if (!\is_array($value) || !\array_is_list($value)) {
			throw new PdfConfigException('pdf.doctor.require', 'must be a list of operation names, got ' . (\is_array($value) ? 'a map' : \get_debug_type($value)) . '.');
		}

		$required = [];

		foreach ($value as $name) {
			if ($name instanceof PdfOperation) {
				$name = $name->value;
			}

			if (!\is_string($name) || PdfOperation::tryFrom($name) === null) {
				$known = \array_map(static fn(PdfOperation $operation): string => $operation->value, PdfOperation::cases());

				throw new PdfConfigException('pdf.doctor.require', 'contains an unknown operation: ' . (\is_string($name) ? '"' . $name . '"' : \get_debug_type($name)) . '. Known operations: ' . \implode(', ', $known) . '.');
			}

			$required[$name] = true;
		}

		return \array_keys($required);
	}


	/**
	 * Name the type of a cfg value that should have been a map, for an error message.
	 *
	 * @param  mixed  $value  A value that is not a Cfg node.
	 * @return string  "a list" for an array, the debug type otherwise.
	 */
	private static function describe(mixed $value): string {
		return \is_array($value) ? 'a list' : \get_debug_type($value);
	}


}
