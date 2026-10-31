import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const viewports = [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 900 },
] as const;

test('owner can create, inspect, publish and unpublish a responsive page', async ({
    page,
}, testInfo) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('e2e-owner@example.test');
    await page.locator('input[name="password"]').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/websites/create');
    await page.getByLabel('Website Name').fill('E2E Website');
    await page.getByLabel('URL Slug').fill('e2e-website');
    await page.getByRole('button', { name: 'Create Website' }).click();
    await expect(page).toHaveURL(/\/websites\/\d+$/);

    await page.getByRole('link', { name: 'New Page' }).click();
    await page.getByLabel('Page Title').fill('E2E Home');
    await page.getByLabel('Page Slug').fill('home');
    await page
        .getByRole('button', { name: 'Create Page & Open Editor' })
        .click();
    await expect(page).toHaveURL(/\/pages\/\d+\/builder/);

    const builderUrl = page.url();
    await expect(page.getByLabel('Editor Canvas Surface')).toBeVisible();

    for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.goto(builderUrl);
        await expect(page.getByLabel('Editor Canvas Surface')).toBeVisible();

        const accessibility = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa'])
            .analyze();
        expect(
            accessibility.violations.filter((violation) =>
                ['critical', 'serious'].includes(violation.impact ?? ''),
            ),
        ).toEqual([]);

        await testInfo.attach(`builder-${viewport.name}`, {
            body: await page.screenshot(),
            contentType: 'image/png',
        });
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(builderUrl);
    await page.getByTestId('publish-btn').click();
    const publishDialog = page.getByRole('dialog', {
        name: 'Page Published',
    });
    await expect(publishDialog).toBeVisible();

    const publicUrl = await publishDialog
        .getByRole('link', { name: 'Visit Site' })
        .getAttribute('href');
    expect(publicUrl).toBeTruthy();

    const publishedResponse = await page.request.get(publicUrl!);
    expect(publishedResponse.status()).toBe(200);
    expect(await publishedResponse.text()).toContain('E2E Home');

    await publishDialog.getByRole('button', { name: 'Close' }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByTestId('unpublish-btn').click();
    await expect(page.getByTestId('editor-live-region')).toContainText(
        'Page unpublished successfully',
    );

    const unpublishedResponse = await page.request.get(publicUrl!);
    expect(unpublishedResponse.status()).toBe(404);
});
