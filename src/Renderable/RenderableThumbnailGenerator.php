<?php

namespace Restruct\SilverStripe\AssetIcons\Renderable;

use SilverStripe\AssetAdmin\Model\ThumbnailGenerator;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Storage\AssetContainer;

/**
 * ThumbnailGenerator override for the named service the asset admin reads its
 * file list through: `.graphql` on Silverstripe 5 (asset-admin 2) and
 * `.assetadminopen` on Silverstripe 6 (asset-admin 3, which has no GraphQL).
 *
 * Falls back to rendered preview URLs (from RenderablePreviewExtension)
 * when the standard thumbnail logic returns null (i.e. for non-image files
 * like PDFs, SVGs, etc.).
 *
 * Registered via Injector for those two named services only (see
 * _config/renderable-previews.yml) - doesn't affect UploadField or other
 * ThumbnailGenerator consumers. Both registrations are needed: overriding only
 * `.graphql` leaves Silverstripe 6 silently without previews.
 *
 * @see RenderablePreviewExtension::getRenderedPreviewURL()
 */
class RenderableThumbnailGenerator extends ThumbnailGenerator
{
    /**
     * @param AssetContainer $file
     * @param int $width
     * @param int $height
     * @param bool $graceful
     * @return string|null
     */
    public function generateThumbnailLink(AssetContainer $file, $width, $height, $graceful = false)
    {
        # Standard behavior — works for images
        $result = parent::generateThumbnailLink($file, $width, $height, $graceful);
        if ($result !== null) {
            return $result;
        }

        # Fall back to rendered preview for non-image files (PDF, SVG, etc.)
        # Call on File directly — __call delegates to the extension with owner set
        if (!($file instanceof File) || !$file->hasExtension(RenderablePreviewExtension::class)) {
            return null;
        }

        return $file->getRenderedPreviewURL();
    }
}
