<?php

namespace Restruct\SilverStripe\AssetIcons\Renderable;

use SilverStripe\Assets\File;
use SilverStripe\Assets\FilenameParsing\AbstractFileIDHelper;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\DBFile;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;

/**
 * Extension on File that generates rendered PNG previews for non-image files
 * (PDFs, SVGs, EPS, etc.) using external CLI tools.
 *
 * Hooks into File::PreviewLink() so asset-admin grid/list views show actual
 * rendered thumbnails instead of generic file-type icons.
 *
 * Previews are stored as file variants in the AssetStore, so they respect
 * visibility (public/protected) and survive flush/deploy.
 *
 * Opt-in feature — enable via YAML:
 *   Restruct\SilverStripe\AssetIcons\Renderable\RenderablePreviewExtension:
 *     enable_renderable_previews: true
 *
 * @extends Extension<File>
 */
class RenderablePreviewExtension extends Extension implements Flushable
{
    use Configurable;

    const VARIANT_NAME = 'renderedpreview';

    /** @config Enable/disable the feature (default: off) */
    private static $enable_renderable_previews = false;

    /**
     * @config Map of file extension => renderer class
     * Override or extend in project YAML to add more formats.
     */
    private static $renderers = [
        'pdf'  => XpdfRenderer::class,        # bundled binary via xpdf-static, no system deps
        'eps'  => GhostscriptRenderer::class,  # requires system gs
        'ps'   => GhostscriptRenderer::class,
        'ai'   => GhostscriptRenderer::class,
        'svg'  => SvgRenderer::class,          # requires system rsvg-convert
        'svgz' => SvgRenderer::class,
    ];

    /** @config Target dimensions for the rendered preview PNG */
    private static $preview_width = 800;
    private static $preview_height = 800;

    /**
     * @config Max previews to generate per request — prevents timeouts
     * when opening a grid with many unrendered files for the first time.
     * Remaining files render on subsequent page loads.
     */
    private static $max_renders_per_request = 5;

    # Request-level caches (cleared on flush)
    private static $checked = [];
    private static $renderer_cache = [];
    private static $render_count = 0;

    /**
     * Hook into File::PreviewLink() — replace icon URL with rendered preview.
     */
    public function updatePreviewLink(&$link, $action = null)
    {
        $url = $this->getRenderedPreviewURL();
        if ($url) {
            $link = $url;
        }
    }

    /**
     * Get the rendered preview URL for this file, or null if not available.
     *
     * Standalone method so RenderableThumbnailGenerator can call it directly
     * from the GraphQL thumbnail resolution path (no fallback to icon).
     */
    public function getRenderedPreviewURL(): ?string
    {
        if (!static::config()->get('enable_renderable_previews')) {
            return null;
        }

        /** @var File $file */
        $file = $this->getOwner();

        # Images already have native thumbnails
        if ($file->getIsImage()) {
            return null;
        }

        $renderer = $this->getRendererForFile($file);
        if (!$renderer) {
            return null;
        }

        return $this->getOrCreatePreview($file, $renderer);
    }

    /**
     * Get the rendered preview as a DBFile, enabling image manipulation in templates.
     *
     * Template usage:
     *   $MyFile.RenderedPreview                  → <img> tag at full preview size
     *   $MyFile.RenderedPreview.URL              → just the URL
     *   $MyFile.RenderedPreview.ScaleWidth(300)  → resized <img> tag
     *
     * The variant uses ExtRewrite so files are stored with .png extension,
     * which is required for InterventionBackend to encode resized variants.
     *
     * @return DBFile|null DBFile with ImageManipulation support, or null
     */
    public function RenderedPreview(): ?DBFile
    {
        if (!static::config()->get('enable_renderable_previews')) {
            return null;
        }

        /** @var File $file */
        $file = $this->getOwner();

        # Images already have native thumbnails — use ScaleWidth etc. directly on those
        if ($file->getIsImage()) {
            return null;
        }

        $renderer = $this->getRendererForFile($file);
        if (!$renderer) {
            return null;
        }

        # Ensure the preview variant exists (generates on-demand if needed)
        $url = $this->getOrCreatePreview($file, $renderer);
        if (!$url) {
            return null;
        }

        # Return a DBFile pointing at the preview variant
        # Supports ScaleWidth(), FitMax(), Fill() etc. via ImageManipulation trait
        /** @var DBFile $dbFile */
        $dbFile = DBFile::create_field(DBFile::class, [
            'Filename' => $file->getFilename(),
            'Hash' => $file->getHash(),
            'Variant' => $this->buildVariantName($file),
        ]);

        return $dbFile;
    }

