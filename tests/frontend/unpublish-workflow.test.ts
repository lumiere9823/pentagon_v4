// @vitest-environment happy-dom

import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import EditorToolbar from '@/components/editor/EditorToolbar.vue';
import Show from '@/pages/Websites/Show.vue';
import { router } from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div data-test="head"><slot /></div>' },
    Link: {
        props: ['href'],
        template: '<a :href="href" data-test="link"><slot /></a>',
    },
    useForm: vi.fn(() => ({
        name: '',
        slug: '',
        email: '',
        role: 'editor',
        post: vi.fn(),
        put: vi.fn(),
        reset: vi.fn(),
    })),
    router: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

const mockWebsite = {
    id: 1,
    name: 'Acme Studio',
    slug: 'acme-studio',
    status: 'active',
    created_at: '2026-10-26T00:00:00Z',
};

describe('Unpublish Workflow in EditorToolbar.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const defaultProps = {
        website: { id: 1, name: 'Acme Studio', slug: 'acme-studio' },
        page: { id: 10, title: 'Landing Page', slug: 'landing', status: 'published' },
        viewport: 'desktop' as const,
        leftSidebarOpen: true,
        rightPanelOpen: true,
        userRole: 'editor',
        canEdit: true,
    };

    const mountToolbar = (props = {}) =>
        mount(EditorToolbar, {
            props: { ...defaultProps, ...props },
            global: {
                stubs: {
                    SidebarTrigger: { template: '<button data-test="sidebar-trigger" />' },
                    TooltipProvider: { template: '<div><slot /></div>' },
                    Tooltip: { template: '<div><slot /></div>' },
                    TooltipTrigger: { template: '<div><slot /></div>' },
                    TooltipContent: { template: '<div><slot /></div>' },
                },
            },
        });

    it('renders unpublish button when page is published and canEdit is true', () => {
        const wrapper = mountToolbar();

        const unpublishBtn = wrapper.find('[data-testid="unpublish-btn"]');
        expect(unpublishBtn.exists()).toBe(true);
        expect(unpublishBtn.text()).toContain('Unpublish');
    });

    it('does not render unpublish button when page is draft', () => {
        const wrapper = mountToolbar({
            page: { ...defaultProps.page, status: 'draft' },
        });

        expect(wrapper.find('[data-testid="unpublish-btn"]').exists()).toBe(false);
    });

    it('does not render unpublish button when canEdit is false (viewer role)', () => {
        const wrapper = mountToolbar({
            canEdit: false,
            userRole: 'viewer',
        });

        expect(wrapper.find('[data-testid="unpublish-btn"]').exists()).toBe(false);
    });

    it('emits unpublish event when clicked', async () => {
        const wrapper = mountToolbar();

        const unpublishBtn = wrapper.find('[data-testid="unpublish-btn"]');
        await unpublishBtn.trigger('click');

        expect(wrapper.emitted('unpublish')).toHaveLength(1);
    });
});

describe('Unpublish Workflow in Websites/Show.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.confirm = vi.fn().mockReturnValue(true);
    });

    const mockPages = [
        {
            id: 101,
            title: 'Published Page',
            slug: 'published-page',
            status: 'published' as const,
            creator: { id: 1, name: 'Owner' },
            updater: null,
            created_at: '2026-10-26T00:00:00Z',
            updated_at: '2026-10-26T00:00:00Z',
        },
        {
            id: 102,
            title: 'Draft Page',
            slug: 'draft-page',
            status: 'draft' as const,
            creator: { id: 1, name: 'Owner' },
            updater: null,
            created_at: '2026-10-26T00:00:00Z',
            updated_at: '2026-10-26T00:00:00Z',
        },
    ];

    it('renders unpublish button for published pages and not draft pages', () => {
        const wrapper = mount(Show, {
            props: {
                website: mockWebsite,
                pages: mockPages,
                memberships: [],
                templates: [],
                userRole: 'owner',
                can: {
                    update: true,
                    delete: true,
                    create_page: true,
                    manage_members: true,
                    manage_templates: true,
                },
            },
        });

        const unpublishButtons = wrapper.findAll('[data-testid="unpublish-page-btn"]');
        expect(unpublishButtons).toHaveLength(1);
    });

    it('does not render unpublish button for viewers lacking create_page permission', () => {
        const wrapper = mount(Show, {
            props: {
                website: mockWebsite,
                pages: mockPages,
                memberships: [],
                templates: [],
                userRole: 'viewer',
                can: {
                    update: false,
                    delete: false,
                    create_page: false,
                    manage_members: false,
                    manage_templates: false,
                },
            },
        });

        const unpublishButtons = wrapper.findAll('[data-testid="unpublish-page-btn"]');
        expect(unpublishButtons).toHaveLength(0);
    });

    it('prompts confirmation and calls router.post to unpublish endpoint', async () => {
        const wrapper = mount(Show, {
            props: {
                website: mockWebsite,
                pages: mockPages,
                memberships: [],
                templates: [],
                userRole: 'editor',
                can: {
                    update: true,
                    delete: false,
                    create_page: true,
                    manage_members: false,
                    manage_templates: false,
                },
            },
        });

        const unpublishBtn = wrapper.find('[data-testid="unpublish-page-btn"]');
        await unpublishBtn.trigger('click');

        expect(window.confirm).toHaveBeenCalledWith(
            expect.stringContaining('Are you sure you want to unpublish "Published Page"?'),
        );
        expect(router.post).toHaveBeenCalledWith('/websites/1/pages/101/unpublish');
    });

    it('does not dispatch router.post if user cancels unpublish confirmation', async () => {
        vi.mocked(window.confirm).mockReturnValueOnce(false);

        const wrapper = mount(Show, {
            props: {
                website: mockWebsite,
                pages: mockPages,
                memberships: [],
                templates: [],
                userRole: 'editor',
                can: {
                    update: true,
                    delete: false,
                    create_page: true,
                    manage_members: false,
                    manage_templates: false,
                },
            },
        });

        const unpublishBtn = wrapper.find('[data-testid="unpublish-page-btn"]');
        await unpublishBtn.trigger('click');

        expect(window.confirm).toHaveBeenCalled();
        expect(router.post).not.toHaveBeenCalled();
    });
});
