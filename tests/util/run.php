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
use CitOmni\Pdf\Util\PdfSignature;

/*
 * Isolated suite for the pure building blocks: the header and end-marker rule of
 * PdfSignature and the operation values of PdfOperation. No files are read or
 * written, and no kernel double is used.
 *
 * Usage:
 *   php tests/util/run.php
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


// -- 1. PdfSignature ---------------------------------------------

test('PdfSignature accepts %PDF-1.x and %PDF-2.x at byte 0', function (): void {
	foreach (['%PDF-1.0', '%PDF-1.4', '%PDF-1.7', '%PDF-2.0', "%PDF-1.7\n%\xE2\xE3\xCF\xD3"] as $head) {
		check(PdfSignature::hasHeader($head), 'Rejected: ' . export($head));
	}
});

test('PdfSignature rejects a header with an offset, a BOM, a wrong major version or wrong case', function (): void {
	$rejected = [
		" %PDF-1.7",              // Junk before the header: No offset tolerance
		"\xEF\xBB\xBF%PDF-1.7",   // UTF-8 BOM
		"\r\n%PDF-1.7",           // Line break first, as some mailers write it
		'%PDF-3.0',               // Unknown major version
		'%PDF-0.9',
		'%pdf-1.7',               // Case-sensitive
		'%PDF-1.',                // No minor digit
		'%PDF-1',
		'',
		'%!PS-Adobe-3.0',
	];

	foreach ($rejected as $head) {
		check(!PdfSignature::hasHeader($head), 'Accepted: ' . export($head));
	}
});

test('PdfSignature reads the header version', function (): void {
	same(PdfSignature::headerVersion('%PDF-1.7'), '1.7', 'Version of %PDF-1.7');
	same(PdfSignature::headerVersion("%PDF-2.0\n"), '2.0', 'Version of %PDF-2.0');
	same(PdfSignature::headerVersion(' %PDF-1.7'), null, 'Version with an offset');
	same(PdfSignature::headerVersion('%PDF-3.0'), null, 'Version of an unknown major');
});

test('PdfSignature finds the end marker anywhere in the tail', function (): void {
	check(PdfSignature::hasEof("startxref\n123\n%%EOF\n"), 'Marker before a final newline not found');
	check(PdfSignature::hasEof("startxref\n123\n%%EOF"), 'Marker at the very end not found');
	check(PdfSignature::hasEof("%%EOF\r\n" . \str_repeat(' ', 100)), 'Marker with trailing whitespace not found');
	check(!PdfSignature::hasEof("startxref\n123\n%%EO"), 'Cut-off marker found');
	check(!PdfSignature::hasEof(''), 'Marker found in an empty tail');
});

test('PdfSignature::matches() needs both the header and the end marker', function (): void {
	check(PdfSignature::matches('%PDF-1.7', "%%EOF\n"), 'Header and marker rejected');
	check(!PdfSignature::matches('%PDF-1.7', 'trailer'), 'Header without marker accepted');
	check(!PdfSignature::matches(' %PDF-1.7', "%%EOF\n"), 'Marker without header accepted');
});

test('PdfSignature slice sizes cover a full header and a 1 KiB tail', function (): void {
	same(PdfSignature::HEAD_BYTES, \strlen('%PDF-1.7'), 'HEAD_BYTES');
	same(PdfSignature::TAIL_BYTES, 1024, 'TAIL_BYTES');
});


// -- 2. PdfOperation ---------------------------------------------
// The values are compared as strings on purpose: They are public values in cfg and JSON, so a
// renamed or reordered case must fail here.

test('PdfOperation values are stable and in a fixed order', function (): void {
	$values = \array_map(static fn(PdfOperation $operation): string => $operation->value, PdfOperation::cases());

	same($values, ['inspect', 'rasterize', 'text', 'attachments', 'html', 'typst', 'assemble', 'decrypt'], 'Case values');
});

done();
