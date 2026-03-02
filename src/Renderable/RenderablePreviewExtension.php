<?php

namespace Restruct\SilverStripe\AssetIcons\Renderable;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Core\Config\Configurable;
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
        if (!static::config()->get('enable_renderable_previews')) {
            return;
        }

        /** @var File $file */
        $file = $this->getOwner();

        # Images already have native thumbnails via Image::PreviewLink()
        if ($file->getIsImage()) {
            return;
        }

        $renderer = $this->getRendererForFile($file);
        if (!$renderer) {
            return;
        }

        $url = $this->getOrCreatePreview($file, $renderer);
        if ($url) {
            $link = $url;
        }
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
        if ($store->exists($filename, $hash, static::VARIANT_NAME)) {
            $url = $store->getAsURL($filename, $hash, static::VARIANT_NAME, true);
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
            # Note: variant inherits original extension (.pdf) but contains PNG data.
            # JS uses fetch() HEAD to check existence; CSS background-image does
            # MIME sniffing and renders PNG bytes regardless of Content-Type header.
            $result = $store->setFromLocalFile(
                $tempOutput,
                $file->getFilename(),
                $file->getHash(),
                static::VARIANT_NAME,
                ['conflict' => AssetStore::CONFLICT_OVERWRITE]
            );

            if (!$result) {
                return null;
            }

            static::$render_count++;

            return $store->getAsURL(
                $file->getFilename(),
                $file->getHash(),
                static::VARIANT_NAME,
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
