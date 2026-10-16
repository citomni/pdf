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

use CitOmni\Pdf\Enum\PdfOperation;
use CitOmni\Pdf\Service\PdfReader;
use CitOmni\Pdf\Tests\Support\Kernel\App;
use CitOmni\Pdf\Util\PdfSignature;

/*
 * Isolated suite for the pdfReader service: looksLikePdf() on real files,
 * including the slice boundaries and path errors, and the shape of
 * capabilities().
 *
 * The app double has no cfg and no services: Neither method reads either.
 * Files are written below one run directory under sys_get_temp_dir(), which
 * the suite removes before it prints its totals.
 *
 * Usage:
 *   php tests/reader/run.php
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

/**
 * Create the service the way the service map creates it.
 *
 * @return PdfReader
 */
function reader(): PdfReader {
	return new PdfReader(new App());
}


/**
 * Build a small, well-formed one-page PDF with a classic xref table.
 *
 * @return string
 */
function minimalPdf(): string {
	$objects = [
		1 => '<< /Type /Catalog /Pages 2 0 R >>',
		2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
		3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>',
	];

	$out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
	$offsets = [];

	foreach ($objects as $number => $body) {
		$offsets[$number] = \strlen($out);
		$out .= "{$number} 0 obj\n{$body}\nendobj\n";
	}

	$xref = \strlen($out);
	$out .= "xref\n0 4\n0000000000 65535 f \n";

	foreach ($offsets as $offset) {
		$out .= \sprintf("%010d 00000 n \n", $offset);
	}

	return $out . "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}


/**
 * Return the run directory of this suite; it exists once tempDir() has run.
 *
 * @return string
 */
function runDir(): string {
	static $dir = \sys_get_temp_dir() . '/citomni_pdf_reader_test_' . \bin2hex(\random_bytes(6));

	return $dir;
}


/**
 * Create an empty directory below the run directory.
 *
 * @return string  Its path.
 */
function tempDir(): string {
	static $count = 0;

	if ($count === 0) {
		\mkdir(runDir());
	}

	$dir = runDir() . '/' . ++$count;
	\mkdir($dir);

	return $dir;
}


/**
 * Write bytes to a new file in a directory of its own.
 *
 * @param  string  $bytes  File contents.
 * @param  string  $name  File name.
 * @return string  Its path.
 */
function tempPdf(string $bytes, string $name = 'input.pdf'): string {
	$path = tempDir() . '/' . $name;

	check(\file_put_contents($path, $bytes) === \strlen($bytes), 'Fixture not written: ' . $path);

	return $path;
}


/**
 * Remove the run directory with every directory and file that tempDir() and tempPdf() made.
 *
 * @return void
 */
function removeRunDir(): void {
	if (!\is_dir(runDir())) {
		return;
	}

	foreach (\glob(runDir() . '/*/*') ?: [] as $file) {
		\unlink($file);
	}

	foreach (\glob(runDir() . '/*') ?: [] as $dir) {
		\rmdir($dir);
	}

	\rmdir(runDir());
}


// -- 1. looksLikePdf() -------------------------------------------

test('looksLikePdf() accepts a well-formed PDF', function (): void {
	check(reader()->looksLikePdf(tempPdf(minimalPdf())), 'Minimal PDF rejected');
});

test('looksLikePdf() accepts an incremental update, where the newest end marker is last', function (): void {
	$updated = minimalPdf() . "4 0 obj\n<< /Producer (update) >>\nendobj\ntrailer\n<< /Size 5 /Root 1 0 R /Info 4 0 R >>\n%%EOF\n";

	same(\substr_count($updated, '%%EOF'), 2, 'End markers in the fixture');
	check(reader()->looksLikePdf(tempPdf($updated)), 'Incremental update rejected');
});

test('looksLikePdf() rejects junk before the header and a missing header', function (): void {
	$pdf = minimalPdf();

	check(!reader()->looksLikePdf(tempPdf(' ' . $pdf)), 'Accepted with one space before the header');
	check(!reader()->looksLikePdf(tempPdf("\xEF\xBB\xBF" . $pdf)), 'Accepted with a UTF-8 BOM before the header');
	check(!reader()->looksLikePdf(tempPdf("Content-Type: application/pdf\r\n\r\n" . $pdf)), 'Accepted with a mail header before the PDF');
	check(!reader()->looksLikePdf(tempPdf("\xFF\xD8\xFF\xE0" . \str_repeat("\0", 64) . "%%EOF\n", 'photo.pdf')), 'Accepted JPEG bytes named .pdf');
});

test('looksLikePdf() rejects a file without an end marker', function (): void {
	$pdf = minimalPdf();
	$cut = \substr($pdf, 0, (int)\strrpos($pdf, '%%EOF'));

	check(!reader()->looksLikePdf(tempPdf($cut)), 'Accepted a file cut off before %%EOF');
});

test('looksLikePdf() searches exactly the last TAIL_BYTES bytes for the end marker', function (): void {
	$pdf = minimalPdf();
	$tail = PdfSignature::TAIL_BYTES;

	// The marker plus its newline is 6 bytes, so this padding puts the "%" at the first byte of the tail.
	check(reader()->looksLikePdf(tempPdf($pdf . \str_repeat(' ', $tail - 6))), 'Rejected with the marker as the first bytes of the tail');

	// One byte more moves the "%" out of the tail.
	check(!reader()->looksLikePdf(tempPdf($pdf . \str_repeat(' ', $tail - 5))), 'Accepted with the marker straddling the tail start');
	check(!reader()->looksLikePdf(tempPdf($pdf . \str_repeat("\n", $tail + 100))), 'Accepted with more than TAIL_BYTES bytes after the marker');
});

test('looksLikePdf() reads a file rewritten under the same path at its new size', function (): void {
	$path = tempPdf('%PDF-1.7 short');

	check(!reader()->looksLikePdf($path), 'Accepted the first version, which has no end marker');

	// A longer rewrite: A size from the stat cache would cut the tail short.
	\file_put_contents($path, minimalPdf() . \str_repeat(' ', 500));
	check(reader()->looksLikePdf($path), 'Rejected the rewritten version');
});

test('looksLikePdf() handles files shorter than the slices', function (): void {
	check(reader()->looksLikePdf(tempPdf("%PDF-1.4\n%%EOF")), 'Rejected a 14-byte file with header and marker');
	check(!reader()->looksLikePdf(tempPdf('%PDF-1.')), 'Accepted a file shorter than HEAD_BYTES');
	check(!reader()->looksLikePdf(tempPdf('')), 'Accepted an empty file');
});

test('looksLikePdf() throws a RuntimeException for a missing file and a directory', function (): void {
	$dir = tempDir();

	throws(\RuntimeException::class, fn() => reader()->looksLikePdf($dir . '/missing.pdf'));
	throws(\RuntimeException::class, fn() => reader()->looksLikePdf($dir));
});

$unreadable = 'looksLikePdf() throws a RuntimeException for a file it may not read';

if (\PHP_OS_FAMILY === 'Windows' || !\function_exists('posix_geteuid') || \posix_geteuid() === 0) {
	skip($unreadable, 'needs a POSIX user other than root, with ext-posix; root reads every file, and Windows ignores the mode bits');
} else {
	test($unreadable, function (): void {
		$path = tempPdf(minimalPdf());
		\chmod($path, 0000);

		try {
			throws(\RuntimeException::class, fn() => reader()->looksLikePdf($path));
		} finally {
			\chmod($path, 0644);
		}
	});
}


// -- 2. capabilities() -------------------------------------------

test('capabilities() reports process support, no backends and every operation without a backend', function (): void {
	$report = reader()->capabilities();

	same(\array_keys($report), ['process', 'backends', 'operations'], 'Report keys');
	same($report['process'], \function_exists('proc_open'), 'process');
	same($report['backends'], [], 'backends');

	$expected = [];

	foreach (PdfOperation::cases() as $operation) {
		$expected[$operation->value] = null;
	}

	same($report['operations'], $expected, 'operations');
});

removeRunDir();
done();
