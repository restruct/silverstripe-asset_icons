import { test, expect, type Page } from './support';

// Rendered preview thumbnails (README "Rendered preview thumbnails"): with
// enable_renderable_previews on, a PDF tile shows a PNG rendered from the file (xpdf, bundled by
// restruct/xpdf-static) instead of the category icon. The option is switched on for this spec's
// requests only, by a cookie the fixture middleware reads (fixtures/AiBVariantMiddleware.php); the
// other specs keep the default (off). The PDF is re-seeded with new content on every db build
// (fixtures/AiBPreviewSeed.php), so the preview is rendered in this run.

async function openPreviewFolder(page: Page) {
    await page.goto('/admin/assets');
    await page.locator('.gallery-item--folder', { hasText: 'aib-previews' }).click();
    await expect(page).toHaveURL(/\/admin\/assets\/show\/\d+/);
    // Listed first (in whichever view the admin remembers), then the view decided on.
    await expect(page.locator('.gallery-item, .gallery__table-row').filter({ hasText: 'sample.pdf' }).first()).toBeVisible();
    if (await page.locator('.gallery__main-view--tile').count() === 0) {
        await page.locator('.gallery__view-change-button').click();
    }
    const thumb = page.locator('.gallery__main-view--tile .gallery__files > div').filter({ hasText: 'sample.pdf' }).locator('.gallery-item__thumbnail');
    await expect(thumb).toHaveAttribute('data-ext', 'pdf');
    return thumb;
}

test('with previews on, a PDF tile shows a PNG rendered from the file', async ({ page, context, baseURL }) => {
    // Control: the default shows the category icon, no preview.
    const plain = await openPreviewFolder(page);
    await expect(plain).not.toHaveAttribute('data-preview', /.*/);

    await context.addCookies([{ name: 'aib-browser-variant', value: 'previews', url: baseURL! }]);
    const thumb = await openPreviewFolder(page);
    await expect(thumb).toHaveAttribute('data-preview', '');
    // The module script sets the preview as an inline background-image (React only does that for images).
    const style = (await thumb.getAttribute('style')) ?? '';
    const url = /background-image:\s*url\("?'?([^"')]+)/.exec(style)?.[1];
    expect(url, `inline preview url in "${style}"`).toMatch(/\.png(\?|$)/);

    // The PNG is served, and is a real image of the rendered page (portrait, like the A6 PDF).
    const response = await page.request.get(url!);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toBe('image/png');
    const size = await page.evaluate(async (src) => {
        const img = new Image();
        img.src = src;
        await img.decode();
        return { w: img.naturalWidth, h: img.naturalHeight };
    }, url!);
    expect(size.w).toBeGreaterThan(0);
    expect(size.h).toBeGreaterThan(size.w);
});
