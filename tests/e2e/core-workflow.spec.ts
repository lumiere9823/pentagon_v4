import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const viewports = [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 900 },
] as const;

test('owner can create website with starter set, publish homepage, redirect technical slug, and reassign homepage', async ({
    page,
    request,
}, testInfo) => {
    // 1. Authenticate as owner
    await page.goto('/login');
    await page.getByLabel('Email address').fill('e2e-owner@example.test');
    await page.locator('input[name="password"]').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    // 2. Website Create with Starter Pages Preview Card
    await page.goto('/websites/create');
    await expect(page.getByRole('heading', { name: 'Starter pages' })).toBeVisible();
    await expect(page.getByTestId('starter-page-home')).toContainText('Home');
    await expect(page.getByTestId('starter-page-home')).toContainText('Homepage');
    await expect(page.getByTestId('starter-page-home')).toContainText('/');
    await expect(page.getByTestId('starter-page-about')).toContainText('About');
    await expect(page.getByTestId('starter-page-about')).toContainText('/about');
    await expect(page.getByTestId('starter-page-contact')).toContainText('Contact');
    await expect(page.getByTestId('starter-page-contact')).toContainText('/contact');

    await page.getByLabel('Website Name').fill('E2E Website');
    await page.getByLabel('Website Name').blur();
    await expect(page.getByLabel('URL Slug')).toHaveValue('e2e-website');
    await page.getByRole('button', { name: 'Create Website' }).click();
    await expect(page).toHaveURL(/\/websites\/\d+$/);

    const websiteShowUrl = page.url();

    // 3. Website Show: Verify immediately created starter set & homepage badge/path
    const homepageRow = page.getByTestId('homepage-row');
    await expect(homepageRow).toBeVisible();
    await expect(homepageRow).toContainText('Home');
    await expect(homepageRow.getByTestId('homepage-badge')).toHaveText('Homepage');
    await expect(homepageRow.getByTestId('page-path')).toHaveText('/');
    await expect(homepageRow.getByTestId('delete-homepage-disabled-btn')).toBeDisabled();

    // Verify non-home pages exist and offer "Set as homepage"
    await expect(page.locator('[data-testid^="page-row-"]').filter({ hasText: 'About' })).toBeVisible();
    await expect(page.locator('[data-testid^="page-row-"]').filter({ hasText: 'Contact' })).toBeVisible();
    await expect(page.getByTestId('set-homepage-btn')).toHaveCount(2);

    // 4. Open Home page in Builder
    await homepageRow.getByRole('link', { name: 'Builder' }).click();
    await expect(page).toHaveURL(/\/pages\/\d+\/builder/);

    const builderUrl = page.url();
    await expect(page.getByLabel('Editor Canvas Surface')).toBeVisible();

    // Responsive & axe checks on Page Builder
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

    // 5. Publish Home page to activate canonical root URL
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
    expect(publicUrl).toMatch(/\/sites\/e2e-website$/);

    // Canonical root returns 200
    const publishedResponse = await request.get(publicUrl!);
    expect(publishedResponse.status()).toBe(200);
    expect(await publishedResponse.text()).toContain('Welcome to your new website');

    // Technical slug /home permanently redirects (301) to canonical root
    const technicalSlugResponse = await request.get(`${publicUrl}/home`, {
        maxRedirects: 0,
    });
    expect(technicalSlugResponse.status()).toBe(301);
    expect(technicalSlugResponse.headers()['location']).toMatch(/\/sites\/e2e-website$/);

    await publishDialog.getByRole('button', { name: 'Close' }).click();

    // 6. Return to Website Show and reassign homepage to About
    await page.goto(websiteShowUrl);
    await expect(page.getByTestId('homepage-row')).toContainText('Home');

    page.once('dialog', (dialog) => dialog.accept());
    // Click "Set as homepage" on About page
    const setHomepageButtons = page.getByTestId('set-homepage-btn');
    await setHomepageButtons.first().click();

    // After reassignment, About is now Homepage with / path, and Home is non-homepage with /home path
    await expect(page.getByTestId('homepage-row')).toContainText('About');
    await expect(page.getByTestId('homepage-row').getByTestId('page-path')).toHaveText('/');
    const homeRow = page
        .locator('[data-testid^="page-row-"]')
        .filter({ has: page.getByText('Home', { exact: true }) });
    await expect(homeRow).toBeVisible();
    await expect(homeRow.getByTestId('page-path')).toHaveText('/home');

    // Old homepage (Home) now offers "Set as homepage" and has an active delete button
    await expect(page.getByTestId('set-homepage-btn')).toHaveCount(2);

    // 7. Verify accessibility on Website Show
    const websiteAxe = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();
    expect(
        websiteAxe.violations.filter((violation) =>
            ['critical', 'serious'].includes(violation.impact ?? ''),
        ),
    ).toEqual([]);
});
