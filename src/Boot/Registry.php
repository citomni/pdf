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

namespace CitOmni\Pdf\Boot;


/**
 * Registry: Boot contributions of citomni/pdf.
 *
 * Declares what the package adds to a host app as constant arrays: services, a cfg baseline
 * and commands. Merging them with the host app's own maps, and deciding what wins, belongs to
 * the kernel.
 *
 * Rules:
 * - Constants only: No IO, no runtime branching and no environment reads.
 * - Ship a baseline for every cfg path the package reads, with a comment on what each key
 *   controls.
 * - Nothing in the package may need a process, Python or a binary of its own to boot, so a
 *   shared host without processes can install it.
 * - Keep names recognizable as the package's own: cfg under pdf and commands under pdf:.
 */
class Registry {

	/** Services for HTTP and CLI. */
	public const array MAP_COMMON = [
		'pdfReader' => \CitOmni\Pdf\Service\PdfReader::class,
	];

	// Services for one mode. Not used: pdfReader works in both modes.
	// public const array MAP_HTTP = [];
	// public const array MAP_CLI = [];


	/** Cfg baseline for both modes. */
	public const array CFG_COMMON = [
		'pdf' => [
			'doctor' => [
				'require' => [],  // Operations that pdf:doctor must find available, as PdfOperation values or cases; an empty list requires nothing.
			],
		],
	];

	// Not used: the package has no mode-specific cfg and no HTTP routes.
	// public const array CFG_HTTP = [];
	// public const array CFG_CLI = [];
	// public const array ROUTES_HTTP = [];


	/** CLI commands. */
	public const array COMMANDS_CLI = [
		'pdf:doctor' => [
			'command'     => \CitOmni\Pdf\Command\PdfDoctorCommand::class,
			'description' => 'Report which PDF operations this host can run.',
		],
	];


}
