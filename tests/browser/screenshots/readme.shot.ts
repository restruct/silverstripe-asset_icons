import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import * as path from 'path';

// Generates the README screenshots in docs/images/. Not a test suite: run it with
// playwright.screenshots.config.ts (see README.md next to this file). The content comes from the
// browser fixtures: AiBShowcaseSeed seeds the "Showcase" folder (one file per common category, a
// renderable PDF and SVG, one JPG), AiBRecord the record whose UploadField holds a PDF.

const IMAGES = path.join(__dirname, '..', '..', '..', 'docs', 'images');

/**
 * Switch rendered previews on for this context's requests, the way the previews spec does: the
 * fixture middleware (fixtures/AiBVariantMiddleware.php) reads this cookie. A project sets
 * enable_renderable_previews in YAML instead.
 */
async function previewsOn(context: BrowserContext, baseURL: string): Promise<void> {
    await context.addCookies([{ name: 'aib-browser-variant', value: 'previews', url: baseURL }]);
}

/**
 * Hide what only the test host has: the browser-test fixtures' menu entry ("Asset icons browser
 * test"). Presentation only; nothing about the module's own UI is changed.
 */
async function hideFixtureChrome(page: Page): Promise<void> {
    await page.addStyleTag({ content: '.cms-menu li:has(> a[href*="admin/aib-browser"]) { display: none !important; }' });
}

/** No hover overlay on a tile or row, no focus highlight anywhere. */
async function settle(page: Page): Promise<void> {
    await page.mouse.move(1, 1);
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await page.waitForTimeout(500);
}

/** Open the Showcase folder and wait until the module script has marked every non-image file. */
async function openShowcase(page: Page): Promise<void> {
    await page.goto('/admin/assets');
    await page.locator('.gallery-item--folder', { hasText: 'Showcase' }).click();
    await expect(page).toHaveURL(/\/admin\/assets\/show\/\d+/);
    await expect(page.locator('.gallery-item, .gallery__table-row').filter({ hasText: 'annual-report.pdf' }).first()).toBeVisible();
}

async function showTiles(page: Page): Promise<void> {
    if (await page.locator('.gallery__main-view--tile').count() === 0) {
        await page.locator('.gallery__view-change-button').click();
    }
    // 15 non-image files, each marked by the module script; the two renderable ones as previews.
    await expect(page.locator('.gallery__main-view--tile .gallery-item__thumbnail[data-ext]')).toHaveCount(15);
    await expect(page.locator('.gallery__main-view--tile .gallery-item__thumbnail[data-preview]')).toHaveCount(2);
}

/** Wait until every CSS background on the gallery thumbnails has actually loaded. */
async function backgroundsLoaded(page: Page, selector: string): Promise<void> {
    await expect
        .poll(
            () =>
                page.$$eval(selector, async (els) => {
                    const urls = els
                        .map((e) => /url\("?([^")]+)"?\)/.exec(getComputedStyle(e).backgroundImage)?.[1])
                        .filter((u): u is string => !!u);
                    const results = await Promise.all(
                        urls.map(
                            (u) =>
                                new Promise<boolean>((resolve) => {
                                    const img = new Image();
                                    img.onload = () => resolve(img.naturalWidth > 0);
                                    img.onerror = () => resolve(false);
                                    img.src = u;
                                }),
                        ),
                    );
                    return urls.length === els.length && results.every(Boolean);
                }),
            { message: 'all thumbnails loaded' },
        )
        .toBe(true);
}

