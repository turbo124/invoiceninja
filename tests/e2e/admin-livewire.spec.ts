import { expect, test } from './fixtures';
import { isLivewireUpdate } from './livewire-helpers';

test.beforeEach(async ({ page, account }) => {
    test.skip(
        !process.env.ADMIN_EMAILS?.split(',').includes(account.ownerEmail),
        'The selected test owner must be configured in ADMIN_EMAILS',
    );

    await page.goto('/admin/login');
    await page.locator('input[name="email"]').fill(account.ownerEmail);
    await page.locator('input[name="password"]').fill(account.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/accounts$/);
});

test('admin Livewire tables filter and switch databases without JavaScript errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    const search = page.locator('input[wire\\:model\\.live="search_query"]');
    const database = page.locator('select[wire\\:model\\.live="db"]');
    const account_key = (await page.locator('tbody tr').first().locator('td').first().innerText()).trim();

    let update = page.waitForResponse(isLivewireUpdate);
    await search.fill(account_key);
    expect((await update).status()).toBe(200);
    await expect(page.locator('tbody tr')).toHaveCount(1);

    update = page.waitForResponse(isLivewireUpdate);
    await database.selectOption('db-ninja-02');
    expect((await update).status()).toBe(200);
    await expect(page.locator('tbody tr')).toHaveCount(0);

    update = page.waitForResponse(isLivewireUpdate);
    await database.selectOption('db-ninja-01');
    expect((await update).status()).toBe(200);
    await expect(page.locator('tbody tr')).toHaveCount(1);
    expect(errors).toEqual([]);
});

test('admin Livewire updates require authentication after logout', async ({ page }) => {
    const snapshot = await page.locator('[wire\\:snapshot]').first().getAttribute('wire:snapshot');
    const update_uri = await page.locator('script[data-update-uri]').getAttribute('data-update-uri');
    expect(snapshot).not.toBeNull();
    expect(update_uri).not.toBeNull();

    await page.goto('/admin/logout');
    await expect(page).toHaveURL(/\/admin\/login$/);
    const csrf = await page.locator('input[name="_token"]').getAttribute('value');
    const response = await page.request.post(update_uri!, {
        headers: { 'X-Livewire': '1' },
        data: {
            _token: csrf,
            components: [{ snapshot, updates: {}, calls: [{ method: '$refresh', params: [] }] }],
        },
        maxRedirects: 0,
    });

    expect(response.status()).toBe(302);
    expect(response.headers().location).toMatch(/\/admin\/login$/);
});
