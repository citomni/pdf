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

namespace CitOmni\Pdf\Service;

use CitOmni\Kernel\Service\BaseService;
use CitOmni\Pdf\Support\Capabilities;
use CitOmni\Pdf\Util\PdfSignature;


/**
 * PdfReader: Service that reads the PDFs an app receives.
 *
 * The package's API for PDFs that come from outside the app: Uploads, mails and other
 * systems. The header and end-marker rule belongs to PdfSignature, and the capability report
 * to Support\Capabilities.
 *
 * Rules:
 * - Never return a PDF. Producing a PDF belongs to a writer, not to the reader.
 * - Take file paths, not file contents, so a large file never sits in PHP memory.
 * - Never parse an untrusted file without enforced limits on time, memory and size.
 */
class PdfReader extends BaseService {

	/**
	 * Tell whether a file is shaped like a PDF.
	 *
	 * The cheap first gate for uploads: It reads two small slices of the file and parses
	 * nothing.
	 *
	 * Behavior:
	 * - Reads the first PdfSignature::HEAD_BYTES bytes and, only when they hold a header, the
	 *   last PdfSignature::TAIL_BYTES bytes, or the whole file when it is shorter. Nothing else
	 *   is read.
	 * - True when PdfSignature::hasHeader() is true for the first slice and PdfSignature::hasEof()
	 *   for the second, so the end marker must lie wholly within the last TAIL_BYTES bytes. An
	 *   incremental update passes, because its newest end marker is last. An empty file is false.
	 * - Takes the size from the open file, so a file rewritten under the same path is read at
	 *   its current size.
	 *
	 * Notes:
	 * - A file that passes may still be damaged, locked or hostile.
	 * - For an upload, check the upload error first: PHP leaves tmp_name empty when an upload
	 *   fails, and an empty path throws.
	 *
	 * Typical usage:
	 *   if ($upload['error'] !== \UPLOAD_ERR_OK || !$this->app->pdfReader->looksLikePdf($upload['tmp_name'])) {
	 *       // Reject the upload: It failed, or it is not shaped like a PDF.
	 *   }
	 *
	 * @param  string  $path  Path of a regular, readable file.
	 * @return bool
	 * @throws \RuntimeException  When the path is not a readable regular file, or reading it fails.
	 * @see \CitOmni\Pdf\Util\PdfSignature  The rule.
	 */
	public function looksLikePdf(string $path): bool {
		if (!\is_file($path) || !\is_readable($path)) {
			throw new \RuntimeException('PDF file does not exist or is not readable: ' . $path);
		}

		$handle = @\fopen($path, 'rb');

		if ($handle === false) {
			throw new \RuntimeException('Failed to open PDF file: ' . $path);
		}

		try {
			// Without a read buffer, an 8-byte read is an 8-byte read, not 8 KiB.
			\stream_set_read_buffer($handle, 0);

			// fstat() of the open handle, not filesize(): The stat cache could hold an older size.
			$stat = \fstat($handle);

			if (!\is_array($stat)) {
				throw new \RuntimeException('Failed to read the size of PDF file: ' . $path);
			}

			$size = (int)$stat['size'];

			if ($size === 0) {
				return false;
			}

			if (!PdfSignature::hasHeader(self::readAt($handle, 0, \min(PdfSignature::HEAD_BYTES, $size), $path))) {
				return false;
			}

			$tail = self::readAt($handle, \max(0, $size - PdfSignature::TAIL_BYTES), \min(PdfSignature::TAIL_BYTES, $size), $path);
		} finally {
			\fclose($handle);
		}

		return PdfSignature::hasEof($tail);
	}


	/**
	 * Report what this host can do.
	 *
	 * Typical usage:
	 *   $operations = $this->app->pdfReader->capabilities()['operations'];
	 *
	 * @return array{process:bool,backends:array<string,array{available:bool,version:?string}>,operations:array<string,?string>}
	 * @see \CitOmni\Pdf\Support\Capabilities::report()  The fields of the report.
	 */
	public function capabilities(): array {
		return (new Capabilities($this->app))->report();
	}


	/**
	 * Read exactly the given number of bytes at an offset.
	 *
	 * @param  resource  $handle  Open, readable file handle.
	 * @param  int  $offset  Start offset, at least 0.
	 * @param  int  $length  Number of bytes, at least 1 and within the file.
	 * @param  string  $path  Path of the file, for the error message.
	 * @return string
	 * @throws \RuntimeException  When seeking or reading fails, or the file shrank while it was read.
	 */
	private static function readAt($handle, int $offset, int $length, string $path): string {
		if (\fseek($handle, $offset) !== 0) {
			throw new \RuntimeException('Failed to seek in PDF file: ' . $path);
		}

		$bytes = \fread($handle, $length);

		if ($bytes === false || \strlen($bytes) !== $length) {
			throw new \RuntimeException('Failed to read PDF file: ' . $path);
		}

		return $bytes;
	}


}
