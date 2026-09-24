<?php

namespace Restruct\AssetIcons\Tests\Stub;

use SilverStripe\Dev\TestOnly;

/**
 * Builds a minimal, valid one-page PDF in memory (with a correct xref table), so the suite
 * needs no binary fixture file and the real XpdfRenderer has something to render.
 */
class PdfFixture implements TestOnly
{
    /**
     * @param int $widthPt  page width in PDF points (1/72 inch)
     * @param int $heightPt page height in PDF points
     */
    public static function minimal(int $widthPt = 200, int $heightPt = 100): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$widthPt} {$heightPt}] /Contents 4 0 R /Resources << >> >>",
        ];
        # A filled rectangle, so the rendered page is not blank
        $stream = "0.8 0.1 0.1 rg 10 10 50 50 re f";
        $objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$body}\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF\n";

        return $pdf;
    }
}
