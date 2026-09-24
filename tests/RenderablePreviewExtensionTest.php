<?php

namespace Restruct\AssetIcons\Tests;

use Restruct\AssetIcons\Tests\Stub\FakeRenderer;
use Restruct\AssetIcons\Tests\Stub\PdfFixture;
use Restruct\SilverStripe\AssetIcons\Renderable\GhostscriptRenderer;
use Restruct\SilverStripe\AssetIcons\Renderable\RenderablePreviewExtension;
use Restruct\SilverStripe\AssetIcons\Renderable\XpdfRenderer;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\DBFile;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Behavioural tests for RenderablePreviewExtension: the opt-in switch, which files get a
 * preview, the per-request render cap, and the stored PNG variant that templates manipulate.
 *
 * Most tests route PDFs to FakeRenderer so the pipeline is exercised without external tools;
 * testXpdfRendererEndToEnd runs the real bundled renderer.
 *
 * Compatibility note: runs under PHPUnit 9 (Silverstripe 5) and PHPUnit 11 (Silverstripe 6).
 * Keep it free of doc-comment metadata and version-specific test methods, so the test count is
 * the same on both majors.
 */
class RenderablePreviewExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        TestAssetStore::activate('AssetIconsPreviewTest');
        # Request-level caches on the extension are plain statics: reset them per test
        RenderablePreviewExtension::flush();
        FakeRenderer::reset();
        $this->logInWithPermission('ADMIN');
    }

    protected function tearDown(): void
    {
        RenderablePreviewExtension::flush();
        FakeRenderer::reset();
        TestAssetStore::reset();
        parent::tearDown();
    }

    private function enable(array $renderers = ['pdf' => FakeRenderer::class]): void
    {
        Config::modify()->set(RenderablePreviewExtension::class, 'enable_renderable_previews', true);
        Config::modify()->set(RenderablePreviewExtension::class, 'renderers', $renderers);
    }

    private function makeFile(string $name, string $content, string $class = File::class): File
    {
        /** @var File $file */
        $file = $class::create();
        $file->setFromString($content, $name);
        $file->write();

        return $file;
    }

    private function makePng(string $name): Image
    {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $this->makeFile($name, $png, Image::class);
    }

    public function testExtensionIsAppliedToFile(): void
    {
        $this->assertTrue(File::has_extension(RenderablePreviewExtension::class));
        # Config defaults: off, with the documented renderer map
        $this->assertFalse(Config::inst()->get(RenderablePreviewExtension::class, 'enable_renderable_previews'));
        $renderers = Config::inst()->get(RenderablePreviewExtension::class, 'renderers');
        $this->assertSame(XpdfRenderer::class, $renderers['pdf']);
        $this->assertSame(GhostscriptRenderer::class, $renderers['eps']);
    }

    /**
     * Regression: the extension was registered twice (once unnamed in config.yml, once as
     * `RenderablePreview` in renderable-previews.yml), so `RenderablePreview: null` could not
     * remove it.
     */
    public function testExtensionIsRegisteredOnceAndRemovableByName(): void
    {
        $matches = array_filter(
            File::get_extensions(),
            fn ($ext) => $ext === RenderablePreviewExtension::class
        );
        $this->assertCount(1, $matches, 'RenderablePreviewExtension must be registered exactly once on File');

        Config::modify()->merge(File::class, 'extensions', ['RenderablePreview' => null]);
        $this->assertNotContains(RenderablePreviewExtension::class, File::get_extensions());
    }

    public function testDisabledByDefault(): void
    {
        Config::modify()->set(RenderablePreviewExtension::class, 'renderers', ['pdf' => FakeRenderer::class]);
        $file = $this->makeFile('disabled.pdf', PdfFixture::minimal());

        $this->assertNull($file->getRenderedPreviewURL());
        $this->assertNull($file->RenderedPreview());
        # PreviewLink falls through to the stock file-type icon
        $this->assertSame($file->getIcon(), $file->PreviewLink());
        $this->assertSame(0, FakeRenderer::$calls);
    }

    public function testEnabledGeneratesPngVariant(): void
    {
        $this->enable();
        $file = $this->makeFile('enabled.pdf', PdfFixture::minimal());

        $url = $file->getRenderedPreviewURL();
        $this->assertNotNull($url);
        $this->assertStringContainsString(RenderablePreviewExtension::VARIANT_NAME, $url);
        $this->assertStringEndsWith('.png', parse_url($url, PHP_URL_PATH));
        $this->assertSame(1, FakeRenderer::$calls);

        # updatePreviewLink swaps the icon for the preview
        $this->assertSame($url, $file->PreviewLink());
    }

    public function testExistingVariantIsReusedNotReRendered(): void
    {
        $this->enable();
        $file = $this->makeFile('reuse.pdf', PdfFixture::minimal());
        $first = $file->getRenderedPreviewURL();

        # A new request: request cache cleared, the stored variant must be found in the store
        RenderablePreviewExtension::flush();
        $second = $file->getRenderedPreviewURL();

        $this->assertSame($first, $second);
        $this->assertSame(1, FakeRenderer::$calls);
    }

    public function testImagesAndUnmappedExtensionsGetNoPreview(): void
    {
        # png IS mapped here, so it is the image guard - not a missing renderer - that must skip it
        $this->enable(['pdf' => FakeRenderer::class, 'png' => FakeRenderer::class]);
        $image = $this->makePng('photo.png');
        $text = $this->makeFile('notes.txt', 'hello');

        $this->assertNull($image->getRenderedPreviewURL());
        $this->assertNull($image->RenderedPreview());
        $this->assertNull($text->getRenderedPreviewURL());
        $this->assertSame(0, FakeRenderer::$calls);
    }

    public function testUnavailableRendererGivesNoPreview(): void
    {
        FakeRenderer::$available = false;
        $this->enable();
        $file = $this->makeFile('norenderer.pdf', PdfFixture::minimal());

        $this->assertNull($file->getRenderedPreviewURL());
        $this->assertSame(0, FakeRenderer::$calls);
    }

    public function testMaxRendersPerRequestIsHonoured(): void
    {
        $this->enable();
        Config::modify()->set(RenderablePreviewExtension::class, 'max_renders_per_request', 1);
        $a = $this->makeFile('a.pdf', PdfFixture::minimal(200, 100));
        $b = $this->makeFile('b.pdf', PdfFixture::minimal(300, 100));

        $this->assertNotNull($a->getRenderedPreviewURL());
        $this->assertNull($b->getRenderedPreviewURL(), 'second render in the same request must be deferred');
        $this->assertSame(1, FakeRenderer::$calls);

        # Next request: the deferred file renders
        RenderablePreviewExtension::flush();
        $this->assertNotNull($b->getRenderedPreviewURL());
        $this->assertSame(2, FakeRenderer::$calls);
    }

    public function testRenderedPreviewIsManipulableDbFile(): void
    {
        $this->enable();
        $file = $this->makeFile('manip.pdf', PdfFixture::minimal());

        $preview = $file->RenderedPreview();
        $this->assertInstanceOf(DBFile::class, $preview);
        $this->assertTrue($preview->exists());
        $this->assertStringEndsWith('.png', parse_url($preview->getURL(), PHP_URL_PATH));
        $this->assertSame(80, (int) $preview->getWidth());

        $scaled = $preview->ScaleWidth(20);
        $this->assertNotNull($scaled, 'ScaleWidth() on the preview must produce a variant');
        $this->assertSame(20, (int) $scaled->getWidth());
    }

    public function testXpdfRendererEndToEnd(): void
    {
        # The default renderer map (pdf => XpdfRenderer), real bundled binary
        Config::modify()->set(RenderablePreviewExtension::class, 'enable_renderable_previews', true);
        # Unique content (height 99): InterventionBackend caches image dimensions by file HASH and
        # variant, not by filename, so reusing another test's PDF bytes would read that test's
        # FakeRenderer dimensions from the cache.
        $file = $this->makeFile('real.pdf', PdfFixture::minimal(200, 99));

        $preview = $file->RenderedPreview();
        $this->assertNotNull($preview, 'XpdfRenderer must produce a preview for a valid PDF');
        # 200pt at the default 150 DPI = 417px (xpdf rounds up)
        $this->assertEqualsWithDelta(417, (int) $preview->getWidth(), 2);
    }
}
