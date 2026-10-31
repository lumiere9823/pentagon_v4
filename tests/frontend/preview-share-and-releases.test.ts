// @vitest-environment happy-dom

import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ReleasesModal from '@/components/editor/ReleasesModal.vue';
import SharePreviewModal from '@/components/editor/SharePreviewModal.vue';
import * as httpClient from '@/lib/httpClient';

vi.mock('@/lib/httpClient', () => ({
    requestJson: vi.fn(),
}));

describe('SharePreviewModal.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        document.body.innerHTML = '';
        Object.defineProperty(navigator, 'clipboard', {
            value: {
                writeText: vi.fn().mockResolvedValue(undefined),
            },
            configurable: true,
            writable: true,
        });
        window.confirm = vi.fn().mockReturnValue(true);
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('generates a signed preview link on open and displays it', async () => {
        vi.mocked(httpClient.requestJson).mockResolvedValueOnce({
            ok: true,
            status: 200,
            statusText: 'OK',
            data: {
                signed_url: 'https://example.com/preview/pages/10?signature=abcdef123456',
                expires_at: '2026-10-26T18:00:00Z',
            },
        });

        const wrapper = mount(SharePreviewModal, {
            props: {
                open: true,
                websiteId: 1,
                pageId: 10,
                pageTitle: 'Home Page',
            },
            attachTo: document.body,
        });

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));
        await nextTick();

        expect(httpClient.requestJson).toHaveBeenCalledWith(
            '/websites/1/pages/10/preview/share-link',
            { method: 'POST' },
        );

        const modal = document.body.querySelector('[role="dialog"]');
        expect(modal).not.toBeNull();
        expect(modal?.textContent).toContain('Share Draft Preview');
        expect(modal?.textContent).toContain('Home Page');

        const input = document.body.querySelector('input[aria-label="Signed preview URL"]') as HTMLInputElement;
        expect(input).not.toBeNull();
        expect(input.value).toBe('https://example.com/preview/pages/10?signature=abcdef123456');

        wrapper.unmount();
    });

    it('allows copying the generated preview URL to clipboard', async () => {
        vi.mocked(httpClient.requestJson).mockResolvedValueOnce({
            ok: true,
            status: 200,
            statusText: 'OK',
            data: {
                signed_url: 'https://example.com/preview/pages/10?signature=secret',
                expires_at: '2026-10-26T18:00:00Z',
            },
        });

        const wrapper = mount(SharePreviewModal, {
            props: {
                open: true,
                websiteId: 1,
                pageId: 10,
            },
            attachTo: document.body,
        });

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));
        await nextTick();

        const copyBtn = document.body.querySelector('button[data-testid="copy-share-link-btn"]') as HTMLButtonElement;
        expect(copyBtn).not.toBeNull();
        copyBtn.click();
        await nextTick();

        expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
            'https://example.com/preview/pages/10?signature=secret',
        );

        wrapper.unmount();
    });

    it('handles failure when generating signed preview link', async () => {
        vi.mocked(httpClient.requestJson).mockResolvedValueOnce({
            ok: false,
            status: 500,
            statusText: 'Internal Server Error',
            data: { message: 'Server signature failed' },
        });

        const wrapper = mount(SharePreviewModal, {
            props: {
                open: true,
                websiteId: 1,
                pageId: 10,
            },
            attachTo: document.body,
        });

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));
        await nextTick();

        const alert = document.body.querySelector('[role="alert"]');
        expect(alert).not.toBeNull();
        expect(alert?.textContent).toContain('Server signature failed');

        wrapper.unmount();
    });
});

describe('ReleasesModal.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        document.body.innerHTML = '';
        window.confirm = vi.fn().mockReturnValue(true);
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    const mockReleases = [
        {
            id: 102,
            page_id: 5,
            release_number: 2,
            status: 'active',
            created_at: '2026-10-26T12:00:00Z',
            creator: { id: 1, name: 'Alice Editor' },
            revision: {
                id: 20,
                revision_number: 3,
                change_summary: 'Updated holiday hero',
            },
        },
        {
            id: 101,
            page_id: 5,
            release_number: 1,
            status: 'rolled_back',
            created_at: '2026-10-25T09:00:00Z',
            creator: { id: 2, name: 'Bob Owner' },
            revision: {
                id: 15,
                revision_number: 1,
                change_summary: 'Initial launch',
            },
        },
    ];

    it('fetches and renders page releases with active status badge', async () => {
        vi.mocked(httpClient.requestJson).mockResolvedValueOnce({
            ok: true,
            status: 200,
            statusText: 'OK',
            data: { releases: mockReleases },
        });

        const wrapper = mount(ReleasesModal, {
            props: {
                open: true,
                websiteId: 1,
                pageId: 5,
                canEdit: true,
            },
            attachTo: document.body,
        });

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));
        await nextTick();

        expect(httpClient.requestJson).toHaveBeenCalledWith(
            '/websites/1/pages/5/releases?limit=50',
            {},
        );

        const modal = document.body.querySelector('[role="dialog"]');
        expect(modal).not.toBeNull();
        expect(modal?.textContent).toContain('Public Release History & Rollback');
        expect(modal?.textContent).toContain('Release #2');
        expect(modal?.textContent).toContain('Live Active');
        expect(modal?.textContent).toContain('Release #1');
        expect(modal?.textContent).toContain('Past Release');
        expect(modal?.textContent).toContain('Initial launch');

        wrapper.unmount();
    });

    it('allows rolling back to a past release when confirmed', async () => {
        vi.mocked(httpClient.requestJson)
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                statusText: 'OK',
                data: { releases: mockReleases },
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                statusText: 'OK',
                data: {
                    success: true,
                    message: 'Successfully rolled back to Release #1.',
                },
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                statusText: 'OK',
                data: { releases: mockReleases },
            });

        const wrapper = mount(ReleasesModal, {
            props: {
                open: true,
                websiteId: 1,
                pageId: 5,
                canEdit: true,
            },
            attachTo: document.body,
        });

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));
        await nextTick();

        const rollbackBtn = document.body.querySelector('button[data-testid="rollback-release-btn"]') as HTMLButtonElement;
        expect(rollbackBtn).not.toBeNull();
        rollbackBtn.click();

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));
        await nextTick();

        expect(window.confirm).toHaveBeenCalled();
        expect(httpClient.requestJson).toHaveBeenCalledWith(
            '/websites/1/pages/5/releases/101/rollback',
            { method: 'POST' },
        );

        expect(wrapper.emitted('rolledBack')).toBeDefined();
        expect(wrapper.emitted('rolledBack')?.[0]?.[0]).toMatchObject({
            id: 101,
            release_number: 1,
        });

        wrapper.unmount();
    });

    it('displays empty state if no releases have been published yet', async () => {
        vi.mocked(httpClient.requestJson).mockResolvedValueOnce({
            ok: true,
            status: 200,
            statusText: 'OK',
            data: { releases: [] },
        });

        const wrapper = mount(ReleasesModal, {
            props: {
                open: true,
                websiteId: 1,
                pageId: 5,
                canEdit: true,
            },
            attachTo: document.body,
        });

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));
        await nextTick();

        const modal = document.body.querySelector('[role="dialog"]');
        expect(modal?.textContent).toContain('No releases published yet');

        wrapper.unmount();
    });
});
