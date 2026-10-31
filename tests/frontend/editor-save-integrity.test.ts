// @vitest-environment happy-dom

import { defineComponent, h, nextTick, ref } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import EditorShell from '@/components/editor/EditorShell.vue';
import type { DocumentSchema } from '@/types/document';

vi.mock('@/composables/useComponentRegistry', () => ({
    createComponentRegistrySession: () => ({
        components: ref({
            Heading: {
                type: 'Heading',
                label: 'Heading',
                category: 'content',
                allowChildren: false,
                canBeRoot: false,
                props: {
                    text: { type: 'string', default: '' },
                },
            },
        }),
        isLoading: ref(false),
        errorMessage: ref(null),
        load: vi.fn(),
        retry: vi.fn(),
        dispose: vi.fn(),
    }),
}));

const EditorToolbarStub = defineComponent({
    name: 'EditorToolbar',
    props: {
        saveStatus: { type: String, required: true },
        isDirty: { type: Boolean, required: true },
    },
    emits: ['publish', 'undo', 'redo', 'save'],
    template: `
        <div>
            <output data-testid="save-status">{{ saveStatus }}</output>
            <output data-testid="is-dirty">{{ isDirty }}</output>
            <button type="button" @click="$emit('publish')">Publish</button>
            <button type="button" @click="$emit('undo')">Undo</button>
            <button type="button" @click="$emit('redo')">Redo</button>
            <button type="button" @click="$emit('save')">Save</button>
        </div>
    `,
});

const EditorCanvasStub = defineComponent({
    name: 'EditorCanvas',
    emits: ['select'],
    template: `
        <button type="button" @click="$emit('select', 'heading_1')">
            Select heading
        </button>
    `,
});

const InspectorPanelStub = defineComponent({
    name: 'InspectorPanel',
    props: {
        selectedNode: { type: Object, default: null },
    },
    emits: ['update-prop', 'update-style', 'reset-style'],
    template: `
        <div v-if="selectedNode">
            <button
                type="button"
                @click="$emit('update-prop', { key: 'text', value: 'First edit' })"
            >
                Edit heading
            </button>
            <button
                type="button"
                @click="$emit('update-prop', { key: 'text', value: 'Second edit' })"
            >
                Edit heading again
            </button>
            <button
                type="button"
                @click="$emit('update-prop', { key: 'text', value: 'Original' })"
            >
                Repeat original value
            </button>
            <button
                type="button"
                @click="$emit('update-style', { viewport: 'mobile', key: 'align', value: 'center' })"
            >
                Set mobile alignment
            </button>
            <button
                type="button"
                @click="$emit('reset-style', { viewport: 'mobile', key: 'align' })"
            >
                Reset mobile alignment
            </button>
        </div>
    `,
});

const initialDocument: DocumentSchema = {
    schemaVersion: 1,
    root: {
        id: 'root_section',
        type: 'Section',
        props: { padding: 'medium', background: 'transparent' },
        children: [
            {
                id: 'heading_1',
                type: 'Heading',
                props: { text: 'Original', level: 2, align: 'left' },
            },
        ],
    },
    pageSettings: { title: 'Integrity test', description: '' },
};

const RevisionsModalStub = defineComponent({
    name: 'RevisionsModal',
    emits: ['restored'],
    setup(_props, { emit }) {
        return () =>
            h(
                'button',
                {
                    type: 'button',
                    onClick: () =>
                        emit('restored', {
                            document_json: JSON.parse(
                                JSON.stringify(initialDocument),
                            ),
                            lock_version: 5,
                        }),
                },
                'Restore identical revision',
            );
    },
});

function mountEditor(): VueWrapper {
    return mount(EditorShell, {
        props: {
            website: { id: 7, name: 'Test Site', slug: 'test-site' },
            page: {
                id: 11,
                title: 'Test Page',
                slug: 'test-page',
                status: 'draft',
                lock_version: 1,
            },
            userRole: 'owner',
            can: { edit: true, delete: true },
            initialDocument: JSON.parse(JSON.stringify(initialDocument)),
        },
        global: {
            stubs: {
                EditorToolbar: EditorToolbarStub,
                EditorCanvas: EditorCanvasStub,
                InspectorPanel: InspectorPanelStub,
                ComponentLibrary: true,
                ConflictModal: true,
                PublishSuccessModal: true,
                RevisionsModal: RevisionsModalStub,
                SaveAsTemplateModal: true,
                SeoSettingsModal: true,
                PanelLeftOpen: true,
                SlidersHorizontal: true,
                transition: false,
            },
        },
    });
}

async function flushPromises() {
    for (let i = 0; i < 10; i++) {
        await Promise.resolve();
        await nextTick();
    }
}

