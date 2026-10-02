<?php

namespace Restruct\AiBrowser;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - every dev/build re-seeds the asset folder "aib-previews" with one real,
 * renderable PDF (sample.pdf), for the rendered-preview spec. Its content carries the build time, so
 * every run gets a new file hash and the preview is rendered afresh instead of found in the store
 * from an earlier run (which would hide a broken renderer). A DataObject only for
 * requireDefaultRecords(); it stores nothing. See AiBRecord for why this never loads in a real
 * install.
 */
class AiBPreviewSeed extends DataObject
{
    private static $table_name = 'AiBrowser_PreviewSeed';

    public const FOLDER = 'aib-previews';

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $folder = Folder::find_or_make(self::FOLDER);
        foreach (File::get()->filter('ParentID', $folder->ID) as $old) {
            $old->doArchive();
        }

        $file = File::create();
        $file->setFromString(self::pdf('Browser preview ' . date('c')), self::FOLDER . '/sample.pdf');
        $file->ParentID = $folder->ID;
        $file->Title = 'sample.pdf';
        $file->write();
        $file->publishSingle();
    }

    /**
     * A minimal one-page PDF (A6, a blue block and a line of text) with a correct xref table, so
     * any renderer reads it without repair.
     */
    public static function pdf(string $text): string
    {
        $stream = "0 0.3 0.8 rg 20 200 257 150 re f\nBT /F1 14 Tf 20 160 Td (" . addcslashes($text, '()\\') . ") Tj ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 297 420] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }
}
