<?php

namespace Restruct\SilverStripe\AssetIcons\Renderable;

use Restruct\Xpdf\PdfToPng;
use SilverStripe\Core\Config\Configurable;

/**
 * Renders PDF files to PNG using restruct/xpdf-static (bundled pdftopng binary).
 *
 * Preferred over GhostscriptRenderer for PDFs because xpdf-static ships its
 * own pre-compiled binaries — no system-level installation required.
 */
class XpdfRenderer implements RendererInterface
{
    use Configurable;

    /** @config DPI for rendering — 150 is a good quality/size balance */
    private static $render_dpi = 150;

    public function isAvailable(): bool
    {
        return class_exists(PdfToPng::class);
    }

    public function render(string $inputPath, string $outputPath, int $width = 800, int $height = 800): bool
    {
        $dpi = (int) static::config()->get('render_dpi');

        # PdfToPng outputs to {pngRoot}-{pagenum}.png (e.g. /tmp/foo-000001.png)
        # Strip .png from outputPath to get the root prefix
        $pngRoot = preg_replace('/\.png$/i', '', $outputPath);

        # Render page 1 only
        $resultPath = PdfToPng::renderPage($inputPath, 1, $pngRoot, $dpi);

        if (!$resultPath || !file_exists($resultPath)) {
            return false;
        }

        # Rename xpdf's output (foo-000001.png) to the expected output path
        if ($resultPath !== $outputPath) {
            rename($resultPath, $outputPath);
        }

        return file_exists($outputPath);
    }
}