test('Files, tile view: category icons, rendered previews, a PDF open in the editor', async ({ page, context, baseURL }) => {
    await previewsOn(context, baseURL!);
    await openShowcase(page);
    await showTiles(page);
    await hideFixtureChrome(page);

    // A little taller than the default, so four full rows of tiles fit next to the editor.
    await page.setViewportSize({ width: 1280, height: 870 });
    await page.locator('.gallery-item', { has: page.getByText('annual-report.pdf', { exact: true }) }).click();
    const editor = page.locator('#Form_fileEditForm');
    await expect(editor.locator('.editor__thumbnail')).toBeVisible();
    await expect.poll(() => editor.locator('img.editor__thumbnail').evaluate((i: HTMLImageElement) => i.complete && i.naturalWidth > 0)).toBe(true);
    await backgroundsLoaded(page, '.gallery__main-view--tile .gallery-item__thumbnail');
    // Opening the editor scrolls the gallery past its toolbar; scroll every panel back to the top.
    await page.evaluate(() => document.querySelectorAll<HTMLElement>('*').forEach((e) => { if (e.scrollTop > 0) e.scrollTop = 0; }));
    await settle(page);

    await page.screenshot({ path: path.join(IMAGES, 'asset-admin-tiles.png') });
});

test('Files, table view: the same icons in the list', async ({ page, context, baseURL }) => {
    await previewsOn(context, baseURL!);
    await openShowcase(page);
    if (await page.locator('.gallery__table').count() === 0) {
        await page.locator('.gallery__view-change-button').click();
    }
    // Tall enough that the rows down to logo.svg are inside the window, so the clip below can reach them.
    await page.setViewportSize({ width: 1280, height: 1100 });
    await expect(page.locator('.gallery__table .gallery__table-image[data-ext]')).toHaveCount(15);
    await hideFixtureChrome(page);
    await backgroundsLoaded(page, '.gallery__table .gallery__table-image');
    await settle(page);

    // The gallery from its toolbar down to the logo.svg row: enough rows to show icons and both
    // previews; the CMS menu is already in the tile screenshot.
    const main = (await page.locator('.gallery__main').boundingBox())!;
    const last = (await page.locator('.gallery__table tr.gallery__table-row').filter({ hasText: 'logo.svg' }).boundingBox())!;
    await page.screenshot({
        path: path.join(IMAGES, 'asset-admin-table.png'),
        clip: { x: main.x, y: main.y, width: main.width, height: Math.ceil(last.y + last.height - main.y) },
    });
    // Back to the default view for the next run (the admin remembers the view per browser).
    await page.locator('.gallery__view-change-button').click();
});

test('UploadField: an attached PDF shows its category icon', async ({ page }) => {
    await page.goto('/admin/aib-browser/records');
    await page.locator('#Form_EditForm_records tr.ss-gridfield-item').filter({ has: page.getByText('PDF attachment', { exact: true }) }).click();
    const field = page.locator('#Form_ItemEditForm_Attachment_Holder');
    await expect(field.locator('.uploadfield-item__thumbnail[data-ext="pdf"]')).toBeVisible();
    await hideFixtureChrome(page);
    await backgroundsLoaded(page, '.uploadfield-item__thumbnail');
    await settle(page);

    // The Title field and the UploadField below it.
    const title = (await page.locator('#Form_ItemEditForm_Title_Holder').boundingBox())!;
    const box = (await field.boundingBox())!;
    const top = Math.floor(title.y - 16);
    await page.screenshot({
        path: path.join(IMAGES, 'uploadfield.png'),
        clip: { x: box.x, y: top, width: Math.min(900, 1280 - box.x), height: Math.ceil(box.y + box.height + 16 - top) },
    });
});

test('Icon overview: every category at /admin/asset-icons-preview', async ({ page }) => {
    await page.goto('/admin/asset-icons-preview');
    await expect(page.locator('.icon-card')).toHaveCount(18);
    await expect
        .poll(() => page.evaluate(() => Array.from(document.images).every((i) => i.complete && i.naturalWidth > 0)))
        .toBe(true);
    await settle(page);
    // The cards only: the page heading is a developer label, not part of what the README shows.
    await page.locator('.grid').screenshot({ path: path.join(IMAGES, 'icon-overview.png') });
});
