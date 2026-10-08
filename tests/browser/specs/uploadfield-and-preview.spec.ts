import { test, expect, backgroundOf, iconUrl } from './support';

// The UploadField in a CMS edit form, and the module's icon overview page.

test.describe('UploadField', () => {
    async function openRecord(page: import('@playwright/test').Page, title = 'PDF attachment') {
        await page.goto('/admin/aib-browser/records');
        // Exact title match, so one record's title containing another's never opens the wrong row.
        await page.locator('#Form_EditForm_records tr.ss-gridfield-item').filter({ has: page.getByText(title, { exact: true }) }).click();
        const item = page.locator('#Form_ItemEditForm .uploadfield-item');
        await expect(item).toBeVisible();
        return item;
    }

    test('the attached PDF is marked with its extension', async ({ page }) => {
        const item = await openRecord(page);
        await expect(item).toHaveAttribute('data-ext', 'pdf');
        await expect(item.locator('.uploadfield-item__thumbnail')).toHaveAttribute('data-ext', 'pdf');
    });

    test('the attached PDF shows the PDF icon, not core\'s generic document image', async ({ page }) => {
        // https://github.com/restruct/silverstripe-asset_icons/issues/4
        // For an UploadField item `thumbnail` is File::PreviewLink(), which with previews off is
        // core's generic app icon (framework client/images/app_icons/document_92.png). The script
        // used to copy it into an inline background-image, hiding the PDF icon.
        const item = await openRecord(page);
        const thumb = item.locator('.uploadfield-item__thumbnail');
        // The script marks the item a frame after React renders it; read the style only after that.
        await expect(thumb).toHaveAttribute('data-ext', 'pdf');
        expect(await backgroundOf(thumb)).toMatch(iconUrl('pdf'));
        expect((await thumb.getAttribute('style')) ?? '').not.toMatch(/app_icons/);
    });

    test('with previews on, the attached PDF shows a PNG rendered from the file', async ({ page, context, baseURL }) => {
        // The other half of #4: the inline background-image IS wanted when `thumbnail` is a rendered
        // preview (RenderablePreviewExtension::updatePreviewLink), so the fix must keep that path.
        await context.addCookies([{ name: 'aib-browser-variant', value: 'previews', url: baseURL! }]);
        const item = await openRecord(page, 'Rendered preview attachment');
        const thumb = item.locator('.uploadfield-item__thumbnail');
        await expect(thumb).toHaveAttribute('data-ext', 'pdf');
        await expect(thumb).toHaveAttribute('style', /background-image/);
        const style = (await thumb.getAttribute('style')) ?? '';
        const url = /background-image:\s*url\("?'?([^"')]+)/.exec(style)?.[1];
        expect(url, `inline preview url in "${style}"`).toMatch(/\.png(\?|$)/);
        expect(url).not.toMatch(/app_icons/);
        const response = await page.request.get(url!);
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toBe('image/png');
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