    /**
     * Get the rendered preview URL, generating it on-demand if needed.
     * This makes the feature retroactive — existing files get previews
     * the first time they appear in the asset-admin grid.
     */
    protected function getOrCreatePreview(File $file, RendererInterface $renderer): ?string
    {
        $store = $this->getAssetStore();
        $filename = $file->getFilename();
        $hash = $file->getHash();
        $variantName = $this->buildVariantName($file);

        # Bail for unsaved/broken file references
        if (!$filename || !$hash) {
            return null;
        }

        # Request-level cache — avoid duplicate store lookups for the same file
        $cacheKey = $filename . ':' . $hash;
        if (array_key_exists($cacheKey, static::$checked)) {
            return static::$checked[$cacheKey];
        }

        # Check if variant already exists in the store
        if ($store->exists($filename, $hash, $variantName)) {
            $url = $store->getAsURL($filename, $hash, $variantName, true);
            static::$checked[$cacheKey] = $url;
            return $url;
        }

        # Respect the per-request render limit to prevent timeouts
        $maxRenders = (int) static::config()->get('max_renders_per_request');
        if (static::$render_count >= $maxRenders) {
            static::$checked[$cacheKey] = null;
            return null;
        }

        # Generate the preview
        $url = $this->generatePreview($file, $renderer, $store);
        static::$checked[$cacheKey] = $url;
        return $url;
    }

    /**
     * Render the file to PNG and store it as a variant of the original.
     */
    protected function generatePreview(File $file, RendererInterface $renderer, AssetStore $store): ?string
    {
        # Stream the file from the store to a temp location for the CLI tool
        $stream = $file->getStream();
        if (!$stream) {
            return null;
        }

        $tempDir = sys_get_temp_dir();
        $tempInput = tempnam($tempDir, 'asseticon_in_');
        $tempOutput = tempnam($tempDir, 'asseticon_out_') . '.png';

        try {
            # Write stream to temp file
            $fp = fopen($tempInput, 'wb');
            stream_copy_to_stream($stream, $fp);
            fclose($fp);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $width = (int) static::config()->get('preview_width');
            $height = (int) static::config()->get('preview_height');

            if (!$renderer->render($tempInput, $tempOutput, $width, $height)) {
                return null;
            }

            # Store the PNG as a variant of the original file
            # Uses ExtRewrite variant naming so the file is stored with .png extension
            # (required for InterventionBackend to encode sub-variants correctly)
            $variantName = $this->buildVariantName($file);
            $result = $store->setFromLocalFile(
                $tempOutput,
                $file->getFilename(),
                $file->getHash(),
                $variantName,
                ['conflict' => AssetStore::CONFLICT_OVERWRITE]
            );

            if (!$result) {
                return null;
            }

            static::$render_count++;

            return $store->getAsURL(
                $file->getFilename(),
                $file->getHash(),
                $variantName,
                true # grant access for protected files
            );
        } finally {
            # Clean up temp files
            if (file_exists($tempInput)) {
                unlink($tempInput);
            }
            if (file_exists($tempOutput)) {
                unlink($tempOutput);
            }
            # tempnam creates a file without .png — clean that up too
            $tempOutputBase = preg_replace('/\.png$/', '', $tempOutput);
            if ($tempOutputBase !== $tempOutput && file_exists($tempOutputBase)) {
                unlink($tempOutputBase);
            }
        }
    }

    /**
     * Get the appropriate renderer for a file's extension.
     * Returns null if the extension isn't mapped or the tool isn't installed.
     */
    protected function getRendererForFile(File $file): ?RendererInterface
    {
        $extension = strtolower($file->getExtension());
        $renderers = static::config()->get('renderers');

        if (!isset($renderers[$extension])) {
            return null;
        }

        $rendererClass = $renderers[$extension];

        # Cache renderer availability per class — avoids repeated `which` calls
        if (!array_key_exists($rendererClass, static::$renderer_cache)) {
            /** @var RendererInterface $renderer */
            $renderer = Injector::inst()->get($rendererClass);
            static::$renderer_cache[$rendererClass] = $renderer->isAvailable() ? $renderer : false;
        }

        $cached = static::$renderer_cache[$rendererClass];
        return $cached ?: null;
    }

    /**
     * Build the full variant name including ExtRewrite for PNG storage.
     *
     * Uses SS's extension rewrite mechanism so the variant file is stored
     * with .png extension. Essential for image manipulation (ScaleWidth etc.)
     * because InterventionBackend::writeToStore() determines the encode
     * format from the URL extension — without ExtRewrite it tries to
     * encode as PDF/SVG/etc. which fails.
     */
    protected function buildVariantName(File $file): string
    {
        $originalExt = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $extRewrite = AbstractFileIDHelper::EXTENSION_REWRITE_VARIANT
            . Convert::base64url_encode([$originalExt, 'png']);

        return static::VARIANT_NAME . '_' . $extRewrite;
    }

    protected function getAssetStore(): AssetStore
    {
        return Injector::inst()->get(AssetStore::class);
    }

    /**
     * Flushable: reset request-level caches.
     */
    public static function flush()
    {
        static::$checked = [];
        static::$renderer_cache = [];
        static::$render_count = 0;
    }
}
