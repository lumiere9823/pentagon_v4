// @vitest-environment happy-dom

import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Submissions, {
    type FormSubmissionItem,
    type PaginatedSubmissions,
} from '@/pages/Websites/Submissions.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div data-test="head"><slot /></div>' },
    Link: {
        props: ['href'],
        template: '<a :href="href" data-test="link"><slot /></a>',
    },
    router: {
        get: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
}));

const mockWebsite = {
    id: 42,
    name: 'Pentagon Studio',
    slug: 'pentagon-studio',
};

const mockSubmissionsList: FormSubmissionItem[] = [
    {
        id: 'sub-uuid-1',
        website_id: 42,
        page_id: 10,
        form_name: 'contact',
        payload: {
            name: 'Sarah Connor',
            email: 'sarah@sky.net',
            message: 'Need help protecting John',
        },
        ip_address: '192.168.1.100',
        user_agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        status: 'new',
        created_at: '2026-10-26T10:00:00Z',
        page: {
            id: 10,
            title: 'Contact Us',
            slug: 'contact',
        },
    },
    {
        id: 'sub-uuid-2',
        website_id: 42,
        page_id: null,
        form_name: 'newsletter',
        payload: {
            email: 'john@rebellion.org',
        },
        ip_address: '10.0.0.5',
        user_agent: 'Curl/7.68.0',
        status: 'read',
        created_at: '2026-10-25T15:30:00Z',
        page: null,
    },
];

const mockPaginated: PaginatedSubmissions = {
    data: mockSubmissionsList,
    current_page: 1,
    last_page: 1,
    total: 2,
    prev_page_url: null,
    next_page_url: null,
    links: [
        { url: null, label: '&laquo; Previous', active: false },
        { url: '/submissions?page=1', label: '1', active: true },
        { url: null, label: 'Next &raquo;', active: false },
    ],
};

const defaultProps = {
    website: mockWebsite,
    submissions: mockPaginated,
    distinctFormNames: ['contact', 'newsletter'],
    filters: { form_name: null, status: null },
    userRole: 'editor' as const,
    can: { manage_submissions: true },
};

