import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { resolveAppLayout } from '@/lib/appLayoutOwnership';

const appLayout = { name: 'AppLayout' };
const authLayout = { name: 'AuthLayout' };
const settingsLayout = { name: 'SettingsLayout' };

describe('application layout ownership', () => {
    it('assigns exactly one application layout owner to each route family', () => {
        expect(
            resolveAppLayout('Websites/Index', {
                app: appLayout,
                auth: authLayout,
                settings: settingsLayout,
            }),
        ).toEqual(appLayout);
        expect(
            resolveAppLayout('settings/Profile', {
                app: appLayout,
                auth: authLayout,
                settings: settingsLayout,
            }),
        ).toEqual([appLayout, settingsLayout]);
        expect(
            resolveAppLayout('auth/Login', {
                app: appLayout,
                auth: authLayout,
                settings: settingsLayout,
            }),
        ).toEqual(authLayout);
        expect(
            resolveAppLayout('Welcome', {
                app: appLayout,
                auth: authLayout,
                settings: settingsLayout,
            }),
        ).toBeNull();
    });

    it.each([
        'resources/js/pages/Websites/Index.vue',
        'resources/js/pages/Websites/Create.vue',
        'resources/js/pages/Websites/Show.vue',
        'resources/js/pages/Websites/Submissions.vue',
        'resources/js/pages/Pages/Create.vue',
        'resources/js/pages/Pages/Edit.vue',
    ])('%s delegates AppLayout rendering to the application', (file) => {
        const source = readFileSync(resolve(process.cwd(), file), 'utf8');

        expect(source).not.toContain(
            "import AppLayout from '@/layouts/AppLayout.vue'",
        );
        expect(source).not.toContain('<AppLayout');
        expect(source).not.toContain('</AppLayout>');
    });
});
