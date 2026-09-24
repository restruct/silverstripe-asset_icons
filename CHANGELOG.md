# Changelog

## 2.4.0 (2026-09-25)

**Silverstripe 6 support, on the same line as Silverstripe 5.** `2.4.x` requires
`silverstripe/asset-admin ^2 || ^3` (Silverstripe 5 or 6) and PHP `^8.1`. Nothing is removed and no
configuration changes are needed; Silverstripe 5 projects can update in place. Silverstripe 4 stays on
the `1.x` tags.

On Silverstripe 6 this module needs `restruct/silverstripe-simpler` 1.x (the `0.x` tags only allow
Silverstripe 4 and 5). simpler 1.0.0 supports Silverstripe 5 and 6, so a fresh install picks it on
either major; `0.x` stays allowed on Silverstripe 5.

### Fixed

- **Rendered previews never appeared in the Silverstripe 6 asset admin.** The preview thumbnail is
  injected by overriding the `ThumbnailGenerator` service the asset admin reads files through. That
  was `ThumbnailGenerator.graphql`, which asset-admin 3 no longer uses: its `AssetAdminOpen`
  controller uses `ThumbnailGenerator.assetadminopen`. Both are now overridden, each with core's
  `Generates: false`. There was no error; the previews were simply missing.
- **Extension icons and previews in tile and table view on Silverstripe 6** (not yet checked in a
  browser, see below). asset-admin 3 wraps each tile in an extra element and renders the table with
  TanStack Table, so the JavaScript found no file data there. It now looks inside the tile and matches
  table rows by their React key, and selects the table by `.gallery__table` (the `.gallery__main-view--table`
  wrapper exists on Silverstripe 5 only).
- **Category icons in list (table) view without JavaScript** (part of #1): the CSS targeted a
  `.gallery__table-row--{category}` class that no asset-admin version sets. It now also targets
  `.gallery__table-image--{category}`, which both majors set on the thumbnail cell.
- **`RenderablePreview: null` could not remove the preview extension from `File`**, because it was
  registered a second time without a name. It is now registered once, as `RenderablePreview`.
- A stray copy of `code.svg` in `client/dist/styles/` (unreferenced; the stylesheet loads
  `client/dist/icons/code.svg`) is removed from the package.

### Added

- A behavioural PHPUnit suite (21 tests, same count on Silverstripe 5 / PHPUnit 9 and Silverstripe 6
  / PHPUnit 11) and GitHub Actions CI: one job per Silverstripe major at its oldest and newest PHP,
  plus a PHP lint job. See "Running the tests" in the README.
- README: installation, a version compatibility table, every configuration option with its default,
  the PHP API (`RenderedPreview()`, `getRenderedPreviewURL()`) and the icon preview screen.
- `composer.json`: `funding` (Packagist Fund link) and an explicit `php` constraint.

### Not verified yet

- The JavaScript and CSS changes above have not been looked at in a browser on either major. The PHP
  side is covered by the suite; the fiber lookups in `client/src/js/asset-icons.js` are not.

## 2.3.1 and earlier

See the git history and tags.
