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

namespace CitOmni\Pdf\Exception;


/**
 * PdfConfigException: Exception for a pdf cfg value that the host app set wrongly.
 *
 * Marks a configuration error in the host app, not a runtime failure: Let it reach the global
 * error handler. Fixing the value belongs to the host app's cfg files.
 *
 * Rules:
 * - Name the cfg path of the invalid value, so the message and the log point at the value to fix.
 */
class PdfConfigException extends \RuntimeException {

	/**
	 * Build the message from the cfg path and what is wrong with its value.
	 *
	 * @param  string  $path  Cfg path of the invalid value, such as pdf.doctor.require.
	 * @param  string  $problem  What is wrong, phrased to follow the path, such as "must be a list of operation names, got string."
	 */
	public function __construct(public readonly string $path, string $problem) {
		parent::__construct('Cfg ' . $path . ' ' . $problem);
	}


}
