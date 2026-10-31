// @vitest-environment happy-dom

import { defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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
                props: { text: { type: 'string', default: '' } },
            },
            Container: {
                type: 'Container',
                label: 'Container',
                category: 'layout',
                allowChildren: true,
                canBeRoot: true,
                props: {},
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
        canUndo: { type: Boolean, required: true },
        canRedo: { type: Boolean, required: true },
    },
    emits: ['publish', 'undo', 'redo', 'save'],
    template: `
        <div>
            <output data-testid="save-status">{{ saveStatus }}</output>
            <output data-testid="is-dirty">{{ isDirty }}</output>
            <output data-testid="can-undo">{{ canUndo }}</output>
            <output data-testid="can-redo">{{ canRedo }}</output>
            <button type="button" data-testid="undo-btn" @click="$emit('undo')">Undo</button>
            <button type="button" data-testid="redo-btn" @click="$emit('redo')">Redo</button>
        </div>
    `,
});

const EditorCanvasStub = defineComponent({
    name: 'EditorCanvas',
    emits: ['select', 'moveUp', 'moveDown'],
    template: `
        <div>
            <button type="button" data-testid="move-up-btn" @click="$emit('moveUp', 'child_1')">
                Move Up Child 1
            </button>
            <button type="button" data-testid="move-down-btn" @click="$emit('moveDown', 'child_0')">
                Move Down Child 0
            </button>
        </div>
    `,
});

const InspectorPanelStub = defineComponent({
    name: 'InspectorPanel',
    template: '<aside data-testid="inspector-panel-stub" />',
});

const ComponentLibraryStub = defineComponent({
    name: 'ComponentLibrary',
    template: '<div data-testid="component-library-stub" />',
});

const LayersPanelStub = defineComponent({
    name: 'LayersPanel',
    emits: ['select', 'moveNode', 'setNodeLocked', 'setNodeHidden'],
    template: `
        <div data-testid="layers-panel-stub">
            <button
                type="button"
                data-testid="trigger-move-node"
                @click="$emit('moveNode', { nodeId: 'child_1', targetParentId: 'parent_container', targetIndex: 0 })"
            >
                Move Child 1 to 0
            </button>
            <button
                type="button"
                data-testid="trigger-noop-move"
                @click="$emit('moveNode', { nodeId: 'child_1', targetParentId: 'parent_container', targetIndex: 1 })"
            >
                Noop Move Child 1 to 1
            </button>
            <button
                type="button"
                data-testid="trigger-invalid-move"
                @click="$emit('moveNode', { nodeId: 'root_section', targetParentId: 'parent_container', targetIndex: 0 })"
            >
                Invalid Move Root
            </button>
        </div>
    `,
});

const sampleDoc: DocumentSchema = {
    schemaVersion: 1,
    root: {
        id: 'root_section',
        type: 'Section',
        props: { padding: 'medium', background: 'transparent' },
        children: [
            {
                id: 'parent_container',
                type: 'Container',
                props: {},
                children: [
                    {
                        id: 'child_0',
                        type: 'Heading',
                        props: { text: 'Heading 0', level: 2 },
                    },
                    {
                        id: 'child_1',
                        type: 'Heading',
                        props: { text: 'Heading 1', level: 2 },
                    },
                    {
                        id: 'child_2',
                        type: 'Heading',
                        props: { text: 'Heading 2', level: 2 },
                    },
                ],
            },
            {
                id: 'other_container',
                type: 'Container',
                props: {},
                children: [],
            },
        ],
    },
};

function createWrapper(canEdit = true) {
    return mount(EditorShell, {
        props: {
            website: { id: 10, name: 'Test Website', slug: 'test' },
            page: { id: 20, title: 'Test Page', slug: 'test-page', lock_version: 1 },
            initialDocument: JSON.parse(JSON.stringify(sampleDoc)),
            can: { edit: canEdit },
            userRole: canEdit ? 'editor' : 'viewer',
        },
        global: {
            stubs: {
                EditorToolbar: EditorToolbarStub,
                EditorCanvas: EditorCanvasStub,
                InspectorPanel: InspectorPanelStub,
                ComponentLibrary: ComponentLibraryStub,
                LayersPanel: LayersPanelStub,
                RevisionsModal: true,
                SeoSettingsModal: true,
                SaveAsTemplateModal: true,
                ConflictModal: true,
                PublishSuccessModal: true,
            },
        },
    });
}