describe('Websites/Submissions.vue - Administration UI', () => {
    it('renders the website header, submissions count, and breadcrumbs', () => {
        const wrapper = mount(Submissions, { props: defaultProps });

        expect(wrapper.text()).toContain('Form Submissions');
        expect(wrapper.text()).toContain('Pentagon Studio');
        expect(wrapper.text()).toContain('Total: 2 submissions received');
    });

    it('renders status filter pills and shows active filter state', () => {
        const wrapper = mount(Submissions, {
            props: {
                ...defaultProps,
                filters: { form_name: null, status: 'new' },
            },
        });

        const statusPills = wrapper.findAll('button').filter(
            (b) =>
                b.text().startsWith('All') ||
                ['New', 'Read', 'Archived', 'Spam'].includes(b.text()),
        );
        expect(statusPills.length).toBe(5);

        // Active pill has default variant styling
        const newPill = statusPills.find((b) => b.text() === 'New');
        expect(newPill?.classes()).toContain('bg-primary');
    });

    it('populates form name filter dropdown with distinct form names', () => {
        const wrapper = mount(Submissions, { props: defaultProps });

        const select = wrapper.find('select');
        expect(select.exists()).toBe(true);

        const options = select.findAll('option');
        expect(options.length).toBe(3); // All forms + contact + newsletter
        expect(options[0].text()).toBe('All Forms');
        expect(options[1].text()).toBe('contact');
        expect(options[2].text()).toBe('newsletter');
    });

    it('renders the submissions table with form names, source page, payload summary, and status badges', () => {
        const wrapper = mount(Submissions, { props: defaultProps });

        expect(wrapper.text()).toContain('contact');
        expect(wrapper.text()).toContain('Contact Us');
        expect(wrapper.text()).toContain('Sarah Connor');
        expect(wrapper.text()).toContain('sarah@sky.net');

        expect(wrapper.text()).toContain('newsletter');
        expect(wrapper.text()).toContain('Direct / Global');
        expect(wrapper.text()).toContain('john@rebellion.org');

        // Status badges
        const badges = wrapper.findAllComponents({ name: 'Badge' });
        const badgeTexts = badges.map((b) => b.text());
        expect(badgeTexts).toContain('new');
        expect(badgeTexts).toContain('read');
    });

    it('displays empty state when there are no submissions', () => {
        const wrapper = mount(Submissions, {
            props: {
                ...defaultProps,
                submissions: {
                    data: [],
                    current_page: 1,
                    last_page: 1,
                    total: 0,
                    prev_page_url: null,
                    next_page_url: null,
                    links: [],
                },
            },
        });

        expect(wrapper.text()).toContain('No submissions found');
        expect(wrapper.text()).toContain('Total: 0 submissions received');
    });

    it('opens detail inspection modal when inspect button is clicked', async () => {
        const wrapper = mount(Submissions, {
            props: defaultProps,
            attachTo: document.body,
        });

        const inspectButtons = wrapper.findAll('button[data-testid="inspect-btn"]');
        expect(inspectButtons.length).toBe(2);

        await inspectButtons[0].trigger('click');
        await nextTick();

        // Modal teleports to body
        const modal = document.body.querySelector('[role="dialog"]');
        expect(modal).not.toBeNull();
        expect(modal?.textContent).toContain('Submission Details');
        expect(modal?.textContent).toContain('Contact Us');
        expect(modal?.textContent).toContain('192.168.1.100');
        expect(modal?.textContent).toContain('Mozilla/5.0');
        expect(modal?.textContent).toContain('Need help protecting John');

        // Close modal
        const closeBtn = document.body.querySelector('button[aria-label="Close dialog"]') as HTMLButtonElement;
        expect(closeBtn).not.toBeNull();
        closeBtn.click();
        await nextTick();

        expect(document.body.querySelector('[role="dialog"]')).toBeNull();
        wrapper.unmount();
    });

    it('enforces viewer read-only policy: displays notice and hides mutation actions', async () => {
        const wrapper = mount(Submissions, {
            props: {
                ...defaultProps,
                userRole: 'viewer',
                can: { manage_submissions: false },
            },
            attachTo: document.body,
        });

        // Viewer notice is rendered
        expect(wrapper.text()).toContain('Viewer mode: Submissions are read-only.');

        // Delete buttons in table must not be rendered
        const deleteButtons = wrapper.findAll('button[data-testid="delete-btn"]');
        expect(deleteButtons.length).toBe(0);

        // Open inspect modal
        const inspectBtn = wrapper.find('button[data-testid="inspect-btn"]');
        await inspectBtn.trigger('click');
        await nextTick();

        const modal = document.body.querySelector('[role="dialog"]');
        expect(modal).not.toBeNull();

        // Inside modal: status mutation buttons must not exist for viewer
        expect(modal?.textContent).not.toContain('Mark as Read');
        expect(modal?.textContent).not.toContain('Mark as Archived');
        expect(modal?.textContent).not.toContain('Mark as Spam');

        wrapper.unmount();
    });

    it('provides status transition actions and delete confirmation dialog for authorized editors', async () => {
        const wrapper = mount(Submissions, {
            props: {
                ...defaultProps,
                userRole: 'editor',
                can: { manage_submissions: true },
            },
            attachTo: document.body,
        });

        // Delete button in table is present for editor
        const deleteButtons = wrapper.findAll('button[data-testid="delete-btn"]');
        expect(deleteButtons.length).toBe(2);

        // Clicking delete button opens alertdialog
        await deleteButtons[0].trigger('click');
        await nextTick();

        const alertdialog = document.body.querySelector('[role="alertdialog"]');
        expect(alertdialog).not.toBeNull();
        expect(alertdialog?.textContent).toContain('Delete Form Submission');
        expect(alertdialog?.textContent).toContain('This action cannot be undone.');

        // Cancel deletion closes dialog
        const cancelBtn = Array.from(document.body.querySelectorAll('button')).find(
            (b) => b.textContent?.trim() === 'Cancel',
        );
        expect(cancelBtn).toBeDefined();
        cancelBtn!.click();
        await nextTick();

        expect(document.body.querySelector('[role="alertdialog"]')).toBeNull();
        wrapper.unmount();
    });
});
