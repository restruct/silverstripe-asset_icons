# CLAUDE.md - Asset Icons Module

This module provides file type icons for SilverStripe's Asset Admin, replacing the default generic icons with category-colored SVG icons.

## File Structure

```
client/
├── icons/              # Individual SVG icons used by the module
│   ├── blank.svg
│   ├── pdf.svg
│   ├── document.svg
│   └── ... (18 total)
├── icons-source.svg    # Master source file with all icons (for editing in Inkscape)
├── src/styles/
│   └── asset-icons.scss
├── extract-icons.py    # Regenerates icons/*.svg from icons-source.svg
└── dist/
    └── styles/
        └── asset-icons.css
```

## Icon Source File

The `client/icons-source.svg` is a combined Inkscape file containing all 18 category icons. It's a single-line XML format for easy sed/regex processing.

### Element Structure

Each icon consists of two elements:
1. **Background path** — Uses `fill:url(#grad-{category})` gradient
2. **Foreground path** — Has `id="file-{category}"` with solid `fill:#{color}`

### Element IDs

| Category | Source ID | Output Filename | Notes |
|----------|-----------|-----------------|-------|
| blank | `file-blank` | blank.svg | |
| pdf | `file-pdf` | pdf.svg | |
| document | `file-document` | document.svg | |
| spreadsheet | `file-spreadsheet` | spreadsheet.svg | |
| presentation | `file-presentation` | presentation.svg | |
| bitmap | `file-bitmap` | **image.svg** | Renamed for SCSS |
| vector | `file-vector` | vector.svg | Not used in SCSS |
| cad | `file-cad` | cad.svg | |
| database | `file-database` | database.svg | |
| font | `file-font` | font.svg | |
| binary | `file-binary` | **system.svg** | Renamed for SCSS |
| archive | `file-archive` | archive.svg | |
| audio | `file-audio` | audio.svg | |
| video | `file-video` | video.svg | |
| code | `file-code` | code.svg | |
| markup | `file-markup` | markup.svg | |
| ebook | `file-ebook` | ebook.svg | |
| plc | `file-plc` | plc.svg | |

## Color Scheme

Colors are distributed across the spectrum for maximum differentiation:

| Category | Hue | Background Light | Background Dark | Foreground |
|----------|-----|------------------|-----------------|------------|
| blank | Gray | #d8dce0 | #b8c0c8 | #505860 |
| pdf | Red 0° | #f5b8b8 | #e89090 | #a01515 |
| presentation | Orange 30° | #f5d4b8 | #e8bc90 | #a05010 |
| archive | Yellow 45° | #f5ecb8 | #e8dc90 | #907000 |
| spreadsheet | Lime 90° | #c8f5b8 | #a0e890 | #308010 |
| cad | Green 120° | #b8f5c8 | #90e8a8 | #108030 |
| database | Teal 180° | #b8f5ec | #90e8dc | #008080 |
| code | Cyan 195° | #b8ecf5 | #90dce8 | #008090 |
| image/bitmap | Sky 200° | #b8e0f5 | #90c8e8 | #0070a0 |
| document | Blue 210° | #b8d4f5 | #90bce8 | #0055a0 |
| vector | Indigo 240° | #c8b8f5 | #a890e8 | #5030a0 |
| audio | Purple 270° | #d8b8f5 | #c090e8 | #6020a0 |
| font | Violet 285° | #e8b8f5 | #d490e8 | #8020a0 |
| video | Magenta 300° | #f5b8e8 | #e890d4 | #a01080 |
| markup | Slate | #c8d0d8 | #a8b4c0 | #405060 |
| system/binary | Gray | #d0d4d8 | #b0b8c0 | #404850 |
| ebook | Brown | #e8d8c0 | #d4c0a0 | #705020 |
| plc | Olive | #dce0b8 | #c8cc90 | #606010 |

## Extracting Icons from Source File

The `icons-source.svg` is an Inkscape file with all icons. Each icon has two elements:
- `bg-{category}` — Background path with gradient fill `url(#grad-{category})`
- `file-{category}` — Foreground pictogram with solid fill