function buttonByText(wrapper: VueWrapper, text: string) {
    const button = wrapper
        .findAll('button')
        .find((candidate) => candidate.text().trim() === text);
    if (!button) throw new Error(`Button not found: ${text}`);
    return button;
}

describe('editor save and publish integrity', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.clearAllTimers();
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('marks the first Inspector edit dirty and saves that document before publish', async () => {
        const fetchMock = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                const url = String(input);
                if (init?.method === 'PUT') {
                    return {
                        ok: true,
                        status: 200,
                        statusText: 'OK',
                        json: async () => ({ success: true, lock_version: 2 }),
                    };
                }

                return {
                    ok: true,
                    status: 200,
                    statusText: 'OK',
                    json: async () => ({
                        success: true,
                        public_url: `${url}/public`,
                    }),
                };
            },
        );
        globalThis.fetch = fetchMock as typeof fetch;
        const wrapper = mountEditor();

        await buttonByText(wrapper, 'Select heading').trigger('click');
        await buttonByText(wrapper, 'Edit heading').trigger('click');
        await nextTick();

        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('dirty');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');

        await buttonByText(wrapper, 'Publish').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(String(fetchMock.mock.calls[0][0])).toContain('/content');
        expect(fetchMock.mock.calls[0][1]?.method).toBe('PUT');
        const savedPayload = JSON.parse(
            String(fetchMock.mock.calls[0][1]?.body),
        );
        expect(savedPayload.document_json.root.children[0].props.text).toBe(
            'First edit',
        );
        expect(String(fetchMock.mock.calls[1][0])).toContain('/publish');

        wrapper.unmount();
    });

    it('persists an edit made during the pre-publish save before publishing', async () => {
        let resolveFirstSave!: (response: Response) => void;
        const firstSave = new Promise<Response>((resolve) => {
            resolveFirstSave = resolve;
        });
        let putCount = 0;
        const fetchMock = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                if (init?.method === 'PUT') {
                    putCount++;
                    if (putCount === 1) return firstSave;
                    return {
                        ok: true,
                        status: 200,
                        statusText: 'OK',
                        json: async () => ({ success: true, lock_version: 3 }),
                    } as Response;
                }

                return {
                    ok: true,
                    status: 200,
                    statusText: 'OK',
                    json: async () => ({
                        success: true,
                        public_url: `${String(input)}/public`,
                    }),
                } as Response;
            },
        );
        globalThis.fetch = fetchMock as typeof fetch;
        const wrapper = mountEditor();

        await buttonByText(wrapper, 'Select heading').trigger('click');
        await buttonByText(wrapper, 'Edit heading').trigger('click');
        await nextTick();
        await buttonByText(wrapper, 'Publish').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        await buttonByText(wrapper, 'Edit heading again').trigger('click');
        await nextTick();

        resolveFirstSave({
            ok: true,
            status: 200,
            statusText: 'OK',
            json: async () => ({ success: true, lock_version: 2 }),
        } as Response);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(3);
        expect(fetchMock.mock.calls.map((call) => call[1]?.method)).toEqual([
            'PUT',
            'PUT',
            'POST',
        ]);
        const secondSavePayload = JSON.parse(
            String(fetchMock.mock.calls[1][1]?.body),
        );
        expect(
            secondSavePayload.document_json.root.children[0].props.text,
        ).toBe('Second edit');

        wrapper.unmount();
    });

    it('does not dirty or autosave a semantic no-op Inspector edit', async () => {
        const fetchMock = vi.fn(async () => ({
            ok: true,
            status: 200,
            statusText: 'OK',
            json: async () => ({ success: true, lock_version: 2 }),
        }));
        globalThis.fetch = fetchMock as typeof fetch;
        const wrapper = mountEditor();

        await buttonByText(wrapper, 'Select heading').trigger('click');
        await buttonByText(wrapper, 'Repeat original value').trigger('click');
        await nextTick();

        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('saved');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');
        vi.advanceTimersByTime(3000);
        await flushPromises();
        expect(fetchMock).not.toHaveBeenCalled();

        await buttonByText(wrapper, 'Edit heading').trigger('click');
        await buttonByText(wrapper, 'Save').trigger('click');
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledOnce();
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('saved');

        await buttonByText(wrapper, 'Edit heading').trigger('click');
        await nextTick();
        vi.advanceTimersByTime(3000);
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledOnce();
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('saved');

        wrapper.unmount();
    });

    it('marks undo, redo, responsive override, and responsive reset as unsaved changes', async () => {
        let lockVersion = 1;
        const fetchMock = vi.fn(
            async (_input: RequestInfo | URL, init?: RequestInit) => ({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: async () => ({
                    success: true,
                    lock_version: ++lockVersion,
                }),
                method: init?.method,
            }),
        );
        globalThis.fetch = fetchMock as typeof fetch;
        const wrapper = mountEditor();

        await buttonByText(wrapper, 'Select heading').trigger('click');
        await buttonByText(wrapper, 'Edit heading').trigger('click');
        await buttonByText(wrapper, 'Save').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('saved');

        await buttonByText(wrapper, 'Undo').trigger('click');
        await nextTick();
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('dirty');
        await buttonByText(wrapper, 'Save').trigger('click');
        await flushPromises();

        await buttonByText(wrapper, 'Redo').trigger('click');
        await nextTick();
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('dirty');
        await buttonByText(wrapper, 'Save').trigger('click');
        await flushPromises();

        await buttonByText(wrapper, 'Set mobile alignment').trigger('click');
        await nextTick();
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('dirty');
        await buttonByText(wrapper, 'Save').trigger('click');
        await flushPromises();

        await buttonByText(wrapper, 'Reset mobile alignment').trigger('click');
        await nextTick();
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('dirty');
        await buttonByText(wrapper, 'Save').trigger('click');
        await flushPromises();

        const finalPayload = JSON.parse(
            String(fetchMock.mock.calls.at(-1)?.[1]?.body),
        );
        expect(finalPayload.document_json.root.children[0].styles).toBe(
            undefined,
        );

        await buttonByText(wrapper, 'Reset mobile alignment').trigger('click');
        await nextTick();
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('saved');

        wrapper.unmount();
    });

    it('keeps a failed pre-publish save dirty and does not publish', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: false,
                status: 422,
                statusText: 'Unprocessable Content',
                json: async () => ({
                    success: false,
                    message: 'Document validation failed.',
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: async () => ({ success: true, lock_version: 2 }),
            });
        globalThis.fetch = fetchMock as typeof fetch;
        const wrapper = mountEditor();

        await buttonByText(wrapper, 'Select heading').trigger('click');
        await buttonByText(wrapper, 'Edit heading').trigger('click');
        await buttonByText(wrapper, 'Publish').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(fetchMock.mock.calls[0][1]?.method).toBe('PUT');
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('error');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');
        expect(wrapper.text()).toContain('Document validation failed.');

        await buttonByText(wrapper, 'Save').trigger('click');
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(2);
        const retryPayload = JSON.parse(
            String(fetchMock.mock.calls[1][1]?.body),
        );
        expect(retryPayload.document_json.root.children[0].props.text).toBe(
            'First edit',
        );
        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('saved');

        wrapper.unmount();
    });

    it('keeps edits made during the publish request dirty for the next save', async () => {
        let resolvePublish!: (response: Response) => void;
        const publishRequest = new Promise<Response>((resolve) => {
            resolvePublish = resolve;
        });
        const fetchMock = vi.fn(
            async (_input: RequestInfo | URL, init?: RequestInit) => {
                if (init?.method === 'POST') return publishRequest;
                return {
                    ok: true,
                    status: 200,
                    statusText: 'OK',
                    json: async () => ({ success: true, lock_version: 2 }),
                } as Response;
            },
        );
        globalThis.fetch = fetchMock as typeof fetch;
        const wrapper = mountEditor();

        await buttonByText(wrapper, 'Select heading').trigger('click');
        await buttonByText(wrapper, 'Edit heading').trigger('click');
        await buttonByText(wrapper, 'Publish').trigger('click');
        await flushPromises();
        expect(fetchMock.mock.calls.map((call) => call[1]?.method)).toEqual([
            'PUT',
            'POST',
        ]);

        await buttonByText(wrapper, 'Edit heading again').trigger('click');
        await nextTick();
        resolvePublish({
            ok: true,
            status: 200,
            statusText: 'OK',
            json: async () => ({
                success: true,
                public_url: '/published/test-page',
            }),
        } as Response);
        await flushPromises();

        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('dirty');
        vi.advanceTimersByTime(2000);
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(3);
        const queuedSavePayload = JSON.parse(
            String(fetchMock.mock.calls[2][1]?.body),
        );
        expect(
            queuedSavePayload.document_json.root.children[0].props.text,
        ).toBe('Second edit');

        wrapper.unmount();
    });

    it('tracks the first edit after restoring an identical saved revision', async () => {
        globalThis.fetch = vi.fn() as typeof fetch;
        const wrapper = mountEditor();

        await buttonByText(wrapper, 'Restore identical revision').trigger(
            'click',
        );
        await nextTick();
        await buttonByText(wrapper, 'Select heading').trigger('click');
        await buttonByText(wrapper, 'Edit heading').trigger('click');
        await nextTick();

        expect(wrapper.get('[data-testid="save-status"]').text()).toBe('dirty');

        wrapper.unmount();
    });
});
