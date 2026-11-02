# Asset icons for Silverstripe

See at a glance what a file is in Silverstripe's Files section: every document gets a colour-coded
icon for its type (PDF, spreadsheet, presentation, archive, font, CAD drawing and more), in the tile
and table views, the edit panel and UploadFields. Optionally, PDFs, SVGs and EPS files get a
thumbnail rendered from the file itself. For Silverstripe 5 and 6.

![The Files section of the Silverstripe CMS in tile view: a PDF and an SVG shown as rendered previews with a small PDF/SVG badge, a JPG with its own thumbnail, and font, spreadsheet, database, CAD, e-book, presentation, Word and zip files each with their own coloured icon; the PDF is open in the edit panel with its rendered first page](docs/images/asset-admin-tiles.png)

*Maintained by [Restruct](https://github.com/restruct). If this module saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

## Out of the box, every document looks the same

Silverstripe generates thumbnails for images only. Every other file gets one of a handful of generic
pictures (document, archive, audio, video): a PDF, a spreadsheet, a slide deck and a Word file all
show the same grey document, and an editor has to read file names to find the one they want.

## An icon per file type, and a preview where one can be rendered

### Category icons in every view

The module maps about 190 file extensions to 17 categories, each with its own coloured icon, and
applies them in the asset admin's tile view, table view and edit panel, in the insert-media modal,
and on UploadField items in any CMS edit form. Any extension it does not know gets a neutral blank
icon. Images are left alone: Silverstripe makes real thumbnails for those.

![The Files section in table view: each row shows the file's category icon, the PDF and SVG rows a rendered preview with a PDF/SVG badge, and the JPG its own thumbnail](docs/images/asset-admin-table.png)

![An UploadField in a CMS edit form holding report.pdf, shown with the red PDF icon instead of Silverstripe's generic document image](docs/images/uploadfield.png)

The category icons work straight away after installation; no configuration is needed.

### Rendered preview thumbnails

For file types that can be rendered to images (PDFs, SVGs, EPS, etc.), the module generates **actual
PNG preview thumbnails** instead of showing a generic category icon. Previews appear in tile view,
table view, the edit panel and on UploadField items.

Previews are generated on-demand the first time a file appears in Asset Admin and stored as file
variants in the AssetStore. A small file-type badge (e.g. "PDF") is shown on the thumbnail in the
asset admin so users can distinguish previews from actual images.

**Enable in your project config:**

```yaml
Restruct\SilverStripe\AssetIcons\Renderable\RenderablePreviewExtension:
  enable_renderable_previews: true
```

**Configuration options:**

```yaml
Restruct\SilverStripe\AssetIcons\Renderable\RenderablePreviewExtension:
  enable_renderable_previews: true
  preview_width: 800           # target width for rendered PNG
  preview_height: 800          # target height for rendered PNG
  max_renders_per_request: 5   # prevents timeouts on first load
  renderers:                   # extension => renderer class
    pdf: Restruct\SilverStripe\AssetIcons\Renderable\XpdfRenderer
    eps: Restruct\SilverStripe\AssetIcons\Renderable\GhostscriptRenderer
    svg: Restruct\SilverStripe\AssetIcons\Renderable\SvgRenderer
```

All options live on `Restruct\SilverStripe\AssetIcons\Renderable\RenderablePreviewExtension`:

| Option | Default | Effect |
|--------|---------|--------|
| `enable_renderable_previews` | `false` | Master switch. Off: no previews are generated or returned anywhere |
| `preview_width` / `preview_height` | `800` / `800` | Bounds passed to the renderer (only the SVG renderer uses them, see the note below) |
| `max_renders_per_request` | `5` | Previews generated per request; the rest render on later page loads. Existing previews are always served |
| `renderers` | pdf, eps, ps, ai, svg, svgz | Map of lower-case file extension to renderer class. Remove or override an entry per extension |

The renderers have their own config:

| Class | Option | Default |
|-------|--------|---------|
| `XpdfRenderer` | `render_dpi` | `150` |
| `GhostscriptRenderer` | `binary_path` / `render_dpi` | `gs` / `150` |
| `SvgRenderer` | `binary_path` | `rsvg-convert` |

A renderer whose binary is not found is skipped: those files keep their category icon. To remove the
extension from `File` entirely:

```yaml
SilverStripe\Assets\File:
  extensions:
    RenderablePreview: null
```

**Supported formats and their requirements:**

| Format | Renderer | Requirement |
|--------|----------|-------------|
| PDF | XpdfRenderer | Bundled via `restruct/xpdf-static` - no system deps |
| EPS, PS, AI | GhostscriptRenderer | System `gs` (Ghostscript) |
| SVG, SVGZ | SvgRenderer | System `rsvg-convert` (librsvg) |

**Using previews in templates:**

The rendered preview is available as a `DBFile` on any `File` object, supporting all standard image
manipulation methods:

```html
<%-- Full-size preview as <img> tag --%>
$MyPDFFile.RenderedPreview

<%-- Resized preview --%>
$MyPDFFile.RenderedPreview.ScaleWidth(300)
$MyPDFFile.RenderedPreview.FitMax(400, 300)

<%-- Just the URL --%>
<img src="$MyPDFFile.RenderedPreview.URL" alt="Preview" />

<%-- Conditional --%>
<% if $MyPDFFile.RenderedPreview %>
    $MyPDFFile.RenderedPreview.ScaleWidth(200)
<% end_if %>
```

Returns `null` for images (use SilverStripe's native manipulation), unsupported formats, or when
previews are disabled.

From PHP, `$file->RenderedPreview()` returns the same `DBFile` (or `null`), and
`$file->getRenderedPreviewURL()` just the URL. Both generate the preview on first use.

> **Note on preview dimensions:** PDF and EPS renderers are DPI-based (default 150 DPI) and ignore
> the `preview_width`/`preview_height` config - the base preview is rendered at the document's
> native page size (e.g. ~1240x1753px for A4). The SVG renderer uses `preview_width`/`preview_height`
> as bounds.

**Known limitations:**

- Preview variants are stored as `.png` files (via SilverStripe's ExtRewrite variant naming),
  supporting full image manipulation. Upgrading from 2.2.x will regenerate previews on first access.
- Not tested with protected/draft files (the module assumes staging is removed).
- EPS files are rendered onto Ghostscript's default page size rather than the drawing's own
  bounding box, so a small drawing sits in the corner of a page-sized preview
  ([#6](https://github.com/restruct/silverstripe-asset_icons/issues/6)).

### 17 categories, about 190 extensions

![The icon overview screen: one card per category (blank, pdf, document, spreadsheet, presentation, archive, audio, video, code, markup, vector, image, cad, database, font, system, ebook, plc), each with its icon and a few of its extensions](docs/images/icon-overview.png)

| Category | Color | Extensions |
|----------|-------|------------|
| **pdf** | Red | pdf |
| **document** | Blue | doc, docx, docm, dotx, odt, ott, rtf, txt, md, pages, wpd, wps |
| **spreadsheet** | Green | xls, xlsx, xlsm, xltx, ods, ots, csv, tsv, numbers |
| **presentation** | Orange | ppt, pptx, pptm, potx, odp, otp, key |
| **archive** | Gold | zip, 7z, rar, tar, gz, tgz, bz2, xz, cab, jar, war, deb, rpm |
| **audio** | Purple | mp3, wav, aac, flac, ogg, opus, m4a, wma, aiff, aif, mid, midi |
| **video** | Magenta | mp4, avi, mov, mkv, webm, flv, wmv, mpg, mpeg, m4v, 3gp, ogv, vob |
| **code** | Teal | js, jsx, ts, tsx, php, py, rb, java, c, cpp, cs, go, rs, swift, sh, bat, ... |
| **markup** | Slate | html, htm, xml, xsl, xslt, json, yaml, yml, toml, ini, cfg, conf, env, css, scss, sass, less |
| **vector** | Indigo | ai, eps, svg |
| **image** | Cyan | psd, raw, cr2, cr3, nef, dng, tif, tiff, bmp, tga, xcf, indd, sketch |
| **cad** | Dark Green | dwg, dxf, vsd, vsdx, vdx, vst, skp, blend, 3ds, fbx, obj, stl, step, stp, iges, igs |
| **database** | Dark Blue | sql, db, sqlite, sqlite3, mdb, accdb, dbf, odb |
| **font** | Dark Purple | ttf, otf, woff, woff2, eot, pfb, pfm |
| **system** | Dark Gray | exe, dll, bin, iso, dmg, app, msi, sys, apk, ipa |
| **ebook** | Brown | epub, mobi, azw, azw3, cbz, cbr, djvu |
| **plc** | Industrial | e80, lsc, zap15_1, awl, gxw, acd, s7p, ap17-20, zap18-20, scl, udt, aml, tia, ... |

Any other extension gets the blank icon. The full map is `$ext-categories` in
`client/src/styles/asset-icons.scss`.

The icons only show for files Silverstripe accepts: which extensions can be uploaded at all is
core's `SilverStripe\Assets\File.allowed_extensions`, which this module does not change.

#### Icon preview screen

`/admin/asset-icons-preview` (CMS users with the `CMS_ACCESS_CMSMain` permission; not in the CMS
menu) shows every category icon with sample extensions, as in the screenshot above. Useful after
editing icons or colours.

### Adding extensions

Edit `client/src/styles/asset-icons.scss`:

```scss
$ext-categories: (
  // ... existing entries ...
  myext: document,    // map to existing category
);
```

Then rebuild: `npm run build` (see [Building the assets](#building-the-assets)).

### Adding categories

1. Create an SVG in `client/icons/` (use an existing one as template)
2. Add to `$category-icons` map in the SCSS
3. Map extensions to the new category in `$ext-categories`
4. Rebuild: `npm run build`

## Technical

### Version compatibility

`composer.json` is the source of truth; this table follows it.

| Branch | Module version | Silverstripe | PHP |
|--------|----------------|--------------|-----|
| `main` | `2.4.x` | `^5 \|\| ^6` | `^8.1` (Silverstripe 6: `^8.3`) |
| (tags only) | `2.0` - `2.3` | `^5` | `^8.1` (not declared; set by asset-admin 2) |
| (tags only) | `1.x` | `^4` | not declared; whatever Silverstripe 4 allows |

Silverstripe 4 reached end of life in April 2025 and is not supported by 2.x. `main` is the only
maintained line; a version branch is created only when a change cannot be made compatible across the
supported range.

### Requirements

- Silverstripe 5 or 6 (`silverstripe/asset-admin` `^2 || ^3`)
- PHP 8.1 or newer (Silverstripe 6 itself needs 8.3)
- `restruct/silverstripe-simpler` (provides DOMNodesInserted events): `^0 || ^1`. `1.x` supports
  Silverstripe 5 and 6 and is what Composer picks on a fresh install; Silverstripe 5 projects already
  on `0.x` can stay there
- `restruct/xpdf-static` (bundled PDF renderer, no system dependencies)
- Optional, for rendered previews of those formats: Ghostscript (`gs`) for EPS/PS/AI and librsvg
  (`rsvg-convert`) for SVG/SVGZ

### Installation

```bash
composer require restruct/silverstripe-asset_icons
```

Then flush (`?flush=1`, or `sake dev/build flush=1` on Silverstripe 5 / `sake db:build --flush` on
Silverstripe 6). The category icons work straight away; rendered previews are opt-in
([above](#rendered-preview-thumbnails)).

### How the icons are applied

1. **CSS** immediately applies category icons using SilverStripe's built-in classes
   (`gallery-item--document`, `gallery-item--archive`, etc.) - **no flash of default icons**
2. **JavaScript** reads React fiber data and sets `data-ext` attributes for more specific icons
   (e.g., PDF instead of generic document)
3. **SCSS** maps about 190 file extensions to 17 categories, each with a coloured SVG icon (18 icons
   with the blank fallback)
4. **External SVGs** are loaded on-demand and cached by the browser (~98KB CSS + ~34KB SVGs)

Regular images don't need icons - SilverStripe generates thumbnails for those. This module targets
document-category files only.

The stylesheet and script are added to every CMS page through `LeftAndMain`'s
`extra_requirements_css` / `extra_requirements_javascript` (`_config/config.yml`). The script runs on
the `DOMNodesInserted` events from `restruct/silverstripe-simpler`.

**Rendered previews, under the hood:**

- `RenderablePreviewExtension` (on `File`) generates PNG variants using CLI renderers (xpdf for PDFs,
  Ghostscript for EPS/PS/AI, rsvg-convert for SVGs)
- `RenderableThumbnailGenerator` overrides the `ThumbnailGenerator` service the asset admin reads its
  file list through, so the `thumbnail` field returns the preview URL for non-image files. That
  service is `ThumbnailGenerator.graphql` on Silverstripe 5 (asset-admin 2) and
  `ThumbnailGenerator.assetadminopen` on Silverstripe 6 (asset-admin 3, which has no GraphQL); the
  module overrides both, each keeping core's `Generates: false`
- UploadField items get the preview through `File::PreviewLink()`, which the extension's
  `updatePreviewLink()` replaces with the preview URL
- Since React's `GalleryItem` only applies `backgroundImage` for image-category files, the JS applies
  the preview as an inline style for non-image files that have a `thumbnail` value

### Building the assets

```bash
cd silverstripe-asset_icons
npm install
npm run build       # production build
```

After building, run `composer vendor-expose` to re-expose client assets.

### File structure

```
client/
  icons/              # 18 category SVG source files
  icons-source.svg    # Master Inkscape file with all icons
  src/
    js/               # Vanilla JS source (React fiber -> data-ext + preview)
    styles/           # SCSS source
  dist/
    icons/            # External SVG files (loaded on-demand, cached)
    js/               # Copied JS
    styles/           # Compiled CSS (~98KB)
src/
  Dev/                # IconsPreviewController (visit /admin/asset-icons-preview)
  Renderable/         # Rendered preview system
    RenderablePreviewExtension.php   # File extension: generates & stores variants
    RenderableThumbnailGenerator.php # Injector override for the asset admin thumbnails (.graphql on SS5, .assetadminopen on SS6)
    RendererInterface.php            # Contract for CLI renderers
    XpdfRenderer.php                 # PDF -> PNG (bundled binary)
    GhostscriptRenderer.php          # EPS/PS/AI -> PNG (system gs)
    SvgRenderer.php                  # SVG -> PNG (system rsvg-convert)
```

### Running the tests

The suite needs a Silverstripe project to boot. Install the module into one with a Composer path
repository and `"symlink": true` (`/tests` is `export-ignore`, so a dist install has no tests), then
run it with a manifest flush:

```bash
# Silverstripe 5 (PHPUnit 9): the test path must come BEFORE flush=1
vendor/bin/phpunit vendor/restruct/silverstripe-asset_icons/tests flush=1

# Silverstripe 6 (PHPUnit 11): flush via the environment
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit vendor/restruct/silverstripe-asset_icons/tests
```

`.github/workflows/ci.yml` builds exactly such a host project per Silverstripe major.

Browser tests (Playwright, against a real CMS on Silverstripe 5 and 6) live in `tests/browser/` and
run in `.github/workflows/browser-tests.yml`. The screenshots in this README are generated from the
same fixtures; see [tests/browser/screenshots/README.md](tests/browser/screenshots/README.md) to
regenerate them.

### Changelog

See [CHANGELOG.md](CHANGELOG.md).

### Credits

- Pictograms: [Bootstrap Icons](https://icons.getbootstrap.com/) (MIT)

### License

MIT, see [LICENSE](LICENSE).
