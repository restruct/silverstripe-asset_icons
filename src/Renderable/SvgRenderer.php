<?php

namespace Restruct\SilverStripe\AssetIcons\Renderable;

use SilverStripe\Core\Config\Configurable;

/**
 * Renders SVG and SVGZ files to PNG using rsvg-convert (from librsvg).
 */
class SvgRenderer implements RendererInterface
{
    use Configurable;

    /** @config */
    private static $binary_path = 'rsvg-convert';

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

        # Render SVG to PNG, preserving aspect ratio within the target dimensions
        $cmd = sprintf(
            '%s --width=%d --height=%d --keep-aspect-ratio --format=png --output=%s %s 2>&1',
            escapeshellarg($binary),
            $width,
            $height,
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );

        exec($cmd, $output, $returnCode);

        return $returnCode === 0 && file_exists($outputPath);
    }
}
