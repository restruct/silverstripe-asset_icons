# README screenshots

`readme.shot.ts` writes the PNGs in `docs/images/` that the module README shows. It is not a test
suite: it runs with its own config, `playwright.screenshots.config.ts`, which the browser tests and
CI never load, so a normal test run never rewrites the images. Never edit the PNGs by hand;
regenerate them.

What it needs:

- a Silverstripe 6 host with this module installed and the browser fixtures from
  `tests/browser/fixtures/` copied into its `app/src/` (and `fixtures/_config/` into its
  `app/_config/`), then the database built. `AiBShowcaseSeed` seeds the content: a `Showcase`
  folder in Files with one file per common category, a PDF and an SVG that get rendered previews,
  and a JPG drawn with GD. `AiBRecord` seeds the record whose UploadField holds a PDF;
- `rsvg-convert` on the host, or the SVG keeps its category icon instead of a preview (the PDF
  renderer is bundled);
- `BROWSER_TARGET_URLS` pointing at that host (only the `ss6` target is used, else the first).

Rendered previews are switched on per request by the fixture middleware's cookie
(`AiBVariantMiddleware`), as the previews spec does; a project sets
`enable_renderable_previews` in YAML instead.

The shared runner in the maintainers' workspace does all of that:

```bash
BROWSER_ONLY=ss6 tools/browser/run.sh silverstripe-asset_icons --config=playwright.screenshots.config.ts
```

Against a host you started yourself, from this directory:

```bash
npm ci
BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8864" npx playwright test --config=playwright.screenshots.config.ts
```