To extract individual icons after modifying the source, run `client/extract-icons.py`:

```bash
cd client
python3 extract-icons.py
```

It regenerates **every** icon found in `icons-source.svg` (each needs a `bg-{category}` path, a
`file-{category}` path and a `grad-{category}` gradient), overwriting `icons/{name}.svg`. Source names
`bitmap` and `binary` are written as `image.svg` and `system.svg`. Running it on an unchanged source
reproduces the committed icons byte for byte (checked 2026-09-24).

## Updating Colors in Source File

The source file uses gradient IDs `grad-{category}` and foreground IDs `file-{category}`.

To update gradient colors:
```bash
# Example: Update PDF gradient
sed -i '' 's|id="grad-pdf"[^>]*>[^<]*<stop[^>]*stop-color="[^"]*"|id="grad-pdf" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#NEW_LIGHT"|' icons-source.svg
```

To update foreground colors:
```bash
# Example: Update PDF foreground
sed -i '' 's/\(id="file-pdf"[^>]*fill:\)#[0-9a-fA-F]*/\1#NEW_COLOR/' icons-source.svg
```

## Build Process

After modifying icons:

```bash
# 1. Rebuild CSS (compiles SCSS; SVGs stay external files in dist/icons, see vite.config.js)
npm run build

# 2. Re-expose assets to public
composer vendor-expose
```

## Adding a New Extension

1. Edit `client/src/styles/asset-icons.scss`
2. Add the extension to `$ext-categories` map:
   ```scss
   newext: category,  // e.g., abc: document
   ```
3. Run `npm run build`

## Adding a New Category

1. Add the category to `client/icons-source.svg` (a `bg-{category}` path, a `file-{category}` path and
   a `grad-{category}` gradient). This is the input step 2 needs, so it is not optional.
2. Run `cd client && python3 extract-icons.py` to write `client/icons/{category}.svg` (it rewrites the
   other icons too, identically if their source is unchanged)
3. Edit `client/src/styles/asset-icons.scss`:
   - Add entry to `$category-icons` map
   - Add extensions to `$ext-categories` map
4. Run `npm run build`

## Local Development (Path Repository)

When developing this module locally using a composer path repository (symlink), the vendor plugin exposes files to the wrong path. After `composer vendor-expose`, files end up in `_resources/_dev/silverstripe-asset_icons/` instead of `_resources/vendor/restruct/silverstripe-asset_icons/`.

**Workaround after building:**
```bash
# After npm run build, manually copy to correct expose path:
mkdir -p public/_resources/vendor/restruct/silverstripe-asset_icons/client
cp -R _dev/silverstripe-asset_icons/client/* public/_resources/vendor/restruct/silverstripe-asset_icons/client/
```

Or add this to the project's `composer.json` scripts:
```json
"scripts": {
    "post-install-cmd": [...],
    "expose-asset-icons": "mkdir -p public/_resources/vendor/restruct/silverstripe-asset_icons/client && cp -R _dev/silverstripe-asset_icons/client/* public/_resources/vendor/restruct/silverstripe-asset_icons/client/"
}
```

## JS Architecture

The JavaScript (`client/src/js/asset-icons.js`) works by:

1. **Listening for DOM events** from `restruct/silverstripe-simpler` module (`DOMNodesInserted`)
2. **Walking React fiber tree** to find file data (extension, category, filename)
3. **Setting `data-ext` attributes** on gallery items so CSS can apply icons

The fiber data is found in `memoizedProps` under these keys (checked in order):
- `mp.item.extension` — tile view items
- `mp.rowData.extension` — some table views
- `mp.data.extension` — table view rows (Griddle)

Silverstripe 6 (asset-admin 3) needs two fallbacks, both in `findAnyItemData()`:
- tiles are wrapped in a `<div role="row">`, so the walk starts from the inner `.gallery-item`
- table rows (TanStack Table) carry no item prop; the row's React `key` is matched against the
  `files` array of the nearest ancestor that has one
