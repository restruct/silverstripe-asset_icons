<?php

namespace Restruct\AssetIcons\Tests;

use Restruct\AssetIcons\Tests\Stub\PdfFixture;
use Restruct\SilverStripe\AssetIcons\Renderable\GhostscriptRenderer;
use Restruct\SilverStripe\AssetIcons\Renderable\SvgRenderer;
use Restruct\SilverStripe\AssetIcons\Renderable\XpdfRenderer;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * The CLI renderers' contract: availability follows the configured binary, a missing binary
 * fails cleanly (false, no exception), and XpdfRenderer's render_dpi config changes the output.
 */
class RenderersTest extends SapphireTest
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function tempPath(string $suffix): string
    {
        $path = sys_get_temp_dir() . '/asseticons-test-' . bin2hex(random_bytes(6)) . $suffix;
        $this->tempFiles[] = $path;

        return $path;
    }

    public function testMissingBinaryIsUnavailableAndRenderFails(): void
    {
        foreach ([GhostscriptRenderer::class, SvgRenderer::class] as $class) {
            Config::modify()->set($class, 'binary_path', '/nonexistent/asseticons-no-such-binary');
            $renderer = Injector::inst()->create($class);

            $this->assertFalse($renderer->isAvailable(), "{$class} must report a missing binary");

            $in = $this->tempPath('.in');
            file_put_contents($in, 'x');
            $out = $this->tempPath('.png');
            $this->assertFalse($renderer->render($in, $out), "{$class} must fail cleanly without its binary");
            $this->assertFileDoesNotExist($out);
        }
    }

    public function testShellAvailableBinaryIsDetected(): void
    {
        # `sh` exists on every system that can run the suite; proves the positive branch
        Config::modify()->set(GhostscriptRenderer::class, 'binary_path', 'sh');
        $this->assertTrue(Injector::inst()->create(GhostscriptRenderer::class)->isAvailable());
    }

    public function testXpdfRenderDpiControlsOutputSize(): void
    {
        $pdf = $this->tempPath('.pdf');
        file_put_contents($pdf, PdfFixture::minimal(144, 72));

        $renderer = Injector::inst()->create(XpdfRenderer::class);
        $this->assertTrue($renderer->isAvailable());

        Config::modify()->set(XpdfRenderer::class, 'render_dpi', 72);
        $out72 = $this->tempPath('.png');
        $this->assertTrue($renderer->render($pdf, $out72));
        $this->assertFileExists($out72);

        Config::modify()->set(XpdfRenderer::class, 'render_dpi', 144);
        $out144 = $this->tempPath('.png');
        $this->assertTrue($renderer->render($pdf, $out144));

        [$w72] = getimagesize($out72);
        [$w144] = getimagesize($out144);
        # 144pt wide: 144px at 72 DPI, 288px at 144 DPI (allow xpdf's rounding)
        $this->assertEqualsWithDelta(144, $w72, 2);
        $this->assertEqualsWithDelta(288, $w144, 2);
    }

    public function testGhostscriptCropsEpsToItsBoundingBox(): void
    {
        $renderer = Injector::inst()->create(GhostscriptRenderer::class);
        if (!$renderer->isAvailable()) {
            # CI installs Ghostscript and sets ASSET_ICONS_REQUIRE_GS, so a missing gs there is a
            # failure rather than a skip that would let this case pass green without running
            if (getenv('ASSET_ICONS_REQUIRE_GS')) {
                $this->fail('ASSET_ICONS_REQUIRE_GS is set but Ghostscript (gs) is not available');
            }
            $this->markTestSkipped('Ghostscript (gs) is not installed');
        }

        # A 100x50 pt drawing (issue #6). Without -dEPSCrop gs renders it onto its default page
        # (US Letter, 1275x1650 px at 150 DPI) instead of the drawing's own BoundingBox.
        $eps = $this->tempPath('.eps');
        file_put_contents($eps, "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 100 50\n"
            . "0 0 1 setrgbcolor 0 0 100 50 rectfill\nshowpage\n");

        Config::modify()->set(GhostscriptRenderer::class, 'render_dpi', 150);
        $out = $this->tempPath('.png');
        $this->assertTrue($renderer->render($eps, $out));

        [$width, $height] = getimagesize($out);
        # 100x50 pt at 150 DPI: about 208x104 px (allow gs's rounding)
        $this->assertEqualsWithDelta(208, $width, 3, "EPS preview width {$width}px: not cropped to its BoundingBox");
        $this->assertEqualsWithDelta(104, $height, 3, "EPS preview height {$height}px: not cropped to its BoundingBox");
    }
}
