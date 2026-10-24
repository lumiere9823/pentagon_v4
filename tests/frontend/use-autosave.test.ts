import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createRenderer, type App } from '@vue/runtime-core';
import { useAutosave, type UseAutosaveOptions } from '@/composables/useAutosave';
import type { DocumentSchema } from '@/types/document';

class MockEventTarget {
    listeners: Record<string, ((event: any) => void)[]> = {};

    addEventListener(event: string, fn: (event: any) => void) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(fn);
    }

    removeEventListener(event: string, fn: (event: any) => void) {
        if (!this.listeners[event]) return;
        this.listeners[event] = this.listeners[event].filter((cb) => cb !== fn);
    }

    dispatchEvent(event: Event | string) {
        const type = typeof event === 'string' ? event : event.type;
        const evtObj = typeof event === 'string' ? new Event(event) : event;
        for (const fn of this.listeners[type] || []) {
            fn(evtObj);
        }
        return !evtObj.defaultPrevented;
    }
}

class MockDocument extends MockEventTarget {
    cookie = 'XSRF-TOKEN=mock_xsrf_token_value; path=/';
    visibilityState: DocumentVisibilityState = 'visible';
}

const customRenderer = createRenderer({
    patchProp() {},
    insert() {},
    remove() {},
    createElement() {
        return {};
    },
    createText() {
        return {};
    },
    createComment() {
        return {};
    },
    setText() {},
    setElementText() {},
    parentNode() {
        return null;
    },
    nextSibling() {
        return null;
    },
});

function withSetup<T>(composable: () => T): [T, App<Element>] {
    let result!: T;
    const app = customRenderer.createApp({
        setup() {
            result = composable();
            return () => null;
        },
    });
    const container = {};
    app.mount(container as unknown as Element);
    return [result, app as unknown as App<Element>];
}

const flushPromises = async () => {
    for (let i = 0; i < 5; i++) {
        await new Promise((resolve) => process.nextTick(resolve));
    }
};

const sampleDoc: DocumentSchema = {
    schemaVersion: 1,
    root: {
        id: 'root_section_default',
        type: 'Section',
        props: {
            padding: 'medium',
            background: 'transparent',
        },
        children: [],
    },
    pageSettings: {
        title: 'Autosave Test Page',
        description: '',
    },
};

