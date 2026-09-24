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
}
