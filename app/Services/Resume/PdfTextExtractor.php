<?php

declare(strict_types=1);

namespace App\Services\Resume;

use Smalot\PdfParser\Parser;

/**
 * Extracts raw text from an uploaded PDF resume using smalot/pdfparser —
 * pure PHP, so it works on any host without a poppler/pdftotext binary.
 *
 * Extraction quality is whatever the PDF's text layer provides; scanned
 * image-only PDFs yield an empty string, which downstream code treats the
 * same as "nothing could be extracted" (no OCR in this phase).
 */
class PdfTextExtractor
{
    /**
     * @throws \Exception When the file is not a readable PDF.
     */
    public function extract(string $contents): string
    {
        $text = (new Parser)->parseContent($contents)->getText();

        // Collapse the parser's layout artifacts (repeated whitespace,
        // stray tabs) so the LLM gets clean, compact input.
        return trim((string) preg_replace('/[ \t]{2,}/', ' ', $text));
    }
}
