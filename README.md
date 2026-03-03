# SilverStripe Asset Icons

Replaces the default document thumbnails in SilverStripe's Asset Admin with **category-colored SVG icons** and **rendered preview thumbnails**. Works in AssetAdmin (grid & list view, edit panel) and UploadField modals. Handles any file extension automatically.

![](docs/asset-admin-icons.png)

## Requirements

- SilverStripe 5 (`silverstripe/asset-admin: ~2.0`)
- `restruct/silverstripe-simpler` (provides DOMNodesInserted events)
- `restruct/xpdf-static` (bundled PDF renderer, no system dependencies)

## How it works

### Category icons

1. **CSS** immediately applies category icons using SilverStripe's built-in classes (`gallery-item--document`, `gallery-item--archive`, etc.) — **no flash of default icons**
2. **JavaScript** reads React fiber data and sets `data-ext` attributes for more specific icons (e.g., PDF instead of generic document)
3. **SCSS** maps ~170 file extensions to 18 categories, each with a colored SVG icon
4. **External SVGs** are loaded on-demand and cached by the browser (76KB CSS + ~31KB SVGs)

Regular images don't need icons — SilverStripe generates thumbnails for those. This module targets document-category files only.

### Rendered preview thumbnails

For file types that can be rendered to images (PDFs, SVGs, EPS, etc.), the module generates **actual PNG preview thumbnails** instead of showing a generic category icon. Previews appear in tile view, table view, and the edit panel.

Previews are generated on-demand the first time a file appears in Asset Admin and stored as file variants in the AssetStore. A small file-type badge (e.g. "PDF") is shown on the thumbnail so users can distinguish previews from actual images.

**How it works under the hood:**

- `RenderablePreviewExtension` (on `File`) generates PNG variants using CLI renderers (xpdf for PDFs, Ghostscript for EPS/PS/AI, rsvg-convert for SVGs)
- `RenderableThumbnailGenerator` overrides the GraphQL-specific `ThumbnailGenerator` service via Injector, so the `thumbnail` field returns the preview URL for non-image files
- Since React's `GalleryItem` only applies `backgroundImage` for image-category files, the JS applies the preview as an inline style for non-image files that have a `thumbnail` value

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

**Supported formats and their requirements:**

| Format | Renderer | Requirement |
|--------|----------|-------------|
| PDF | XpdfRenderer | Bundled via `restruct/xpdf-static` — no system deps |
| EPS, PS, AI | GhostscriptRenderer | System `gs` (Ghostscript) |
| SVG, SVGZ | SvgRenderer | System `rsvg-convert` (librsvg) |

**Known limitations:**

- Preview variants inherit the original file extension (e.g. `.pdf`) but contain PNG data. Browsers handle this via MIME sniffing.
- Not tested with protected/draft files (the module assumes staging is removed).

## Categories

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
| **markup** | Slate | html, htm, xml, json, yaml, yml, toml, ini, cfg, css, scss, sass, less |
| **vector** | Indigo | ai, eps, svg |
| **image** | Cyan | psd, raw, cr2, cr3, nef, dng, tif, tiff, bmp, tga, xcf, indd, sketch |
| **cad** | Dark Green | dwg, dxf, vsd, vsdx, vdx, vst, skp, blend, 3ds, fbx, obj, stl, step, stp, iges |
| **database** | Dark Blue | sql, db, sqlite, sqlite3, mdb, accdb, dbf, odb |
| **font** | Dark Purple | ttf, otf, woff, woff2, eot, pfb, pfm |
| **system** | Dark Gray | exe, dll, bin, iso, dmg, app, msi, sys, apk, ipa |
| **ebook** | Brown | epub, mobi, azw, azw3, cbz, cbr, djvu |
| **plc** | Industrial | e80, lsc, zap15_1, awl, gxw, acd, s7p, ap17-20, zap18-20, scl, udt, aml, tia, ... |

## Adding extensions

Edit `client/src/styles/asset-icons.scss`:

```scss
$ext-categories: (
  // ... existing entries ...
  myext: document,    // map to existing category
);
```

Then rebuild: `npm run build`

## Adding categories

1. Create an SVG in `client/icons/` (use an existing one as template)
2. Add to `$category-icons` map in the SCSS
3. Map extensions to the new category in `$ext-categories`
4. Rebuild: `npm run build`

## Build

```bash
cd silverstripe-asset_icons
npm install
npm run build       # production build
```

After building, run `composer vendor-expose` to re-expose client assets.

## File structure

```
client/
  icons/              # 18 category SVG source files
  icons-source.svg    # Master Inkscape file with all icons
  src/
    js/               # Vanilla JS source (React fiber → data-ext + preview)
    styles/           # SCSS source
  dist/
    icons/            # External SVG files (loaded on-demand, cached)
    js/               # Copied JS
    styles/           # Compiled CSS (~77KB)
src/
  Dev/                # IconsPreviewController (visit /admin/asset-icons-preview)
  Renderable/         # Rendered preview system
    RenderablePreviewExtension.php   # File extension: generates & stores variants
    RenderableThumbnailGenerator.php # Injector override for GraphQL thumbnails
    RendererInterface.php            # Contract for CLI renderers
    XpdfRenderer.php                 # PDF → PNG (bundled binary)
    GhostscriptRenderer.php          # EPS/PS/AI → PNG (system gs)
    SvgRenderer.php                  # SVG → PNG (system rsvg-convert)
```

## Credits

- Pictograms: [Bootstrap Icons](https://icons.getbootstrap.com/) (MIT)
