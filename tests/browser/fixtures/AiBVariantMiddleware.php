<?php

namespace Restruct\AiBrowser;

use Restruct\SilverStripe\AssetIcons\Renderable\RenderablePreviewExtension;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Config;

/**
 * BROWSER-TEST FIXTURE ONLY - lets one spec see rendered previews without changing what every other
 * spec checks (previews off, the module default): a request carrying the cookie
 * aib-browser-variant=previews gets enable_renderable_previews = true for that request only, as if
 * a project had set it in YAML. Registered as a Director middleware by fixtures/_config/variant.yml.
 * See AiBRecord for why this never loads in a real install.
 */
class AiBVariantMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        if (Cookie::get('aib-browser-variant') === 'previews') {
            Config::modify()->set(RenderablePreviewExtension::class, 'enable_renderable_previews', true);
        }
        return $delegate($request);
    }
}
