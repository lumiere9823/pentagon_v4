// @vitest-environment happy-dom

import { mount, flushPromises } from '@vue/test-utils';
import { defineComponent, h, nextTick, ref } from 'vue';
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import EditorShell from '@/components/editor/EditorShell.vue';
import { useModalFocus } from '@/composables/useModalFocus';
import type { DocumentSchema } from '@/types/document';

const sampleDocument: DocumentSchema = {
    schemaVersion: 1,
    root: {
        id: 'root_node',
        type: 'Section',
        props: {},
        children: [
            {
                id: 'heading_node_1',
                type: 'Heading',
                props: { text: 'Initial Headline' },
            },
        ],
    },
};

const sampleMetadata = {
    Heading: {
        type: 'Heading',
        label: 'Heading',
        category: 'typography',
        allowChildren: false,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    Section: {
        type: 'Section',
        label: 'Section',
        category: 'layout',
        allowChildren: true,
        canBeRoot: true,
        props: {},
        inspector: [],
    },
};

vi.mock('@/composables/useComponentRegistry', () => ({
    createComponentRegistrySession: () => ({
        components: ref(sampleMetadata),
        isLoading: ref(false),
        errorMessage: ref(null),
        load: vi.fn(),
        retry: vi.fn(),
        dispose: vi.fn(),
    }),
}));

describe('Package 3G / Phase 3: Accessibility & Workflow Hardening (R-16 & R-18)', () => {
    let originalFetch: typeof fetch;
    const wrappers: any[] = [];

    beforeEach(() => {
        originalFetch = globalThis.fetch;
        globalThis.fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ success: true, lock_version: 2 }),
        }) as any;
    });

    afterEach(() => {
        globalThis.fetch = originalFetch;
        for (const w of wrappers) {
            try {
                w.unmount();
            } catch {
                // ignore
            }
        }
        wrappers.length = 0;
    });

    function createEditorWrapper(canEdit = true) {
        const wrapper = mount(EditorShell, {
            props: {
                website: { id: 1, name: 'Pentagon Site', slug: 'pentagon-site' },
                page: {
                    id: 10,
                    title: 'Home Page',
                    slug: 'home',
                    status: 'draft',
                    lock_version: 1,
                },
                initialDocument: JSON.parse(JSON.stringify(sampleDocument)),
                can: { edit: canEdit },
                userRole: canEdit ? 'editor' : 'viewer',
            },
            global: {
                stubs: {
                    SidebarTrigger: true,
                    RevisionsModal: true,
                    SaveAsTemplateModal: true,
                    SeoSettingsModal: true,
                },
            },
            attachTo: document.body,
        });
        wrappers.push(wrapper);
        return wrapper;
    }

    describe('R-16: Live Region Status & Action Announcements', () => {
        it('renders an accessible live region with role="status" and aria-live="polite"', () => {
            const wrapper = createEditorWrapper();
            const liveRegion = wrapper.get('[data-testid="editor-live-region"]');

            expect(liveRegion.attributes('role')).toBe('status');
            expect(liveRegion.attributes('aria-live')).toBe('polite');
            expect(liveRegion.attributes('aria-atomic')).toBe('true');
            expect(liveRegion.classes()).toContain('sr-only');
        });

        it('announces status transitions when saving and when save completes', async () => {
            const wrapper = createEditorWrapper();
            const vm = wrapper.vm as any;

            // Trigger save
            await vm.saveNow('Manual save');
            await nextTick();

            const liveRegion = wrapper.get('[data-testid="editor-live-region"]');
            expect(liveRegion.text()).toBe('All changes saved successfully.');
        });

        it('announces undo and redo executions', async () => {
            const wrapper = createEditorWrapper();
            const vm = wrapper.vm as any;

            vm.selectNode('heading_node_1');
            vm.handleUpdateProp({ key: 'text', value: 'Updated Headline' });
            vm.flushPending();
            await nextTick();

            // Perform undo
            const undoBtn = wrapper.get('[data-testid="undo-btn"]');
            expect(undoBtn.attributes('disabled')).toBeUndefined();
            await undoBtn.trigger('click');
            await nextTick();

            const liveRegion = wrapper.get('[data-testid="editor-live-region"]');
            expect(liveRegion.text()).toBe('Undo action executed.');

            // Perform redo
            const redoBtn = wrapper.get('[data-testid="redo-btn"]');
            expect(redoBtn.attributes('disabled')).toBeUndefined();
            await redoBtn.trigger('click');
            await nextTick();
            expect(liveRegion.text()).toBe('Redo action executed.');
        });

        it('announces mutation errors via live region when action fails', async () => {
            const wrapper = createEditorWrapper(false); // viewer mode
            const vm = wrapper.vm as any;

            vm.handleMoveNode({
                nodeId: 'heading_node_1',
                targetParentId: 'root_node',
                targetIndex: 0,
            });
            await nextTick();

            const liveRegion = wrapper.get('[data-testid="editor-live-region"]');
            expect(liveRegion.text()).toContain('Error: Permission denied');
        });
    });

    describe('R-16: Keyboard Escape Guard in Input Fields (UX-08)', () => {
        it('Escape key blurs an active input field without deselecting the canvas node', async () => {
            const wrapper = createEditorWrapper();
            const vm = wrapper.vm as any;

            // Select node
            vm.selectNode('heading_node_1');
            expect(vm.selectedNodeId).toBe('heading_node_1');

            // Create and focus an input element (simulating an Inspector or rename input)
            const input = document.createElement('input');
            document.body.appendChild(input);
            input.focus();
            expect(document.activeElement).toBe(input);

            // Press Escape while focused in the input
            const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
            input.dispatchEvent(event);
            await nextTick();

            // Input is blurred
            expect(document.activeElement).not.toBe(input);
            // Selected node remains selected!
            expect(vm.selectedNodeId).toBe('heading_node_1');

            input.remove();
        });

        it('Escape key outside of input fields clears selection and announces to live region', async () => {
            const wrapper = createEditorWrapper();
            const vm = wrapper.vm as any;

            vm.selectNode('heading_node_1');
            expect(vm.selectedNodeId).toBe('heading_node_1');

            // Press Escape outside any input field
            const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
            window.dispatchEvent(event);
            await nextTick();

            expect(vm.selectedNodeId).toBeNull();
            const liveRegion = wrapper.get('[data-testid="editor-live-region"]');
            expect(liveRegion.text()).toBe('Selection cleared.');
        });
    });

    describe('R-16: Modal Focus Trapping & Focus Recovery', () => {
        it('useModalFocus traps Tab navigation within modal and recovers focus to trigger on close', async () => {
            // Test container with trigger button and modal
            const openerBtn = document.createElement('button');
            openerBtn.id = 'test-opener-btn';
            openerBtn.textContent = 'Open Dialog';
            document.body.appendChild(openerBtn);
            openerBtn.focus();

            const isOpen = ref(false);
            const modalEl = ref<HTMLElement | null>(null);

            const TestComponent = defineComponent({
                setup() {
                    useModalFocus(isOpen, modalEl, {
                        onClose: () => {
                            isOpen.value = false;
                        },
                    });
                    return { isOpen, modalEl };
                },
                template: `
                    <div>
                        <div v-if="isOpen" ref="modalEl" role="dialog" tabindex="-1">
                            <button id="btn-first">First</button>
                            <input id="input-middle" type="text" />
                            <button id="btn-last">Last</button>
                        </div>
                    </div>
                `,
            });

            const wrapper = mount(TestComponent, { attachTo: document.body });
            wrappers.push(wrapper);

            // 1. Open modal
            isOpen.value = true;
            await flushPromises();

            const firstBtn = document.getElementById('btn-first')!;
            const lastBtn = document.getElementById('btn-last')!;

            // Initial focus moves to first focusable element
            expect(document.activeElement).toBe(firstBtn);

            // 2. Tab on last button loops to first button
            lastBtn.focus();
            const tabForward = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true });
            modalEl.value!.dispatchEvent(tabForward);
            expect(document.activeElement).toBe(firstBtn);

            // 3. Shift+Tab on first button loops to last button
            firstBtn.focus();
            const tabBackward = new KeyboardEvent('keydown', {
                key: 'Tab',
                shiftKey: true,
                bubbles: true,
            });
            modalEl.value!.dispatchEvent(tabBackward);
            expect(document.activeElement).toBe(lastBtn);

            // 4. Escape key closes modal
            const escEvent = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
            modalEl.value!.dispatchEvent(escEvent);
            await flushPromises();

            expect(isOpen.value).toBe(false);

            // 5. Focus recovers to original opener button
            await flushPromises();
            expect(document.activeElement).toBe(openerBtn);

            openerBtn.remove();
        });
    });

    describe('R-18: Restore Revision History Boundary', () => {
        it('resets local history stack upon revision restore, preventing accidental undo into pre-restore edits', async () => {
            const wrapper = createEditorWrapper();
            const vm = wrapper.vm as any;

            // 1. Make local edits before restoring
            vm.selectNode('heading_node_1');
            vm.handleUpdateProp({ key: 'text', value: 'Local Unsaved Edit' });
            vm.flushPending();
            await nextTick();

            expect(vm.document.root.children[0].props.text).toBe('Local Unsaved Edit');
            expect(vm.canUndo).toBe(true);

            // 2. Restore historical revision
            const restoredDocument: DocumentSchema = {
                schemaVersion: 1,
                root: {
                    id: 'root_node',
                    type: 'Section',
                    props: {},
                    children: [
                        {
                            id: 'heading_node_1',
                            type: 'Heading',
                            props: { text: 'Historical Restored Headline' },
                        },
                    ],
                },
            };

            vm.handleRevisionRestored({
                document_json: restoredDocument,
                lock_version: 7,
            });
            await nextTick();

            // 3. Document is updated to restored content
            expect(vm.document.root.children[0].props.text).toBe(
                'Historical Restored Headline',
            );
            expect(vm.lockVersion).toBe(7);
            expect(vm.saveStatus).toBe('saved');
            expect(vm.isDirty).toBe(false);

            // 4. Local history stack is strictly cleared (R-18 boundary)
            expect(vm.canUndo).toBe(false);
            expect(vm.canRedo).toBe(false);

            // 5. Attempting to invoke undo does NOT revert to pre-restore edit
            const undoResult = vm.undo();
            expect(undoResult).toBe(false);
            expect(vm.document.root.children[0].props.text).toBe(
                'Historical Restored Headline',
            );

            // 6. Live region announces revision restoration and history reset
            const liveRegion = wrapper.get('[data-testid="editor-live-region"]');
            expect(liveRegion.text()).toBe(
                'Revision restored (Version #7). Document history reset.',
            );

            // 7. Subsequent edit starts a fresh history stack from the restored state
            vm.handleUpdateProp({ key: 'text', value: 'Post-Restore Fresh Edit' });
            vm.flushPending();
            await nextTick();

            expect(vm.document.root.children[0].props.text).toBe(
                'Post-Restore Fresh Edit',
            );
            expect(vm.canUndo).toBe(true);

            // Undo once restores back to restored state, not pre-restore state!
            await wrapper.get('[data-testid="undo-btn"]').trigger('click');
            await nextTick();

            expect(vm.document.root.children[0].props.text).toBe(
                'Historical Restored Headline',
            );
            expect(vm.canUndo).toBe(false);
        });
    });
});
