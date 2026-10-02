import { test, expect, backgroundOf, iconUrl } from './support';

// The UploadField in a CMS edit form, and the module's icon overview page.

test.describe('UploadField', () => {
    async function openRecord(page: import('@playwright/test').Page) {
        await page.goto('/admin/aib-browser/records');
        await page.locator('#Form_EditForm_records tr.ss-gridfield-item', { hasText: 'PDF attachment' }).click();
        const item = page.locator('#Form_ItemEditForm .uploadfield-item');
        await expect(item).toBeVisible();
        return item;
    }

    test('the attached PDF is marked with its extension', async ({ page }) => {
        const item = await openRecord(page);
        await expect(item).toHaveAttribute('data-ext', 'pdf');
        await expect(item.locator('.uploadfield-item__thumbnail')).toHaveAttribute('data-ext', 'pdf');
    });

    test.fixme('the attached PDF shows the PDF icon, not core\'s generic document image', async ({ page }) => {
        // FIXME https://github.com/restruct/silverstripe-asset_icons/issues/4
        // The script copies the item's `thumbnail` into an inline background-image whenever it is
        // set, meant for rendered previews. For an UploadField item `thumbnail` is core's generic
        // app icon (document_92.png), so with previews off that inline style hides the PDF icon.
        const item = await openRecord(page);
        expect(await backgroundOf(item.locator('.uploadfield-item__thumbnail'))).toMatch(iconUrl('pdf'));
    });
});

test.describe('Icon overview (admin/asset-icons-preview)', () => {
    test('lists all 18 categories and every icon loads', async ({ page }) => {
        await page.goto('/admin/asset-icons-preview');
        const cards = page.locator('.icon-card');
        await expect(cards).toHaveCount(18);
        await expect(cards.locator('.label').first()).toHaveText('blank');
        // Every <img> has finished loading with a real image (a 404 would also hit the console guard).
        const images = cards.locator('img');
        await expect(images).toHaveCount(18);
        const broken = await images.evaluateAll((imgs) =>
            (imgs as HTMLImageElement[]).filter((i) => !i.complete || i.naturalWidth === 0).map((i) => i.alt),
        );
        expect(broken, 'icons that did not load').toEqual([]);
    });

    test('is for CMS users only', async ({ browser, baseURL }) => {
        const anonymous = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        const page = await anonymous.newPage();
        await page.goto('/admin/asset-icons-preview');
        await expect(page).toHaveURL(/\/Security\/login/);
        await expect(page.locator('.icon-card')).toHaveCount(0);
        await anonymous.close();
    });
});
