import {
    test,
    expect,
    ICONS,
    IMAGE,
    backgroundOf,
    extOf,
    iconUrl,
    openFixtureFolder,
    row,
    showTable,
    showTiles,
    tile,
} from './support';

// The category icons in the asset admin: the module script (client/dist/js/asset-icons.js) reads
// each file's extension from React's data and sets data-ext on the tile, table row and edit-panel
// thumbnail; the stylesheet maps data-ext to a module SVG. Silverstripe 5 and 6 render the gallery
// differently (Griddle vs TanStack table, an extra tile wrapper on 6), which is what the script's
// lookups have to survive.

test('the module script and stylesheet load on admin pages', async ({ page }) => {
    // LeftAndMain extra_requirements (_config/config.yml) add both to every admin page.
    const assets = new Map<string, number>();
    page.on('response', (r) => {
        const m = r.url().match(/silverstripe-asset_icons\/client\/dist\/(js\/asset-icons\.js|styles\/asset-icons\.css)/);
        if (m) assets.set(m[1], r.status());
    });
    await page.goto('/admin/assets');
    await expect(page.locator('.gallery-item--folder', { hasText: 'aib-files' })).toBeVisible();
    expect(assets.get('js/asset-icons.js'), 'client/dist/js/asset-icons.js').toBe(200);
    expect(assets.get('styles/asset-icons.css'), 'client/dist/styles/asset-icons.css').toBe(200);
});

test('tile view: every non-image file shows its own category icon', async ({ page }) => {
    const svgs = new Map<string, number>();
    page.on('response', (r) => {
        const m = r.url().match(/silverstripe-asset_icons\/client\/dist\/icons\/([a-z]+)\.svg/);
        if (m) svgs.set(m[1], r.status());
    });
    await openFixtureFolder(page);
    await showTiles(page);

    for (const [name, icon] of Object.entries(ICONS)) {
        const t = tile(page, name);
        await expect(t, `${name} tile marked`).toHaveAttribute('data-ext', extOf(name));
        const thumb = t.locator('.gallery-item__thumbnail');
        await expect(thumb, `${name} thumbnail marked`).toHaveAttribute('data-ext', extOf(name));
        expect(await backgroundOf(thumb), `${name} shows ${icon}.svg`).toMatch(iconUrl(icon));
        // Rendered previews are off (the default): no preview flag, no inline preview image.
        await expect(thumb).not.toHaveAttribute('data-preview', /.*/);
    }
    for (const icon of new Set(Object.values(ICONS))) {
        expect(svgs.get(icon), `${icon}.svg served`).toBe(200);
    }

    // The image keeps core's own thumbnail: no data-ext, no module icon.
    const image = tile(page, IMAGE);
    await expect(image).toBeVisible();
    await expect(image).not.toHaveAttribute('data-ext', /.*/);
    expect(await backgroundOf(image.locator('.gallery-item__thumbnail'))).not.toMatch(/asset_icons/);
});

test('table view: every non-image row shows its icon and the file name under the title', async ({ page }) => {
    await openFixtureFolder(page);
    await showTable(page);

    for (const [name, icon] of Object.entries(ICONS)) {
        const r = row(page, name);
        await expect(r, `${name} row marked`).toHaveAttribute('data-ext', extOf(name));
        const img = r.locator('.gallery__table-image');
        await expect(img).toHaveAttribute('data-ext', extOf(name));
        expect(await backgroundOf(img), `${name} shows ${icon}.svg`).toMatch(iconUrl(icon));
        // The title cell carries the file name for the stylesheet's ::after line.
        const title = r.locator('.gallery__table-column--title span');
        await expect(title).toHaveAttribute('data-name', name);
        await expect(title).toHaveAttribute('data-filename', `aib-files/${name}`);
    }

    const image = row(page, IMAGE);
    await expect(image).not.toHaveAttribute('data-ext', /.*/);
    expect(await backgroundOf(image.locator('.gallery__table-image'))).not.toMatch(/asset_icons/);
});

test('edit panel: the selected PDF shows the PDF icon in place of core\'s generic one', async ({ page }) => {
    await openFixtureFolder(page);
    await showTiles(page);
    await tile(page, 'report.pdf').locator('.gallery-item').click();
    await expect(page).toHaveURL(/\/edit\/\d+/);

    const thumb = page.locator('.editor__details .editor__thumbnail-container');
    await expect(thumb).toHaveAttribute('data-ext', 'pdf');
    expect(await backgroundOf(thumb)).toMatch(iconUrl('pdf'));
    // Core's generic document image is hidden so the icon behind it shows.
    await expect(thumb.locator('img')).toHaveCSS('opacity', '0');
});
