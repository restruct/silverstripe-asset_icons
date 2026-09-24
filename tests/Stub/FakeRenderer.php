<?php

namespace Restruct\AssetIcons\Tests\Stub;

use Restruct\SilverStripe\AssetIcons\Renderable\RendererInterface;
use SilverStripe\Dev\TestOnly;

/**
 * Deterministic stand-in for the CLI renderers: writes a real PNG with GD, so the rest of the
 * pipeline (variant storage, URL, image manipulation) runs for real without depending on gs,
 * rsvg-convert or xpdf being installed.
 *
 * Not a DataObject, so it is safe in a consumer's manifest (see SOP Phase 4 on test fixtures).
 */
class FakeRenderer implements RendererInterface, TestOnly
{
    # Plain public statics are NOT reset by SapphireTest between tests; every test class
    # using this stub resets them in setUp()/tearDown() via reset().
    public static int $calls = 0;

    public static bool $available = true;

    public static function reset(): void
    {
        static::$calls = 0;
        static::$available = true;
    }

    public function isAvailable(): bool
    {
        return static::$available;
    }

    public function render(string $inputPath, string $outputPath, int $width = 800, int $height = 800): bool
    {
        static::$calls++;

        # 80x40 so a later ScaleWidth() has something real to scale down
        $img = imagecreatetruecolor(80, 40);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        $ok = imagepng($img, $outputPath);
        imagedestroy($img);

        return $ok && file_exists($outputPath);
    }
}
