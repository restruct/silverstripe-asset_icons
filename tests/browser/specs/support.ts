import { test as base, expect, type Locator, type Page } from '@playwright/test';

// Shared fixtures and helpers for the asset_icons specs.
//
// Every dev/build re-seeds the asset folder "aib-files" with one file per icon the specs check, and
// a record whose UploadField holds the PDF (tests/browser/fixtures/AiBRecord.php, copied into the
// scratch host by the runner). Rendered previews are off, the module default.

/**
 * test, extended with an automatic console guard: every spec fails if the page logs a console
 * error or throws an uncaught exception at any point, page load included. "Failed to load
 * resource" (any 4xx/5xx asset or request) arrives as a console error too, so a missing module
 * script, stylesheet or icon SVG is caught here as well. Warnings (the admin's own Apollo
 * deprecation notices) do not count.
 */
export const test = base.extend<{ consoleGuard: void }>({
    consoleGuard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => errors.push(`uncaught: ${err.message}`));

            await use();

            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors or uncaught exceptions').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/**
 * The seeded non-image files and the module icon each must show (client/src/styles/asset-icons.scss,
 * $ext-categories). pdf and xlsx are the ones only the module's data-ext tells apart: core files
 * both under its broad "document" category.
 */
export const ICONS: Record<string, string> = {
    'report.pdf': 'pdf',
    'notes.docx': 'document',
    'sheet.xlsx': 'spreadsheet',
    'bundle.zip': 'archive',
    'song.mp3': 'audio',
};

/** The seeded image: core renders its thumbnail, so the module must leave it alone. */
export const IMAGE = 'pixel.png';

/** The extension of a file name. */
export function extOf(name: string): string {
    return name.split('.').pop()!;
}

/** A computed background-image that is this module icon (served from client/dist/icons/). */
export function iconUrl(icon: string): RegExp {
    return new RegExp(`^url\\("[^"]*/silverstripe-asset_icons/client/dist/icons/${icon}\\.svg"\\)$`);
}

/** The computed background-image of an element. */
export function backgroundOf(locator: Locator): Promise<string> {
    return locator.evaluate((el) => getComputedStyle(el).backgroundImage);
}

/**
 * Open the fixture folder in the asset admin the way an editor does: the asset admin root, then a
 * click on the folder tile. Waits until the folder's files are listed.
 */
export async function openFixtureFolder(page: Page): Promise<void> {
    await page.goto('/admin/assets');
    await page.locator('.gallery-item--folder', { hasText: 'aib-files' }).click();
    await expect(page).toHaveURL(/\/admin\/assets\/show\/\d+/);
    await expect(page.locator('.gallery-item, .gallery__table-row').filter({ hasText: 'report.pdf' }).first()).toBeVisible();
}

/** The tile of a file in the tile view: the per-file wrapper the module script marks. */
export function tile(page: Page, name: string): Locator {
    return page.locator('.gallery__main-view--tile .gallery__files > div').filter({ hasText: name });
}

/** The row of a file in the table view. */
export function row(page: Page, name: string): Locator {
    return page.locator('.gallery__table tbody tr.gallery__table-row').filter({ hasText: name });
}

/** Switch the gallery to the table view (the view toggle in the toolbar). */
export async function showTable(page: Page): Promise<void> {
    if (await page.locator('.gallery__table').count() === 0) {
        await page.locator('.gallery__view-change-button').click();
    }
    await expect(row(page, 'report.pdf')).toBeVisible();
}

/** Switch the gallery to the tile view. */
export async function showTiles(page: Page): Promise<void> {
    if (await page.locator('.gallery__main-view--tile').count() === 0) {
        await page.locator('.gallery__view-change-button').click();
    }
    await expect(tile(page, 'report.pdf')).toBeVisible();
}
