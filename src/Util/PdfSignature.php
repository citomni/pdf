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

namespace CitOmni\Pdf\Util;


/**
 * PdfSignature: Util with the header and end-marker rule for PDF files.
 *
 * Keeps the cheap first gate for uploads in one place, so every caller decides the same way
 * whether bytes are shaped like a PDF. Reading the bytes belongs to the caller, and so does
 * any check of the structure: a match says nothing about whether the file is intact.
 *
 * Rules:
 * - Pure: Every result depends only on the arguments.
 */
class PdfSignature {

	/** Number of bytes from the start of a file that the header rule needs. */
	public const HEAD_BYTES = 8;

	/** Number of bytes from the end of a file that are searched for the end marker. */
	public const TAIL_BYTES = 1024;


	/**
	 * Tell whether the first bytes carry a PDF header.
	 *
	 * Behavior:
	 * - True only for %PDF-1.x or %PDF-2.x starting at byte 0. There is no offset tolerance,
	 *   so a BOM, a mail header or an HTTP response before the header fails.
	 *
	 * @param  string  $head  At least the first HEAD_BYTES bytes of the file.
	 * @return bool
	 */
	public static function hasHeader(string $head): bool {
		return \preg_match('/\A%PDF-[12]\.[0-9]/', $head) === 1;
	}


	/**
	 * Return the version that the header states.
	 *
	 * @param  string  $head  At least the first HEAD_BYTES bytes of the file.
	 * @return string|null  Version such as "1.7", or null when hasHeader() is false.
	 */
	public static function headerVersion(string $head): ?string {
		return \preg_match('/\A%PDF-([12]\.[0-9])/', $head, $m) === 1 ? $m[1] : null;
	}


	/**
	 * Tell whether the last bytes contain an end-of-file marker.
	 *
	 * Behavior:
	 * - True when "%%EOF" occurs anywhere in the bytes.
	 *
	 * @param  string  $tail  The last TAIL_BYTES bytes of the file, or the whole file when it is shorter.
	 * @return bool
	 */
	public static function hasEof(string $tail): bool {
		return \str_contains($tail, '%%EOF');
	}


	/**
	 * Apply both halves of the rule.
	 *
	 * Typical usage:
	 *   $ok = PdfSignature::matches($firstBytes, $lastBytes);
	 *
	 * @param  string  $head  At least the first HEAD_BYTES bytes of the file.
	 * @param  string  $tail  The last TAIL_BYTES bytes of the file, or the whole file when it is shorter.
	 * @return bool  True when hasHeader() and hasEof() are both true.
	 */
	public static function matches(string $head, string $tail): bool {
		return self::hasHeader($head) && self::hasEof($tail);
	}


}
