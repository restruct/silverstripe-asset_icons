<?php

namespace Restruct\AssetIcons\Tests;

use Restruct\AssetIcons\Tests\Stub\FakeRenderer;
use Restruct\AssetIcons\Tests\Stub\PdfFixture;
use Restruct\SilverStripe\AssetIcons\Renderable\RenderablePreviewExtension;
use Restruct\SilverStripe\AssetIcons\Renderable\RenderableThumbnailGenerator;
use SilverStripe\AssetAdmin\Model\ThumbnailGenerator;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * The GraphQL-era ThumbnailGenerator override, and - the part that silently broke on
 * Silverstripe 6 - that the service the asset admin ACTUALLY uses is ours on each major:
 * - asset-admin 2 (SS5): FileTypeResolver, via `ThumbnailGenerator.graphql`
 * - asset-admin 3 (SS6): AssetAdminOpen, via `ThumbnailGenerator.assetadminopen`
 */
class RenderableThumbnailGeneratorTest extends SapphireTest
{
    protected $usesDatabase = true;

    # Named as strings, not ::class imports: only one of the two exists per major.
    private const SS6_CONSUMER = 'SilverStripe\\AssetAdmin\\Controller\\AssetAdminOpen';
    private const SS5_CONSUMER = 'SilverStripe\\AssetAdmin\\GraphQL\\Resolvers\\FileTypeResolver';

    protected function setUp(): void
    {
        parent::setUp();
        TestAssetStore::activate('AssetIconsThumbTest');
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

    public function testAssetAdminUsesOurGenerator(): void
    {
        if (class_exists(self::SS6_CONSUMER)) {
            $service = ThumbnailGenerator::class . '.assetadminopen';
            $consumer = Injector::inst()->get(self::SS6_CONSUMER);
        } else {
            $service = ThumbnailGenerator::class . '.graphql';
            $consumer = Injector::inst()->get(self::SS5_CONSUMER);
        }

        $generator = Injector::inst()->get($service);
        $this->assertInstanceOf(RenderableThumbnailGenerator::class, $generator, "{$service} must be overridden");
        # Core defines this service with Generates: false (no thumbnail generation while browsing)
        $this->assertFalse((bool) $generator->getGenerates());

        $this->assertInstanceOf(
            RenderableThumbnailGenerator::class,
            $consumer->getThumbnailGenerator(),
            get_class($consumer) . ' must receive the overriding generator'
        );
    }

    public function testFallsBackToRenderedPreviewForNonImages(): void
    {
        Config::modify()->set(RenderablePreviewExtension::class, 'enable_renderable_previews', true);
        Config::modify()->set(RenderablePreviewExtension::class, 'renderers', ['pdf' => FakeRenderer::class]);

        $file = File::create();
        $file->setFromString(PdfFixture::minimal(), 'thumb.pdf');
        $file->write();

        $generator = Injector::inst()->create(RenderableThumbnailGenerator::class);
        $link = $generator->generateThumbnailLink($file, 100, 100);

        $this->assertNotNull($link);
        $this->assertSame($file->getRenderedPreviewURL(), $link);
    }

    public function testReturnsNullWhenPreviewsDisabled(): void
    {
        $file = File::create();
        $file->setFromString(PdfFixture::minimal(), 'off.pdf');
        $file->write();

        $this->assertNull(Injector::inst()->create(RenderableThumbnailGenerator::class)->generateThumbnailLink($file, 100, 100));
    }
}