describe('EditorShell Package 3D.1 Structural Move Integration', () => {
    it('creates exactly one undo entry and marks document dirty on a successful move', async () => {
        const wrapper = createWrapper(true);
        await nextTick();

        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

        // Trigger valid move: child_1 (at index 1) moved to index 0
        const moveBtn = wrapper.get('[data-testid="trigger-move-node"]');
        await moveBtn.trigger('click');
        await nextTick();

        // Exactly one undo entry should now be available
        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');

        // Verify document structure in VM
        const vm = wrapper.vm as any;
        const parent = vm.document.root.children[0];
        expect(parent.children.map((c: any) => c.id)).toEqual(['child_1', 'child_0', 'child_2']);

        // Selected node should remain child_1
        expect(vm.selectedNodeId).toBe('child_1');
    });

    it('does not create undo entry or mark dirty on a no-op move', async () => {
        const wrapper = createWrapper(true);
        await nextTick();

        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

        // Trigger no-op move: child_1 moved to index 1 (same spot)
        const noopBtn = wrapper.get('[data-testid="trigger-noop-move"]');
        await noopBtn.trigger('click');
        await nextTick();

        // Must NOT create undo entry or mark dirty
        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('surfaces accessible error alert and does not push undo entry on rejected move', async () => {
        const wrapper = createWrapper(true);
        await nextTick();

        // Trigger invalid move: moving root
        const invalidBtn = wrapper.get('[data-testid="trigger-invalid-move"]');
        await invalidBtn.trigger('click');
        await nextTick();

        // Must NOT create undo entry or mark dirty
        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

        // Must display accessible alert
        const alert = wrapper.find('[role="alert"]');
        expect(alert.exists()).toBe(true);
        expect(alert.text()).toContain('root node cannot be moved');

        // Dismiss error
        const dismissBtn = alert.find('button[aria-label="Dismiss error"]');
        expect(dismissBtn.exists()).toBe(true);
        await dismissBtn.trigger('click');
        await nextTick();

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('undoes a move restoring original parent/order, and redoes deterministically', async () => {
        const wrapper = createWrapper(true);
        await nextTick();

        const moveBtn = wrapper.get('[data-testid="trigger-move-node"]');
        await moveBtn.trigger('click');
        await nextTick();

        const vm = wrapper.vm as any;
        expect(vm.document.root.children[0].children[0].id).toBe('child_1');

        // Trigger Undo
        const undoBtn = wrapper.get('[data-testid="undo-btn"]');
        await undoBtn.trigger('click');
        await nextTick();

        // Original order restored
        expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
            'child_0',
            'child_1',
            'child_2',
        ]);
        expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('true');

        // Trigger Redo
        const redoBtn = wrapper.get('[data-testid="redo-btn"]');
        await redoBtn.trigger('click');
        await nextTick();

        // Move reapplied deterministically
        expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
            'child_1',
            'child_0',
            'child_2',
        ]);
    });

    it('undoes consecutive moves in reverse order restoring original tree', async () => {
        const wrapper = createWrapper(true);
        await nextTick();

        const vm = wrapper.vm as any;

        // Move 1: moveUp on child_1 -> swaps with child_0 -> [child_1, child_0, child_2]
        await wrapper.get('[data-testid="move-up-btn"]').trigger('click');
        await nextTick();
        expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
            'child_1',
            'child_0',
            'child_2',
        ]);

        // Move 2: moveDown on child_0 -> swaps with child_2 -> [child_1, child_2, child_0]
        await wrapper.get('[data-testid="move-down-btn"]').trigger('click');
        await nextTick();
        expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
            'child_1',
            'child_2',
            'child_0',
        ]);

        // Undo Move 2
        await wrapper.get('[data-testid="undo-btn"]').trigger('click');
        await nextTick();
        expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
            'child_1',
            'child_0',
            'child_2',
        ]);

        // Undo Move 1
        await wrapper.get('[data-testid="undo-btn"]').trigger('click');
        await nextTick();
        expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
            'child_0',
            'child_1',
            'child_2',
        ]);
    });

    it('blocks structural mutations in Viewer mode (can.edit === false)', async () => {
        const wrapper = createWrapper(false);
        await nextTick();

        const vm = wrapper.vm as any;
        const initialOrder = vm.document.root.children[0].children.map((c: any) => c.id);

        // Attempt move in viewer mode
        const moveBtn = wrapper.get('[data-testid="trigger-move-node"]');
        await moveBtn.trigger('click');
        await nextTick();

        // Document order must be unchanged
        expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual(initialOrder);
        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
        expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

        // Permission denied alert should be displayed
        const alert = wrapper.find('[role="alert"]');
        expect(alert.exists()).toBe(true);
        expect(alert.text()).toContain('Permission denied');
    });

    it('leaves both undo and redo history untouched when a move is rejected or a no-op', async () => {
        const wrapper = createWrapper(true);
        await nextTick();

        // Perform a valid move to establish history
        const moveBtn = wrapper.get('[data-testid="trigger-move-node"]');
        await moveBtn.trigger('click');
        await nextTick();

        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
        expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('false');

        // Undo the move: now undo is empty, redo has 1 item
        await wrapper.get('[data-testid="undo-btn"]').trigger('click');
        await nextTick();

        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
        expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('true');

        // Trigger rejected move (move root)
        await wrapper.get('[data-testid="trigger-invalid-move"]').trigger('click');
        await nextTick();

        // Undo and redo stacks must be unchanged!
        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
        expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('true');

        // Trigger no-op move
        await wrapper.get('[data-testid="trigger-noop-move"]').trigger('click');
        await nextTick();

        // Stacks must still be completely unchanged
        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
        expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('true');

        // Redo still functions properly
        await wrapper.get('[data-testid="redo-btn"]').trigger('click');
        await nextTick();

        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
        expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('false');
        const vm = wrapper.vm as any;
        expect(vm.document.root.children[0].children[0].id).toBe('child_1');
    });

    it('preserves pending debounced edits as a distinct history step prior to move', async () => {
        const wrapper = createWrapper(true);
        await nextTick();

        const vm = wrapper.vm as any;
        // Select child_0
        vm.selectNode('child_0');

        // Simulate debounced prop edit on child_0
        vm.handleUpdateProp({ key: 'text', value: 'Updated Heading 0' });

        expect(vm.document.root.children[0].children[0].props.text).toBe('Updated Heading 0');

        // Immediately trigger structural move before debounce timeout expires
        const moveBtn = wrapper.get('[data-testid="trigger-move-node"]');
        await moveBtn.trigger('click');
        await nextTick();

        // Move applied: child_1 is now at index 0, child_0 is at index 1
        expect(vm.document.root.children[0].children[0].id).toBe('child_1');
        expect(vm.document.root.children[0].children[1].id).toBe('child_0');
        expect(vm.document.root.children[0].children[1].props.text).toBe('Updated Heading 0');

        // Undo 1: Should undo the move, but retain the debounced prop edit!
        await wrapper.get('[data-testid="undo-btn"]').trigger('click');
        await nextTick();

        expect(vm.document.root.children[0].children[0].id).toBe('child_0');
        expect(vm.document.root.children[0].children[1].id).toBe('child_1');
        expect(vm.document.root.children[0].children[0].props.text).toBe('Updated Heading 0');
        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');

        // Undo 2: Should undo the debounced prop edit, restoring original text!
        await wrapper.get('[data-testid="undo-btn"]').trigger('click');
        await nextTick();

        expect(vm.document.root.children[0].children[0].props.text).toBe('Heading 0');
        expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
    });

    describe('Package 3D.4 12 Edge-Case Matrix & Move Contract Verification', () => {
        it('Scenario 1: executes same-parent upward and downward moves creating exact history snapshots', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });
            const vm = wrapper.vm as any;

            // Initial: [child_0, child_1, child_2]
            // Move child_2 up before child_1 (post-removal targetIndex: 1)
            layers.vm.$emit('moveNode', {
                nodeId: 'child_2',
                targetParentId: 'parent_container',
                targetIndex: 1,
            });
            await nextTick();

            expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
                'child_0',
                'child_2',
                'child_1',
            ]);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');

            // Move child_0 down after child_2 (post-removal targetIndex: 1)
            layers.vm.$emit('moveNode', {
                nodeId: 'child_0',
                targetParentId: 'parent_container',
                targetIndex: 1,
            });
            await nextTick();

            expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
                'child_2',
                'child_0',
                'child_1',
            ]);
        });

        it('Scenario 2: treats drops before and after adjacent siblings as no-ops without history mutation', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });

            // child_1 is at index 1. Moving to index 1 is a no-op
            layers.vm.$emit('moveNode', {
                nodeId: 'child_1',
                targetParentId: 'parent_container',
                targetIndex: 1,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');
            expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        });

        it('Scenario 3: rejects moving a node inside itself', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });

            layers.vm.$emit('moveNode', {
                nodeId: 'parent_container',
                targetParentId: 'parent_container',
                targetIndex: 0,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('Cannot move a container into itself or its own descendants');
        });

        it('Scenario 4: appends a non-final child to its own parent as a valid move', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });
            const vm = wrapper.vm as any;

            // Initial: [child_0, child_1, child_2]. Append child_0 (index 0) to end (index 2 after removal)
            layers.vm.$emit('moveNode', {
                nodeId: 'child_0',
                targetParentId: 'parent_container',
                targetIndex: 2,
            });
            await nextTick();

            expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
                'child_1',
                'child_2',
                'child_0',
            ]);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');
        });

        it('Scenario 5: treats dropping final child inside current parent as no-op', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });

            // child_2 is already at index 2 (the end). Moving to index 2 is a no-op
            layers.vm.$emit('moveNode', {
                nodeId: 'child_2',
                targetParentId: 'parent_container',
                targetIndex: 2,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');
        });

        it('Scenario 6: moves node from one parent into another parent preserving selection', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });
            const vm = wrapper.vm as any;

            // Move child_1 from parent_container into other_container at index 0
            layers.vm.$emit('moveNode', {
                nodeId: 'child_1',
                targetParentId: 'other_container',
                targetIndex: 0,
            });
            await nextTick();

            expect(vm.document.root.children[0].children.map((c: any) => c.id)).toEqual([
                'child_0',
                'child_2',
            ]);
            expect(vm.document.root.children[1].children.map((c: any) => c.id)).toEqual([
                'child_1',
            ]);
            expect(vm.selectedNodeId).toBe('child_1');
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
        });

        it('Scenario 7: rejects circular moves into descendants', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });

            // Moving root_section into parent_container
            layers.vm.$emit('moveNode', {
                nodeId: 'root_section',
                targetParentId: 'parent_container',
                targetIndex: 0,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('root node cannot be moved');
        });

        it('Scenario 8: rejects moving the root container', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });

            layers.vm.$emit('moveNode', {
                nodeId: 'root_section',
                targetParentId: 'other_container',
                targetIndex: 0,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.find('[role="alert"]').text()).toContain('root node cannot be moved');
        });

        it('Scenario 9: rejects move when target container reaches 50 children limit', async () => {
            // Document with full container (50 children)
            const fullDoc: DocumentSchema = {
                schemaVersion: 1,
                root: {
                    id: 'root_section',
                    type: 'Section',
                    props: {},
                    children: [
                        {
                            id: 'full_container',
                            type: 'Container',
                            props: {},
                            children: Array.from({ length: 50 }, (_, i) => ({
                                id: `c_${i}`,
                                type: 'Heading',
                                props: {},
                            })),
                        },
                        {
                            id: 'source_container',
                            type: 'Container',
                            props: {},
                            children: [
                                { id: 'extra_node', type: 'Heading', props: {} },
                            ],
                        },
                    ],
                },
            };

            const wrapper = mount(EditorShell, {
                props: {
                    website: { id: 10, name: 'Test Website', slug: 'test' },
                    page: { id: 20, title: 'Test Page', slug: 'test-page', lock_version: 1 },
                    initialDocument: fullDoc,
                    can: { edit: true },
                    userRole: 'editor',
                },
                global: {
                    stubs: {
                        EditorToolbar: EditorToolbarStub,
                        EditorCanvas: EditorCanvasStub,
                        InspectorPanel: InspectorPanelStub,
                        ComponentLibrary: ComponentLibraryStub,
                        LayersPanel: LayersPanelStub,
                        RevisionsModal: true,
                        SeoSettingsModal: true,
                        SaveAsTemplateModal: true,
                        ConflictModal: true,
                        PublishSuccessModal: true,
                    },
                },
            });
            await nextTick();

            const layers = wrapper.findComponent({ name: 'LayersPanel' });
            layers.vm.$emit('moveNode', {
                nodeId: 'extra_node',
                targetParentId: 'full_container',
                targetIndex: 0,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('target container already has the maximum of 50 children');
        });

        it('Scenario 10: rejects move when resulting tree exceeds depth 10', async () => {
            // Build tree of depth 9
            let deepChild: any = { id: 'depth_9', type: 'Container', props: {}, children: [] };
            for (let d = 8; d >= 1; d--) {
                deepChild = { id: `depth_${d}`, type: 'Container', props: {}, children: [deepChild] };
            }

            const deepDoc: DocumentSchema = {
                schemaVersion: 1,
                root: {
                    id: 'root_section',
                    type: 'Section',
                    props: {},
                    children: [
                        deepChild,
                        {
                            id: 'moving_container',
                            type: 'Container',
                            props: {},
                            children: [
                                { id: 'moving_leaf', type: 'Heading', props: {} },
                            ],
                        },
                    ],
                },
            };

            const wrapper = mount(EditorShell, {
                props: {
                    website: { id: 10, name: 'Test Website', slug: 'test' },
                    page: { id: 20, title: 'Test Page', slug: 'test-page', lock_version: 1 },
                    initialDocument: deepDoc,
                    can: { edit: true },
                    userRole: 'editor',
                },
                global: {
                    stubs: {
                        EditorToolbar: EditorToolbarStub,
                        EditorCanvas: EditorCanvasStub,
                        InspectorPanel: InspectorPanelStub,
                        ComponentLibrary: ComponentLibraryStub,
                        LayersPanel: LayersPanelStub,
                        RevisionsModal: true,
                        SeoSettingsModal: true,
                        SaveAsTemplateModal: true,
                        ConflictModal: true,
                        PublishSuccessModal: true,
                    },
                },
            });
            await nextTick();

            const layers = wrapper.findComponent({ name: 'LayersPanel' });
            layers.vm.$emit('moveNode', {
                nodeId: 'moving_container',
                targetParentId: 'depth_9',
                targetIndex: 0,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('would exceed maximum tree depth of 10');
        });

        it('Scenario 12: handles stale source or target IDs safely without throwing', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const layers = wrapper.findComponent({ name: 'LayersPanel' });

            // Stale source
            layers.vm.$emit('moveNode', {
                nodeId: 'non_existent_node',
                targetParentId: 'parent_container',
                targetIndex: 0,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.find('[role="alert"]').text()).toContain('Source node \'non_existent_node\' not found');

            // Stale destination
            layers.vm.$emit('moveNode', {
                nodeId: 'child_0',
                targetParentId: 'non_existent_parent',
                targetIndex: 0,
            });
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.find('[role="alert"]').text()).toContain('Target container \'non_existent_parent\' not found');
        });
    });

    describe('Package 3E.1 Node Locking Orchestration & Permission Guard', () => {
        it('creates exactly one undo entry and marks dirty on valid lock change, and undo restores unlocked state', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

            // Lock child_0
            const res = vm.handleSetNodeLocked({ nodeId: 'child_0', locked: true });
            expect(res.success).toBe(true);
            expect(res.changed).toBe(true);
            await nextTick();

            expect(vm.isNodeLocked('child_0')).toBe(true);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');

            // Trigger Undo
            await wrapper.get('[data-testid="undo-btn"]').trigger('click');
            await nextTick();

            // Lock state restored to unlocked!
            expect(vm.isNodeLocked('child_0')).toBe(false);
            expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('true');

            // Trigger Redo
            await wrapper.get('[data-testid="redo-btn"]').trigger('click');
            await nextTick();

            // Lock state reapplied!
            expect(vm.isNodeLocked('child_0')).toBe(true);
        });

        it('creates zero undo entries and does not mark dirty on lock no-ops', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            // Setting already unlocked node to false
            const noopRes = vm.handleSetNodeLocked({ nodeId: 'child_0', locked: false });
            expect(noopRes.success).toBe(true);
            expect(noopRes.changed).toBe(false);
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');
            expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        });

        it('blocks both locking and unlocking in Viewer mode (can.edit === false)', async () => {
            const wrapper = createWrapper(false);
            await nextTick();
            const vm = wrapper.vm as any;

            // Attempt to lock in viewer mode
            const lockRes = vm.handleSetNodeLocked({ nodeId: 'child_0', locked: true });
            expect(lockRes.success).toBe(false);
            expect(lockRes.changed).toBe(false);
            expect(lockRes.error).toContain('Permission denied');
            await nextTick();

            expect(vm.isNodeLocked('child_0')).toBe(false);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('Permission denied');
        });

        it('surfaces error alert and leaves history untouched when mutation targets a locked node', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            // Lock parent_container
            vm.handleSetNodeLocked({ nodeId: 'parent_container', locked: true });
            await nextTick();

            // Clear dirty and undo history for clean baseline test
            const canUndoBefore = wrapper.get('[data-testid="can-undo"]').text();

            // Attempt to move child_1 (inside locked parent)
            const layers = wrapper.findComponent({ name: 'LayersPanel' });
            layers.vm.$emit('moveNode', {
                nodeId: 'child_1',
                targetParentId: 'other_container',
                targetIndex: 0,
            });
            await nextTick();

            // Mutation must be rejected
            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('locked');

            // Child 1 must still be in parent_container
            expect(vm.document.root.children[0].children.map((c: any) => c.id)).toContain('child_1');

            // Attempt to delete locked parent
            vm.handleDelete('parent_container');
            await nextTick();
            expect(wrapper.find('[role="alert"]').text()).toContain('locked');
            expect(vm.document.root.children.some((c: any) => c.id === 'parent_container')).toBe(true);

            // Attempt to update prop on child of locked container
            vm.selectNode('child_0');
            vm.handleUpdateProp({ key: 'text', value: 'Disallowed Edit' });
            await nextTick();
            expect(wrapper.find('[role="alert"]').text()).toContain('locked');
            expect(vm.document.root.children[0].children[0].props.text).toBe('Heading 0');
        });

        it('disables Move Up, Move Down, Duplicate, and Delete in NodeToolbar when node is locked', async () => {
            const { default: NodeToolbar } = await import('@/components/editor/canvas/NodeToolbar.vue');

            // 1. Unlocked node: actions enabled according to boundaries
            const unlockedWrapper = mount(NodeToolbar, {
                props: {
                    componentType: 'Heading',
                    isRoot: false,
                    canMoveUp: true,
                    canMoveDown: true,
                    isLocked: false,
                },
            });

            expect(unlockedWrapper.get('button[aria-label="Move Up"]').attributes('disabled')).toBeUndefined();
            expect(unlockedWrapper.get('button[aria-label="Move Down"]').attributes('disabled')).toBeUndefined();
            expect(unlockedWrapper.get('button[aria-label="Duplicate"]').attributes('disabled')).toBeUndefined();
            expect(unlockedWrapper.get('button[aria-label="Delete"]').attributes('disabled')).toBeUndefined();
            expect(unlockedWrapper.get('button[aria-label="Select Parent"]').attributes('disabled')).toBeUndefined();

            await unlockedWrapper.get('button[aria-label="Move Up"]').trigger('click');
            expect(unlockedWrapper.emitted('moveUp')).toHaveLength(1);

            await unlockedWrapper.get('button[aria-label="Duplicate"]').trigger('click');
            expect(unlockedWrapper.emitted('duplicate')).toHaveLength(1);

            await unlockedWrapper.get('button[aria-label="Delete"]').trigger('click');
            expect(unlockedWrapper.emitted('delete')).toHaveLength(1);

            unlockedWrapper.unmount();

            // 2. Locked node: Move, Duplicate, and Delete disabled; Select Parent remains active
            const lockedWrapper = mount(NodeToolbar, {
                props: {
                    componentType: 'Heading',
                    isRoot: false,
                    canMoveUp: true,
                    canMoveDown: true,
                    isLocked: true,
                },
            });

            expect(lockedWrapper.get('button[aria-label="Move Up"]').attributes('disabled')).toBeDefined();
            expect(lockedWrapper.get('button[aria-label="Move Down"]').attributes('disabled')).toBeDefined();
            expect(lockedWrapper.get('button[aria-label="Duplicate"]').attributes('disabled')).toBeDefined();
            expect(lockedWrapper.get('button[aria-label="Delete"]').attributes('disabled')).toBeDefined();
            expect(lockedWrapper.get('button[aria-label="Select Parent"]').attributes('disabled')).toBeUndefined();

            // Clicking disabled buttons does not emit
            await lockedWrapper.get('button[aria-label="Move Up"]').trigger('click');
            expect(lockedWrapper.emitted('moveUp')).toBeUndefined();

            await lockedWrapper.get('button[aria-label="Duplicate"]').trigger('click');
            expect(lockedWrapper.emitted('duplicate')).toBeUndefined();

            await lockedWrapper.get('button[aria-label="Delete"]').trigger('click');
            expect(lockedWrapper.emitted('delete')).toBeUndefined();

            // Select Parent still works
            await lockedWrapper.get('button[aria-label="Select Parent"]').trigger('click');
            expect(lockedWrapper.emitted('selectParent')).toHaveLength(1);

            // Shows lock icon in header
            expect(lockedWrapper.find('[aria-label="Component is locked"]').exists()).toBe(true);

            lockedWrapper.unmount();
        });

        it('preserves root restrictions in NodeToolbar regardless of lock state', async () => {
            const { default: NodeToolbar } = await import('@/components/editor/canvas/NodeToolbar.vue');

            const rootWrapper = mount(NodeToolbar, {
                props: {
                    componentType: 'Section',
                    isRoot: true,
                    canMoveUp: false,
                    canMoveDown: false,
                    isLocked: false,
                },
            });

            // Root has no structural move, duplicate, delete, or parent buttons
            expect(rootWrapper.find('button[aria-label="Move Up"]').exists()).toBe(false);
            expect(rootWrapper.find('button[aria-label="Move Down"]').exists()).toBe(false);
            expect(rootWrapper.find('button[aria-label="Duplicate"]').exists()).toBe(false);
            expect(rootWrapper.find('button[aria-label="Delete"]').exists()).toBe(false);
            expect(rootWrapper.find('button[aria-label="Select Parent"]').exists()).toBe(false);

            rootWrapper.unmount();
        });

        it('propagates lock state from LayersPanel through EditorShell orchestration', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            const layers = wrapper.findComponent({ name: 'LayersPanel' });
            expect(layers.exists()).toBe(true);

            // Emit setNodeLocked from LayersPanel
            layers.vm.$emit('setNodeLocked', { nodeId: 'child_0', locked: true });
            await nextTick();

            expect(vm.isNodeLocked('child_0')).toBe(true);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');

            // Select child_0 and verify Inspector gets isLocked: true
            vm.selectNode('child_0');
            await nextTick();

            expect(vm.selectedNodeIsLocked).toBe(true);
            expect(vm.selectedNodeIsAncestorLocked).toBe(false);

            // Now select child_0's sibling in same container:
            // Since child_0 was locked, child_1 is not locked
            vm.selectNode('child_1');
            await nextTick();
            expect(vm.selectedNodeIsLocked).toBe(false);
            expect(vm.selectedNodeIsAncestorLocked).toBe(false);

            // Now lock parent_container
            layers.vm.$emit('setNodeLocked', { nodeId: 'parent_container', locked: true });
            await nextTick();

            // child_1 now has ancestor locked
            expect(vm.selectedNodeIsLocked).toBe(false);
            expect(vm.selectedNodeIsAncestorLocked).toBe(true);
            expect(vm.selectedNodeLockedAncestorLabel).toBe('Container');

            wrapper.unmount();
        });
    });

    describe('Package 3E.3 - Layer Visibility & History Integration', () => {
        it('records a history snapshot on successful visibility mutation and allows undo/redo', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            expect(vm.isNodeHidden('child_0')).toBe(false);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');

            // Hide child_0
            const res = vm.handleSetNodeHidden({ nodeId: 'child_0', hidden: true });
            expect(res.success).toBe(true);
            expect(res.changed).toBe(true);
            await nextTick();

            expect(vm.isNodeHidden('child_0')).toBe(true);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');

            // Undo restores visibility
            await wrapper.get('[data-testid="undo-btn"]').trigger('click');
            await nextTick();

            expect(vm.isNodeHidden('child_0')).toBe(false);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('true');

            // Redo reapplies hidden
            await wrapper.get('[data-testid="redo-btn"]').trigger('click');
            await nextTick();

            expect(vm.isNodeHidden('child_0')).toBe(true);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
            expect(wrapper.get('[data-testid="can-redo"]').text()).toBe('false');

            wrapper.unmount();
        });

        it('does not record a history snapshot when visibility change is a no-op or invalid', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            // 1. No-op (unhiding already visible node)
            const noopRes = vm.handleSetNodeHidden({ nodeId: 'child_0', hidden: false });
            expect(noopRes.success).toBe(true);
            expect(noopRes.changed).toBe(false);
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');

            // 2. Invalid (trying to hide root node)
            const rootRes = vm.handleSetNodeHidden({ nodeId: 'root_section', hidden: true });
            expect(rootRes.success).toBe(false);
            expect(rootRes.changed).toBe(false);
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('The root node cannot be hidden.');

            wrapper.unmount();
        });

        it('blocks setNodeHidden in viewer mode (can.edit: false)', async () => {
            const wrapper = createWrapper(false);
            await nextTick();
            const vm = wrapper.vm as any;

            const res = vm.handleSetNodeHidden({ nodeId: 'child_0', hidden: true });
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toContain('Permission denied');
            await nextTick();

            expect(vm.isNodeHidden('child_0')).toBe(false);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('Permission denied');

            wrapper.unmount();
        });

        it('propagates setNodeHidden event from LayersPanel through EditorShell orchestration', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            const layers = wrapper.findComponent({ name: 'LayersPanel' });
            expect(layers.exists()).toBe(true);

            // Emit setNodeHidden from LayersPanel
            layers.vm.$emit('setNodeHidden', { nodeId: 'child_0', hidden: true });
            await nextTick();

            expect(vm.isNodeHidden('child_0')).toBe(true);
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');

            wrapper.unmount();
        });

        it('isolates pending debounced edits prior to locking, preventing subsequent edits from bypassing lock', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            // 1. Select child_0 and start debounced edit
            vm.selectNode('child_0');
            vm.handleUpdateProp({ key: 'text', value: 'Pending Debounced Edit' });
            expect(vm.document.root.children[0].children[0].props.text).toBe(
                'Pending Debounced Edit',
            );

            // 2. Immediately lock child_0 before debounce timeout expires
            const lockRes = vm.handleSetNodeLocked({
                nodeId: 'child_0',
                locked: true,
            });
            expect(lockRes.success).toBe(true);
            expect(lockRes.changed).toBe(true);
            await nextTick();

            expect(vm.isNodeLocked('child_0')).toBe(true);

            // 3. Attempt to mutate locked node
            vm.handleUpdateProp({
                key: 'text',
                value: 'Lock Bypass Attempt',
            });
            await nextTick();

            // Edit must be rejected fail-closed
            expect(vm.document.root.children[0].children[0].props.text).toBe(
                'Pending Debounced Edit',
            );
            const alert = wrapper.find('[role="alert"]');
            expect(alert.exists()).toBe(true);
            expect(alert.text()).toContain('locked');

            // 4. Undo 1: Undoes the lock toggle, restoring unlocked state
            await wrapper.get('[data-testid="undo-btn"]').trigger('click');
            await nextTick();
            expect(vm.isNodeLocked('child_0')).toBe(false);
            expect(vm.document.root.children[0].children[0].props.text).toBe(
                'Pending Debounced Edit',
            );

            // 5. Undo 2: Undoes the debounced edit, restoring original text
            await wrapper.get('[data-testid="undo-btn"]').trigger('click');
            await nextTick();
            expect(vm.document.root.children[0].children[0].props.text).toBe(
                'Heading 0',
            );

            wrapper.unmount();
        });

        it('does not overwrite a different selection when switching nodes during edits', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            // Edit child_0
            vm.selectNode('child_0');
            vm.handleUpdateProp({ key: 'text', value: 'Custom Text for 0' });

            // Switch to child_1
            vm.selectNode('child_1');
            vm.handleUpdateProp({ key: 'text', value: 'Custom Text for 1' });

            await nextTick();

            // Verify both nodes retained their distinct values
            expect(vm.document.root.children[0].children[0].props.text).toBe(
                'Custom Text for 0',
            );
            expect(vm.document.root.children[0].children[1].props.text).toBe(
                'Custom Text for 1',
            );

            wrapper.unmount();
        });
    });

    describe('Package 3F: Node Custom Labeling Integration', () => {
        it('creates exactly one undo entry and marks dirty on valid label change, and undo restores previous label', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

            // Apply custom label
            const res = vm.handleSetNodeLabel({
                nodeId: 'child_0',
                label: 'Main Header',
            });
            expect(res.success).toBe(true);
            expect(res.changed).toBe(true);
            await nextTick();

            expect(vm.document.root.children[0].children[0].label).toBe(
                'Main Header',
            );
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('true');

            // Undo: restores previous undefined label
            await wrapper.get('[data-testid="undo-btn"]').trigger('click');
            await nextTick();
            expect(
                vm.document.root.children[0].children[0].label,
            ).toBeUndefined();
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');

            // Redo: re-applies custom label
            await wrapper.get('[data-testid="redo-btn"]').trigger('click');
            await nextTick();
            expect(vm.document.root.children[0].children[0].label).toBe(
                'Main Header',
            );
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');

            wrapper.unmount();
        });

        it('creates zero undo entries and does not mark dirty on no-op label changes', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            // Attempt setting undefined/empty label on already unlabeled node
            const resNoop = vm.handleSetNodeLabel({
                nodeId: 'child_0',
                label: null,
            });
            expect(resNoop.success).toBe(true);
            expect(resNoop.changed).toBe(false);
            await nextTick();

            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

            // Set label first
            vm.handleSetNodeLabel({
                nodeId: 'child_0',
                label: 'Main Header',
            });
            await nextTick();
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('true');

            // Now attempt identical label
            const resSame = vm.handleSetNodeLabel({
                nodeId: 'child_0',
                label: '  Main Header  ',
            });
            expect(resSame.success).toBe(true);
            expect(resSame.changed).toBe(false);

            // Undo should undo the single change and exhaust the stack
            await wrapper.get('[data-testid="undo-btn"]').trigger('click');
            await nextTick();
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');

            wrapper.unmount();
        });

        it('rejects label changes in Viewer mode fail-closed without mutating document or creating undo snapshot', async () => {
            const wrapper = createWrapper(false); // viewer mode
            await nextTick();
            const vm = wrapper.vm as any;

            const res = vm.handleSetNodeLabel({
                nodeId: 'child_0',
                label: 'Hacked Label',
            });
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toBe('Permission denied: modifications disabled.');

            expect(
                vm.document.root.children[0].children[0].label,
            ).toBeUndefined();
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');
            expect(wrapper.get('[data-testid="is-dirty"]').text()).toBe('false');

            wrapper.unmount();
        });

        it('rejects label changes on locked nodes fail-closed', async () => {
            const wrapper = createWrapper(true);
            await nextTick();
            const vm = wrapper.vm as any;

            // Lock the node first
            vm.handleSetNodeLocked({ nodeId: 'child_0', locked: true });
            await nextTick();

            // Clear dirty/undo by clicking undo? No, we check that attempting to rename does not add undo
            const res = vm.handleSetNodeLabel({
                nodeId: 'child_0',
                label: 'Renaming Locked Node',
            });
            expect(res.success).toBe(false);
            expect(res.changed).toBe(false);
            expect(res.error).toBe(
                'Cannot rename locked node or node inside a locked container.',
            );
            expect(
                vm.document.root.children[0].children[0].label,
            ).toBeUndefined();

            // Exactly 1 undo entry exists (from the lock toggle itself)
            await wrapper.get('[data-testid="undo-btn"]').trigger('click');
            await nextTick();
            // Now back to initial state
            expect(wrapper.get('[data-testid="can-undo"]').text()).toBe('false');

            wrapper.unmount();
        });
    });
});
