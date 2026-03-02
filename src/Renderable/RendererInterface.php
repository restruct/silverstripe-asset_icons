<?php

namespace Restruct\SilverStripe\AssetIcons\Renderable;

/**
 * Contract for external tools that render non-image files to PNG previews.
 */
interface RendererInterface
{
    /**
     * Check if the required external tool (gs, rsvg-convert, etc.) is available.
     */
    public function isAvailable(): bool;

    /**
     * Render the first page/view of the input file to a PNG.
     *
     * @param string $inputPath Absolute path to the source file
     * @param string $outputPath Absolute path for the output PNG
     * @param int $width Target width in pixels
     * @param int $height Target height in pixels
     * @return bool True on success
     */
    public function render(string $inputPath, string $outputPath, int $width = 800, int $height = 800): bool;
}
