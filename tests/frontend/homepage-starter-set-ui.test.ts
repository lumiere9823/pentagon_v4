// @vitest-environment happy-dom

import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CreateWebsite from '@/pages/Websites/Create.vue';
import ShowWebsite from '@/pages/Websites/Show.vue';
import EditPage from '@/pages/Pages/Edit.vue';
import { router } from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div data-test="head"><slot /></div>' },
    Link: {
        props: ['href'],
        template: '<a :href="href" data-test="link"><slot /></a>',
    },
    useForm: vi.fn((initial = {}) => ({
        ...initial,
        errors: {},
        processing: false,
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(),
    })),
    router: {
        get: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('Homepage & Starter Set UI Integration', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.confirm = vi.fn().mockReturnValue(true);
    });

    describe('Websites/Create.vue', () => {
        it('renders the Starter pages preview card with Home, About, and Contact', () => {
            const wrapper = mount(CreateWebsite);

            expect(wrapper.text()).toContain('Starter pages');
            expect(wrapper.text()).toContain('Your website will be provisioned with three draft starter pages');

            const homeRow = wrapper.find('[data-testid="starter-page-home"]');
            expect(homeRow.exists()).toBe(true);
            expect(homeRow.text()).toContain('Home');
            expect(homeRow.text()).toContain('Homepage');
            expect(homeRow.text()).toContain('/');

            const aboutRow = wrapper.find('[data-testid="starter-page-about"]');
            expect(aboutRow.exists()).toBe(true);
            expect(aboutRow.text()).toContain('About');
            expect(aboutRow.text()).toContain('/about');

            const contactRow = wrapper.find('[data-testid="starter-page-contact"]');
            expect(contactRow.exists()).toBe(true);
            expect(contactRow.text()).toContain('Contact');
            expect(contactRow.text()).toContain('/contact');

            expect(wrapper.text()).toContain('Starter pages are created as drafts and remain private until published');
        });
    });

    describe('Websites/Show.vue', () => {
        const mockWebsite = {
            id: 1,
            name: 'Acme Studio',
            slug: 'acme-studio',
            status: 'active',
            created_at: '2026-10-31T00:00:00Z',
        };

        const mockPages = [
            {
                id: 101,
                title: 'Home',
                slug: 'home',
                is_homepage: true,
                status: 'draft' as const,
                creator: { id: 1, name: 'Owner' },
                updater: { id: 1, name: 'Owner' },
                created_at: '2026-10-31T00:00:00Z',
                updated_at: '2026-10-31T00:00:00Z',
            },
            {
                id: 102,
                title: 'About Us',
                slug: 'about',
                is_homepage: false,
                status: 'draft' as const,
                creator: { id: 1, name: 'Owner' },
                updater: { id: 1, name: 'Owner' },
                created_at: '2026-10-31T00:00:00Z',
                updated_at: '2026-10-31T00:00:00Z',
            },
        ];

        const defaultProps = {
            website: mockWebsite,
            pages: mockPages,
            memberships: [],
            templates: [],
            userRole: 'owner' as const,
            can: {
                update: true,
                delete: true,
                create_page: true,
                manage_members: true,
                manage_templates: true,
            },
        };

        it('displays Homepage badge, canonical / path, and disabled delete action on homepage row', () => {
            const wrapper = mount(ShowWebsite, { props: defaultProps });

            const homeRow = wrapper.find('[data-testid="homepage-row"]');
            expect(homeRow.exists()).toBe(true);
            expect(homeRow.find('[data-testid="homepage-badge"]').exists()).toBe(true);
            expect(homeRow.find('[data-testid="page-path"]').text()).toBe('/');
            expect(homeRow.text()).toContain('Set another page as homepage before deleting this page.');

            const disabledDeleteBtn = homeRow.find('[data-testid="delete-homepage-disabled-btn"]');
            expect(disabledDeleteBtn.exists()).toBe(true);
            expect(disabledDeleteBtn.attributes('disabled')).toBeDefined();
        });

        it('displays normal slug and Set as homepage button for eligible non-home pages', async () => {
            const wrapper = mount(ShowWebsite, { props: defaultProps });

            const aboutRow = wrapper.find('[data-testid="page-row-102"]');
            expect(aboutRow.exists()).toBe(true);
            expect(aboutRow.find('[data-testid="homepage-badge"]').exists()).toBe(false);
            expect(aboutRow.find('[data-testid="page-path"]').text()).toBe('/about');

            const setHomepageBtn = aboutRow.find('[data-testid="set-homepage-btn"]');
            expect(setHomepageBtn.exists()).toBe(true);

            await setHomepageBtn.trigger('click');
            expect(window.confirm).toHaveBeenCalled();
            expect(router.put).toHaveBeenCalledWith('/websites/1/homepage', {
                page_id: 102,
            });
        });

        it('includes a Visit Site action pointing to the canonical public root URL', () => {
            const wrapper = mount(ShowWebsite, { props: defaultProps });

            const visitLink = wrapper.find('[data-testid="visit-site-link"]');
            expect(visitLink.exists()).toBe(true);
            expect(visitLink.attributes('href')).toBe('/sites/acme-studio');
        });
    });

    describe('Pages/Edit.vue', () => {
        const mockWebsite = {
            id: 1,
            name: 'Acme Studio',
            slug: 'acme-studio',
        };

        it('shows effective public path / and helper text when editing the homepage', () => {
            const wrapper = mount(EditPage, {
                props: {
                    website: mockWebsite,
                    page: {
                        id: 101,
                        title: 'Home',
                        slug: 'home',
                        is_homepage: true,
                        status: 'draft',
                        created_at: '2026-10-31T00:00:00Z',
                        updated_at: '2026-10-31T00:00:00Z',
                    },
                    statuses: ['draft', 'published', 'archived'],
                },
            });

            const effectivePath = wrapper.find('[data-testid="effective-homepage-path"]');
            expect(effectivePath.exists()).toBe(true);
            expect(effectivePath.text()).toBe('Effective public path: /');
            expect(wrapper.text()).toContain('This page is currently the homepage with public path');
            expect(wrapper.find('input#slug').exists()).toBe(true);
        });

        it('does not show effective homepage path when editing an ordinary page', () => {
            const wrapper = mount(EditPage, {
                props: {
                    website: mockWebsite,
                    page: {
                        id: 102,
                        title: 'About Us',
                        slug: 'about',
                        is_homepage: false,
                        status: 'draft',
                        created_at: '2026-10-31T00:00:00Z',
                        updated_at: '2026-10-31T00:00:00Z',
                    },
                    statuses: ['draft', 'published', 'archived'],
                },
            });

            expect(wrapper.find('[data-testid="effective-homepage-path"]').exists()).toBe(false);
        });
    });
});
