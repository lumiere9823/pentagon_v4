import { defineConfig } from '@playwright/test';
import { e2eEnvironment } from './tests/e2e/environment';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 2 : 0,
    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },
    outputDir: 'storage/framework/testing/playwright-results',
    reporter: [
        ['list'],
        [
            'html',
            {
                outputFolder: 'storage/framework/testing/playwright-report',
                open: 'never',
            },
        ],
    ],
    use: {
        baseURL: e2eEnvironment.APP_URL,
        channel: process.platform === 'win32' ? 'msedge' : undefined,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8173 --no-reload',
        url: e2eEnvironment.APP_URL,
        env: e2eEnvironment,
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
    },
});
