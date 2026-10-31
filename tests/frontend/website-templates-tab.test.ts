// @vitest-environment happy-dom

import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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

const mockTemplates = [
    {
        id: 10,
        name: 'Landing Page Hero',
        slug: 'landing-page-hero',
        category: 'landing',
        description: 'High converting landing hero layout.',
        is_active: true,
        created_at: '2026-10-26T10:00:00Z',
        creator: { id: 1, name: 'Alice' },
    },
    {
        id: 11,
        name: 'Pricing Grid',
        slug: 'pricing-grid',
        category: 'pricing',
        description: 'Three-tier pricing table.',
        is_active: true,
        created_at: '2026-10-26T11:00:00Z',
        creator: { id: 2, name: 'Bob' },
    },
];

const defaultProps = {
    website: mockWebsite,
    pages: [],
    memberships: [],
    templates: mockTemplates,
    userRole: 'owner' as const,
    can: {
        update: true,
        delete: true,
        create_page: true,
        manage_members: true,
        manage_templates: true,
    },
};

describe('Websites/Show.vue - Templates Tab', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        window.confirm = vi.fn().mockReturnValue(true);
    });

    it('renders the templates tab button with count', () => {
        const wrapper = mount(Show, { props: defaultProps });

        expect(wrapper.text()).toContain('Templates (2)');
    });

    it('switches to templates tab and displays custom templates', async () => {
        const wrapper = mount(Show, { props: defaultProps });

        const templateTabBtn = wrapper.findAll('button').find(
            (b) => b.text().includes('Templates (2)'),
        );
        expect(templateTabBtn).toBeDefined();

        await templateTabBtn!.trigger('click');
        await nextTick();

        const cards = wrapper.findAll('[data-testid="template-card"]');
        expect(cards.length).toBe(2);

        expect(wrapper.text()).toContain('Landing Page Hero');
        expect(wrapper.text()).toContain('High converting landing hero layout.');
        expect(wrapper.text()).toContain('By Alice');

        expect(wrapper.text()).toContain('Pricing Grid');
        expect(wrapper.text()).toContain('Three-tier pricing table.');
        expect(wrapper.text()).toContain('By Bob');

        // Check "Use Template" link
        const links = wrapper.findAll('a[data-test="link"]');
        const useLinks = links.filter((l) => l.attributes('href')?.includes('template_id=10'));
        expect(useLinks.length).toBeGreaterThan(0);
    });

    it('allows deleting a template when confirmed by authorized user', async () => {
        const wrapper = mount(Show, { props: defaultProps });

        const templateTabBtn = wrapper.findAll('button').find(
            (b) => b.text().includes('Templates (2)'),
        );
        await templateTabBtn!.trigger('click');
        await nextTick();

        const deleteButtons = wrapper.findAll('button[title="Delete Template"]');
        expect(deleteButtons.length).toBe(2);

        await deleteButtons[0].trigger('click');
        await nextTick();

        expect(window.confirm).toHaveBeenCalled();
        expect(router.delete).toHaveBeenCalledWith('/websites/1/templates/10');
    });

    it('hides delete button when user lacks manage_templates permission', async () => {
        const wrapper = mount(Show, {
            props: {
                ...defaultProps,
                userRole: 'viewer',
                can: {
                    ...defaultProps.can,
                    manage_templates: false,
                },
            },
        });

        const templateTabBtn = wrapper.findAll('button').find(
            (b) => b.text().includes('Templates (2)'),
        );
        await templateTabBtn!.trigger('click');
        await nextTick();

        const deleteButtons = wrapper.findAll('button[title="Delete Template"]');
        expect(deleteButtons.length).toBe(0);
    });

    it('displays empty state when website has no custom templates', async () => {
        const wrapper = mount(Show, {
            props: {
                ...defaultProps,
                templates: [],
            },
        });

        const templateTabBtn = wrapper.findAll('button').find(
            (b) => b.text().includes('Templates (0)'),
        );
        await templateTabBtn!.trigger('click');
        await nextTick();

        expect(wrapper.text()).toContain('No custom templates yet');
    });
});
