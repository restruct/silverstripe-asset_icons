<?php

namespace Restruct\AssetIcons\Tests;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\FunctionalTest;

/**
 * Real CMS requests: the module's JS/CSS reach the asset admin, and the icon preview screen
 * renders every category with an icon file that exists.
 */
class AdminIntegrationTest extends FunctionalTest
{
    protected $usesDatabase = true;

    private const MODULE = 'restruct/silverstripe-asset_icons';

    # One per category in IconsPreviewController::CATEGORIES and client/icons/
    private const CATEGORY_COUNT = 18;

    public function testClientResourcesAreConfiguredAndExist(): void
    {
        $module = ModuleLoader::getModule(self::MODULE);
        $this->assertNotNull($module, 'module must be in the manifest');

        $js = Config::inst()->get(LeftAndMain::class, 'extra_requirements_javascript');
        $css = Config::inst()->get(LeftAndMain::class, 'extra_requirements_css');
        $this->assertContains(self::MODULE . ':client/dist/js/asset-icons.js', $js);
        $this->assertContains(self::MODULE . ':client/dist/styles/asset-icons.css', $css);

        foreach (['client/dist/js/asset-icons.js', 'client/dist/styles/asset-icons.css'] as $path) {
            $this->assertTrue($module->getResource($path)->exists(), "{$path} must ship with the module");
        }
    }

    public function testAssetAdminPageLoadsModuleAssets(): void
    {
        $this->logInWithPermission('ADMIN');
        $response = $this->get('admin/assets');

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('asset-icons.js', $body);
        $this->assertStringContainsString('asset-icons.css', $body);
        # The DOMNodesInserted events the JS listens for come from silverstripe-simpler
        $this->assertStringContainsString('simpler-silverstripe.js', $body);
    }

    public function testIconsPreviewScreenRendersEveryCategory(): void
    {
        $this->logInWithPermission('ADMIN');
        $response = $this->get('admin/asset-icons-preview');

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertSame(self::CATEGORY_COUNT, substr_count($body, 'class="icon-card"'));

        # Every card's icon must resolve to a file the module ships
        preg_match_all('#<img src="[^"]*/client/icons/([a-z]+)\.svg[^"]*" alt="\1">#', $body, $m);
        $this->assertCount(self::CATEGORY_COUNT, $m[1], 'each card must reference client/icons/<category>.svg');
        $module = ModuleLoader::getModule(self::MODULE);
        foreach ($m[1] as $category) {
            $this->assertTrue(
                $module->getResource("client/icons/{$category}.svg")->exists(),
                "client/icons/{$category}.svg is missing"
            );
            $this->assertTrue(
                $module->getResource("client/dist/icons/{$category}.svg")->exists(),
                "client/dist/icons/{$category}.svg is missing"
            );
        }
    }

    public function testIconsPreviewScreenRequiresCmsAccess(): void
    {
        $this->logOut();
        # Look at the access decision itself, not at the login page it redirects to
        $this->autoFollowRedirection = false;
        $response = $this->get('admin/asset-icons-preview');

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('icon-card', (string) $response->getBody());
    }
}
