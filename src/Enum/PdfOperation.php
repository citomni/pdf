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

namespace CitOmni\Pdf\Enum;


/**
 * PdfOperation: Enum of the operations that citomni/pdf defines.
 *
 * The one vocabulary for an operation, so the capability report, cfg pdf.doctor.require, logs
 * and adapters agree on it. Whether an operation can run on a host is a runtime capability
 * that Support\Capabilities reports.
 *
 * Rules:
 * - Never rename or remove a backing value: Apps write the values in cfg, logs and scripts
 *   read them.
 */
enum PdfOperation: string {

	/** Read the structure and metadata of a PDF without rendering it. */
	case Inspect = 'inspect';

	/** Render one page of a PDF to an image. */
	case Rasterize = 'rasterize';

	/** Extract the text of a PDF. */
	case Text = 'text';

	/** List and extract the files embedded in a PDF. */
	case Attachments = 'attachments';

	/** Generate a PDF from HTML. */
	case Html = 'html';

	/** Generate a PDF from a Typst template and data. */
	case Typst = 'typst';

	/** Build a PDF from existing PDFs: merge, split, overlay and rotate. */
	case Assemble = 'assemble';

	/** Write an unlocked copy of an encrypted PDF. */
	case Decrypt = 'decrypt';

}
