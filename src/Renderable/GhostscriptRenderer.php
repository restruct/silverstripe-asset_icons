<?php

namespace Restruct\SilverStripe\AssetIcons\Renderable;

use SilverStripe\Core\Config\Configurable;

/**
 * Renders PDF, EPS, PS, and AI files to PNG using Ghostscript (gs CLI).
 *
 * Uses gs directly rather than Imagick PHP extension to avoid policy.xml
 * restrictions and reduce dependencies.
 */
class GhostscriptRenderer implements RendererInterface
{
    use Configurable;

    /** @config */
    private static $binary_path = 'gs';

    /** @config DPI for rendering — 150 is a good quality/size balance */
    private static $render_dpi = 150;

    public function isAvailable(): bool
    {
        $binary = static::config()->get('binary_path');
        $escaped = escapeshellarg($binary);
        exec("command -v {$escaped} 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }

    public function render(string $inputPath, string $outputPath, int $width = 800, int $height = 800): bool
    {
        $binary = static::config()->get('binary_path');
        $dpi = (int) static::config()->get('render_dpi');

        # Render first page only, with anti-aliasing for sharp text
        $cmd = sprintf(
            '%s -dNOPAUSE -dBATCH -dSAFER -dFirstPage=1 -dLastPage=1'
            . ' -sDEVICE=png16m -r%d -dTextAlphaBits=4 -dGraphicsAlphaBits=4'
            . ' -sOutputFile=%s %s 2>&1',
            escapeshellarg($binary),
            $dpi,
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );

        exec($cmd, $output, $returnCode);

        return $returnCode === 0 && file_exists($outputPath);
    }
}
