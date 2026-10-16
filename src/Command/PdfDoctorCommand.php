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

namespace CitOmni\Pdf\Command;

use CitOmni\Kernel\Command\BaseCommand;
use CitOmni\Pdf\Support\Capabilities;


/**
 * PdfDoctorCommand: CLI adapter that reports which PDF operations this host can run.
 *
 * Lets a deploy fail early on a host that cannot run what the app requires. It presents the
 * result; the report and the check belong to Support\Capabilities.
 *
 * Rules:
 * - Read only: Never change cfg, files or processes.
 * - With --json, write exactly one JSON document to stdout for every SUCCESS or FAILURE, and
 *   nothing else.
 */
class PdfDoctorCommand extends BaseCommand {

	/** Shown in place of a backend name when no backend can run an operation. */
	private const NONE = '-';


	/**
	 * Declare the command interface.
	 *
	 * @return array<string,array<string,mixed>>
	 * @see \CitOmni\Kernel\Command\BaseCommand::signature()  The signature format.
	 */
	protected function signature(): array {
		return [
			'options' => [
				'json' => [
					'short'       => 'j',
					'type'        => 'bool',
					'description' => 'Emit the check result as JSON',
				],
			],
		];
	}


	/**
	 * Print the capability report and the check against cfg pdf.doctor.require.
	 *
	 * Gives a person titled sections and a deploy script one JSON document, from the same
	 * check result.
	 *
	 * Behavior:
	 * - With --json: One JSON line on stdout with ok and a result that holds report, required
	 *   and missing of the check. ok is true when every required operation is available. The
	 *   backends map is a JSON object, also when it is empty.
	 * - Otherwise the sections citomni/pdf, Backends, Operations and Required by
	 *   pdf.doctor.require on stdout, then OK on stdout, or a FAILED line that names the
	 *   missing operations on stderr.
	 * - Exit code 0 when every required operation is available, also when nothing is
	 *   required; 1 otherwise.
	 *
	 * @return int  Process exit code.
	 * @throws \CitOmni\Pdf\Exception\PdfConfigException  When cfg pdf or pdf.doctor is invalid; nothing is printed.
	 * @throws \OutOfBoundsException  When cfg pdf, pdf.doctor or pdf.doctor.require is missing; nothing is printed.
	 * @see \CitOmni\Pdf\Support\Capabilities::check()  The check result.
	 */
	protected function execute(): int {
		$check = (new Capabilities($this->app))->check();

		if ($this->getBool('json')) {
			// The backend map stays a JSON object when it is empty, so its type never changes.
			$check['report']['backends'] = (object)$check['report']['backends'];

			$this->stdout(\json_encode([
				'ok'     => $check['ok'],
				'result' => [
					'report'   => $check['report'],
					'required' => $check['required'],
					'missing'  => $check['missing'],
				],
			], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR));
		} else {
			$this->renderHuman($check);
		}

		return $check['ok'] ? self::SUCCESS : self::FAILURE;
	}


	/**
	 * Print the check result as titled sections for a person.
	 *
	 * @param  array{ok:bool,report:array{process:bool,backends:array<string,array{available:bool,version:?string}>,operations:array<string,?string>},required:list<string>,missing:list<string>}  $check
	 * @return void
	 */
	private function renderHuman(array $check): void {
		$report = $check['report'];

		$this->writeSection('citomni/pdf', [
			'Processes' => $report['process'] ? 'yes' : 'no (proc_open is not available)',
		]);

		$backends = [];

		foreach ($report['backends'] as $name => $backend) {
			$backends[$name] = ($backend['available'] ? 'available' : 'not available') . ($backend['version'] !== null ? ', ' . $backend['version'] : '');
		}

		$this->writeSection('Backends', $backends);

		$operations = [];

		foreach ($report['operations'] as $operation => $backend) {
			$operations[$operation] = $backend ?? self::NONE;
		}

		$this->writeSection('Operations', $operations);

		$required = [];

		foreach ($check['required'] as $operation) {
			$required[$operation] = \in_array($operation, $check['missing'], true) ? 'missing' : 'ok';
		}

		$this->writeSection('Required by pdf.doctor.require', $required);

		if ($check['ok']) {
			$this->success('OK');

			return;
		}

		$count = \count($check['missing']);
		$this->error('FAILED: ' . $count . ' required ' . ($count === 1 ? 'operation is' : 'operations are') . ' not available: ' . \implode(', ', $check['missing']));
	}


	/**
	 * Print a title and its label-value rows, or "none" when there are no rows.
	 *
	 * @param  string  $title
	 * @param  array<string,string>  $rows  Value by label.
	 * @return void
	 */
	private function writeSection(string $title, array $rows): void {
		$this->stdout($title);

		if ($rows === []) {
			$this->stdout('  none');
			$this->stdout('');

			return;
		}

		$width = 0;

		foreach ($rows as $label => $_value) {
			$width = \max($width, \strlen((string)$label));
		}

		foreach ($rows as $label => $value) {
			$this->stdout('  ' . \str_pad((string)$label, $width + 2) . $value);
		}

		$this->stdout('');
	}


}