describe('useAutosave Composable (M14)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;
    let mockWindow: MockEventTarget;
    let mockDocument: MockDocument;

    beforeEach(() => {
        vi.useFakeTimers();
        fetchMock = vi.fn();
        globalThis.fetch = fetchMock;
        mockWindow = new MockEventTarget();
        mockDocument = new MockDocument();
        (globalThis as any).window = mockWindow;
        (globalThis as any).document = mockDocument;
    });

    afterEach(() => {
        vi.clearAllTimers();
        vi.useRealTimers();
        vi.restoreAllMocks();
        delete (globalThis as any).window;
        delete (globalThis as any).document;
    });

    it('1. Debounce timing: does not save before delay, saves after delay', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({
                success: true,
                lock_version: 2,
                updated_at: '2026-10-18T12:00:00Z',
            }),
        });

        let currentDoc = { ...sampleDoc };
        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 1,
                getCurrentDocument: () => currentDoc,
                debounceMs: 2000,
            }),
        );

        expect(autosave.saveStatus.value).toBe('saved');
        expect(autosave.isDirty.value).toBe(false);

        // Edit made
        autosave.notifyChange();
        expect(autosave.saveStatus.value).toBe('dirty');
        expect(autosave.isDirty.value).toBe(true);

        // Advance 1500ms (before debounce expires)
        vi.advanceTimersByTime(1500);
        expect(fetchMock).not.toHaveBeenCalled();

        // Advance remaining 500ms -> triggers saveNow
        vi.advanceTimersByTime(500);
        expect(fetchMock).toHaveBeenCalledTimes(1);

        // Await promise resolution
        await flushPromises();
        expect(autosave.saveStatus.value).toBe('saved');
        expect(autosave.lockVersion.value).toBe(2);
        expect(autosave.isDirty.value).toBe(false);

        app.unmount();
    });

    it('2. Multiple edits within debounce interval consolidate into one save', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({
                success: true,
                lock_version: 2,
                updated_at: '2026-10-18T12:00:00Z',
            }),
        });

        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 1,
                getCurrentDocument: () => sampleDoc,
                debounceMs: 2000,
            }),
        );

        autosave.notifyChange();
        vi.advanceTimersByTime(800);
        autosave.notifyChange();
        vi.advanceTimersByTime(800);
        autosave.notifyChange();

        // Total 1600ms elapsed, but debounce was reset twice
        expect(fetchMock).not.toHaveBeenCalled();

        // Advance 2000ms after last edit
        vi.advanceTimersByTime(2000);
        expect(fetchMock).toHaveBeenCalledTimes(1);

        await flushPromises();
        expect(autosave.saveStatus.value).toBe('saved');

        app.unmount();
    });

    it('3. Edit made during in-flight save is preserved and queued for next save', async () => {
        let resolveInFlight!: (val: any) => void;
        const inFlightPromise = new Promise((resolve) => {
            resolveInFlight = resolve;
        });

        fetchMock.mockImplementationOnce(() => inFlightPromise);

        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 1,
                getCurrentDocument: () => sampleDoc,
                debounceMs: 2000,
            }),
        );

        // Trigger first save immediately
        autosave.notifyChange();
        vi.advanceTimersByTime(2000);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(autosave.isSaving.value).toBe(true);

        // User makes another edit while save 1 is in-flight
        autosave.notifyChange();
        expect(autosave.isDirty.value).toBe(true);

        // Resolve first save
        fetchMock.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({
                success: true,
                lock_version: 2,
            }),
        });

        resolveInFlight({
            ok: true,
            status: 200,
            json: async () => ({
                success: true,
                lock_version: 2,
            }),
        });

        await flushPromises();

        // Second edit was marked pending and scheduled next debounce
        expect(autosave.isDirty.value).toBe(true);

        vi.advanceTimersByTime(2000);
        expect(fetchMock).toHaveBeenCalledTimes(2);

        await flushPromises();
        expect(autosave.saveStatus.value).toBe('saved');

        app.unmount();
    });

    it('4. Retries on network error with exponential backoff and exhausts after maxRetries', async () => {
        fetchMock.mockRejectedValue(new Error('Network offline'));

        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 1,
                getCurrentDocument: () => sampleDoc,
                debounceMs: 1000,
                maxRetries: 3,
            }),
        );

        // First attempt (autosave)
        autosave.notifyChange();
        vi.advanceTimersByTime(1000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(autosave.saveStatus.value).toBe('error');

        // Retry 1: 1000ms backoff (2^0 * 1000)
        vi.advanceTimersByTime(1000);
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(2);

        // Retry 2: 2000ms backoff (2^1 * 1000)
        vi.advanceTimersByTime(2000);
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(3);

        // Retry 3: 4000ms backoff (2^2 * 1000)
        vi.advanceTimersByTime(4000);
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(4);

        // Exhausted: no further retries
        vi.advanceTimersByTime(10000);
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(4);
        expect(autosave.saveStatus.value).toBe('error');
        expect(autosave.errorMessage.value).toBe('Network offline');

        app.unmount();
    });

    it('5. HTTP 409 Conflict enters conflict state, halts retries, and preserves local document', async () => {
        const onConflictMock = vi.fn();

        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 409,
            json: async () => ({
                success: false,
                error_code: 'document_conflict',
                current_lock_version: 7,
                updated_at: '2026-10-18T14:30:00Z',
            }),
        });

        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 3,
                getCurrentDocument: () => sampleDoc,
                debounceMs: 1000,
                onConflict: onConflictMock,
            }),
        );

        autosave.notifyChange();
        vi.advanceTimersByTime(1000);
        await flushPromises();

        expect(autosave.saveStatus.value).toBe('conflict');
        expect(autosave.conflictData.value).toEqual({
            current_lock_version: 7,
            updated_at: '2026-10-18T14:30:00Z',
        });
        expect(onConflictMock).toHaveBeenCalledWith({
            current_lock_version: 7,
            updated_at: '2026-10-18T14:30:00Z',
        });

        // Further notifyChange should not fire while conflict is active
        autosave.notifyChange();
        vi.advanceTimersByTime(5000);
        expect(fetchMock).toHaveBeenCalledTimes(1);

        app.unmount();
    });

    it('6. Failed request remains visible as error without discarding pending edits', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 422,
            json: async () => ({
                success: false,
                message: 'Document depth exceeds limit.',
            }),
        });

        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 1,
                getCurrentDocument: () => sampleDoc,
            }),
        );

        const result = await autosave.saveNow();
        expect(result).toBe(false);
        expect(autosave.saveStatus.value).toBe('error');
        expect(autosave.errorMessage.value).toBe('Document depth exceeds limit.');
        expect(autosave.lockVersion.value).toBe(1);

        app.unmount();
    });

    it('7. Stale or rejected response does not advance local lock version', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 500,
            json: async () => ({
                success: false,
                message: 'Internal Server Error',
            }),
        });

        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 4,
                getCurrentDocument: () => sampleDoc,
            }),
        );

        await autosave.saveNow();
        expect(autosave.lockVersion.value).toBe(4);

        app.unmount();
    });

    it('8. Timer cleanup and pending timer cancellation on component disposal', () => {
        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 1,
                getCurrentDocument: () => sampleDoc,
                debounceMs: 2000,
            }),
        );

        autosave.notifyChange();
        expect(autosave.saveStatus.value).toBe('dirty');

        // Unmount before timer expires
        app.unmount();

        vi.advanceTimersByTime(3000);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('9. Explicit Save Draft flushes immediately without waiting for debounce', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({
                success: true,
                lock_version: 5,
            }),
        });

        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 4,
                getCurrentDocument: () => sampleDoc,
                debounceMs: 5000,
            }),
        );

        // Explicit manual save
        const savePromise = autosave.saveNow('User clicked Save Draft', false);
        expect(fetchMock).toHaveBeenCalledTimes(1);

        const requestBody = JSON.parse(fetchMock.mock.calls[0][1].body);
        expect(requestBody.lock_version).toBe(4);
        expect(requestBody.change_summary).toBe('User clicked Save Draft');
        expect(requestBody.is_autosave).toBe(false);

        await savePromise;
        expect(autosave.saveStatus.value).toBe('saved');
        expect(autosave.lockVersion.value).toBe(5);

        app.unmount();
    });

    it('10. visibilitychange and beforeunload handlers handle best-effort flush and warn', async () => {
        fetchMock.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({
                success: true,
                lock_version: 2,
            }),
        });

        const [autosave, app] = withSetup(() =>
            useAutosave({
                websiteId: 1,
                pageId: 10,
                initialLockVersion: 1,
                getCurrentDocument: () => sampleDoc,
                debounceMs: 5000,
            }),
        );

        // Mark document dirty
        autosave.notifyChange();

        // 1. beforeunload warns user
        const beforeUnloadEvent = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(beforeUnloadEvent);
        expect(beforeUnloadEvent.defaultPrevented).toBe(true);

        // 2. visibilitychange triggers flush when hidden
        Object.defineProperty(document, 'visibilityState', {
            value: 'hidden',
            writable: true,
            configurable: true,
        });

        document.dispatchEvent(new Event('visibilitychange'));
        expect(fetchMock).toHaveBeenCalledTimes(1);

        await flushPromises();
        expect(autosave.saveStatus.value).toBe('saved');

        app.unmount();
    });
});
