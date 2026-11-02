<?php

namespace Restruct\AiBrowser;

use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - seeds the asset folder "Showcase" that the README screenshots show
 * (tests/browser/screenshots/readme.shot.ts): one file per common category, two renderable files
 * (a PDF and an SVG) for the rendered previews, and one raster image to show that images keep
 * core's own thumbnail. No spec looks at this folder. A DataObject only for
 * requireDefaultRecords(); it stores nothing. See AiBRecord for why this never loads in a real
 * install.
 *
 * Every dev/build empties the folder and writes the files again. The PDF and SVG carry no build
 * time (unlike AiBPreviewSeed), so their previews are rendered once per host and then reused.
 */
class AiBShowcaseSeed extends DataObject
{
    private static $table_name = 'AiBrowser_ShowcaseSeed';

    public const FOLDER = 'Showcase';

    /**
     * Non-renderable files: name => placeholder content. Only the extension matters for the icon;
     * their content is never read. Listed in the order the gallery sorts them (by title).
     */
    public const PLACEHOLDERS = [
        'brand-font.woff2' => 'showcase fixture',
        'budget-2026.xlsx' => 'showcase fixture',
        'contacts.csv' => "name,email\n",
        'database-backup.sql' => "-- showcase fixture\n",
        'floor-plan.dwg' => 'showcase fixture',
        'handbook.epub' => 'showcase fixture',
        'kick-off.pptx' => 'showcase fixture',
        'meeting-notes.docx' => 'showcase fixture',
        'photos.zip' => 'showcase fixture',
        'podcast-episode.mp3' => 'showcase fixture',
        'product-video.mp4' => 'showcase fixture',
        'settings.json' => "{}\n",
        'setup.exe' => 'showcase fixture',
    ];

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $folder = Folder::find_or_make(self::FOLDER);
        foreach (File::get()->filter('ParentID', $folder->ID) as $old) {
            $old->doArchive();
        }

        $files = self::PLACEHOLDERS + [
            'annual-report.pdf' => self::pdf(),
            'logo.svg' => self::svg(),
        ];
        foreach ($files as $name => $content) {
            $this->seed($folder, File::create(), $name, $content);
        }

        # One raster image, drawn with GD. The Files gallery never generates image thumbnails on
        # display (asset-admin config: "Generates: false"), so make the ones an upload makes.
        if (function_exists('imagecreatetruecolor')) {
            $image = $this->seed($folder, Image::create(), 'cover-photo.jpg', self::jpg());
            AssetAdmin::singleton()->generateThumbnails($image);
        }
    }

    private function seed(Folder $folder, File $file, string $name, string $content): File
    {
        $file->setFromString($content, self::FOLDER . '/' . $name);
        $file->ParentID = $folder->ID;
        $file->Title = $name;
        $file->write();
        $file->publishSingle();
        return $file;
    }

    /**
     * A one-page A5 "report" (title bar, text lines and a bar chart) with a correct xref table, so
     * any renderer reads it without repair. Built like AiBPreviewSeed::pdf().
     */
    public static function pdf(): string
    {
        $s = "0.15 0.35 0.65 rg 0 515 420 80 re f\n";
        $s .= "1 1 1 rg BT /F1 24 Tf 30 548 Td (Annual report) Tj ET\n";
        $s .= "0.2 0.2 0.2 rg BT /F1 11 Tf 30 480 Td (Highlights of the year) Tj ET\n";
        # Grey "text" lines.
        $s .= "0.8 0.82 0.85 rg\n";
        foreach ([455, 440, 425, 410, 395] as $i => $y) {
            $s .= sprintf("30 %d %d 6 re f\n", $y, $i === 4 ? 220 : 360);
        }
        # A bar chart.
        $s .= "0.2 0.2 0.2 rg BT /F1 11 Tf 30 350 Td (Results per quarter) Tj ET\n";
        $s .= "0.6 0.6 0.6 rg 30 120 360 1 re f\n";
        $colours = ['0.15 0.35 0.65', '0.25 0.55 0.8', '0.95 0.6 0.2', '0.3 0.65 0.4'];
        foreach ([120, 170, 140, 210] as $i => $h) {
            $s .= sprintf("%s rg %d 121 60 %d re f\n", $colours[$i], 50 + $i * 85, $h);
        }
        $s .= "0.8 0.82 0.85 rg 30 70 360 6 re f 30 55 280 6 re f\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 420 595] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($s) . " >>\nstream\n" . $s . 'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
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

    /** A neutral logo-like drawing, rendered to a preview by rsvg-convert. */
    public static function svg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
  <rect width="400" height="400" fill="#f4f1ea"/>
  <circle cx="200" cy="170" r="110" fill="#2a5aa6"/>
  <path d="M120 230 L200 90 L280 230 Z" fill="#f29a33"/>
  <circle cx="200" cy="185" r="32" fill="#f4f1ea"/>
  <rect x="90" y="315" width="220" height="22" rx="11" fill="#2a5aa6"/>
</svg>
SVG;
    }

    /** A soft gradient "photo" with a few shapes, as JPEG. */
    public static function jpg(): string
    {
        $w = 640;
        $h = 427;
        $gd = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($gd, (int) (90 + 120 * $t), (int) (150 + 60 * $t), (int) (210 - 40 * $t));
            imageline($gd, 0, $y, $w, $y, $c);
        }
        imagefilledellipse($gd, 220, 300, 260, 260, imagecolorallocate($gd, 0x3d, 0x6b, 0x4f));
        imagefilledellipse($gd, 440, 320, 300, 240, imagecolorallocate($gd, 0x2f, 0x5a, 0x42));
        imagefilledellipse($gd, 520, 90, 70, 70, imagecolorallocate($gd, 0xfd, 0xe6, 0x8a));
        ob_start();
        imagejpeg($gd, null, 85);
        imagedestroy($gd);
        return (string) ob_get_clean();
    }
}
