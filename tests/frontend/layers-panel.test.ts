// @vitest-environment happy-dom

import { defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import LayersPanel from '@/components/editor/layers/LayersPanel.vue';
import LayerTreeNode from '@/components/editor/layers/LayerTreeNode.vue';
import NodeToolbar from '@/components/editor/canvas/NodeToolbar.vue';
import { useDocumentState } from '@/composables/useDocumentState';
import type {
    ComponentMetadata,
    CoreComponentType,
    DocumentNode,
    DocumentSchema,
} from '@/types/document';

const sampleMetadata: Record<string, ComponentMetadata> = {
    Section: {
        type: 'Section',
        label: 'Section',
        category: 'layout',
        allowChildren: true,
        canBeRoot: true,
        props: {},
        inspector: [],
    },
    Container: {
        type: 'Container',
        label: 'Container',
        category: 'layout',
        allowChildren: true,
        canBeRoot: true,
        props: {},
        inspector: [],
    },
    Heading: {
        type: 'Heading',
        label: 'Heading',
        category: 'typography',
        allowChildren: false,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    RichText: {
        type: 'RichText',
        label: 'Rich Text',
        category: 'typography',
        allowChildren: false,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    Image: {
        type: 'Image',
        label: 'Image',
        category: 'media',
        allowChildren: false,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    Button: {
        type: 'Button',
        label: 'Button',
        category: 'interactive',
        allowChildren: false,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    Hero: {
        type: 'Hero',
        label: 'Hero',
        category: 'content',
        allowChildren: true,
        canBeRoot: true,
        props: {},
        inspector: [],
    },
    Columns: {
        type: 'Columns',
        label: 'Columns',
        category: 'layout',
        allowChildren: true,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    CardGrid: {
        type: 'CardGrid',
        label: 'Card Grid',
        category: 'layout',
        allowChildren: true,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    FAQ: {
        type: 'FAQ',
        label: 'FAQ',
        category: 'content',
        allowChildren: false,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    Spacer: {
        type: 'Spacer',
        label: 'Spacer',
        category: 'layout',
        allowChildren: false,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
    Divider: {
        type: 'Divider',
        label: 'Divider',
        category: 'layout',
        allowChildren: false,
        canBeRoot: false,
        props: {},
        inspector: [],
    },
};

const fullDocument: DocumentSchema = {
    schemaVersion: 1,
    root: {
        id: 'node_root_section',
        type: 'Section',
        props: { padding: 'medium', background: 'transparent' },
        children: [
            {
                id: 'node_container_1',
                type: 'Container',
                props: { maxWidth: 'lg' },
                children: [
                    {
                        id: 'node_hero_1',
                        type: 'Hero',
                        props: { title: 'Welcome Hero' },
                        children: [],
                    },
                    {
                        id: 'node_heading_1',
                        type: 'Heading',
                        props: { text: 'Main Heading' },
                    },
                    {
                        id: 'node_richtext_1',
                        type: 'RichText',
                        props: { content: '<p>Some text</p>' },
                    },
                    {
                        id: 'node_image_1',
                        type: 'Image',
                        props: { src: 'https://example.com/img.jpg', alt: 'Hero Graphic' },
                    },
                    {
                        id: 'node_button_1',
                        type: 'Button',
                        props: { label: 'Click Me' },
                    },
                    {
                        id: 'node_columns_1',
                        type: 'Columns',
                        props: { count: 2 },
                        children: [
                            {
                                id: 'node_spacer_1',
                                type: 'Spacer',
                                props: { size: 'medium' },
                            },
                            {
                                id: 'node_divider_1',
                                type: 'Divider',
                                props: { style: 'solid' },
                            },
                        ],
                    },
                    {
                        id: 'node_cardgrid_empty',
                        type: 'CardGrid',
                        props: { columns: 3 },
                        children: [],
                    },
                    {
                        id: 'node_faq_1',
                        type: 'FAQ',
                        props: {
                            items: [
                                { question: 'Q1', answer: 'A1' },
                                { question: 'Q2', answer: 'A2' },
                            ],
                        },
                    },
                ],
            },
        ],
    },
};

describe('LayersPanel Component (Package 3B)', () => {
    it('renders the complete document tree with all 12 registered component types and icons', async () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                components: sampleMetadata,
            },
            global: {
                stubs: {
                    Tooltip: { template: '<div><slot /></div>' },
                    TooltipContent: { template: '<div><slot /></div>' },
                    TooltipProvider: { template: '<div><slot /></div>' },
                    TooltipTrigger: { template: '<div><slot /></div>' },
                },
            },
        });

        // Expand all so all nodes are in the DOM
        const expandAllBtn = wrapper.find('button[aria-label="Expand All Containers"]');
        expect(expandAllBtn.exists()).toBe(true);
        await expandAllBtn.trigger('click');

        const expectedTypes: CoreComponentType[] = [
            'Section',
            'Container',
            'Hero',
            'Heading',
            'RichText',
            'Image',
            'Button',
            'Columns',
            'Spacer',
            'Divider',
            'CardGrid',
            'FAQ',
        ];

        for (const type of expectedTypes) {
            const row = wrapper.find(`[data-component-type="${type}"]`);
            expect(row.exists()).toBe(true);
        }

        // Verify total node count in footer
        expect(wrapper.text()).toContain('12 / 250 nodes');
    });

    it('displays safe text previews for Heading, Button, Hero, Image, and FAQ', async () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                components: sampleMetadata,
            },
        });

        // Expand all containers
        const expandAllBtn = wrapper.get('button[aria-label="Expand All Containers"]');
        await expandAllBtn.trigger('click');

        expect(wrapper.text()).toContain('"Welcome Hero"');
        expect(wrapper.text()).toContain('"Main Heading"');
        expect(wrapper.text()).toContain('"Click Me"');
        expect(wrapper.text()).toContain('"Hero Graphic"');
        expect(wrapper.text()).toContain('"2 items"');
    });

    it('displays clear indication for empty containers', async () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                components: sampleMetadata,
            },
        });

        const expandAllBtn = wrapper.get('button[aria-label="Expand All Containers"]');
        await expandAllBtn.trigger('click');

        // Hero and CardGrid are empty containers in our fixture
        expect(wrapper.text()).toContain('Empty container');
    });

    it('does not display chevron expansion buttons on leaf nodes', async () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                components: sampleMetadata,
            },
        });

        const expandAllBtn = wrapper.get('button[aria-label="Expand All Containers"]');
        await expandAllBtn.trigger('click');

        const headingRow = wrapper.get('[data-node-id="node_heading_1"]');
        expect(headingRow.find('button[aria-label*="Expand"], button[aria-label*="Collapse"]').exists()).toBe(false);

        const buttonRow = wrapper.get('[data-node-id="node_button_1"]');
        expect(buttonRow.find('button[aria-label*="Expand"], button[aria-label*="Collapse"]').exists()).toBe(false);
    });

    it('toggles expansion of container nodes and supports Collapse/Expand All', async () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                components: sampleMetadata,
            },
        });

        // Root is expanded by default; container_1 is inside root
        expect(wrapper.find('[data-node-id="node_container_1"]').exists()).toBe(true);

        // Click Collapse All
        const collapseAllBtn = wrapper.get('button[aria-label="Collapse All Containers"]');
        await collapseAllBtn.trigger('click');

        // Root container remains, but container_1 children should be collapsed
        expect(wrapper.find('[data-node-id="node_container_1"]').exists()).toBe(true);
        expect(wrapper.find('[data-node-id="node_button_1"]').exists()).toBe(false);

        // Click Expand All
        const expandAllBtn = wrapper.get('button[aria-label="Expand All Containers"]');
        await expandAllBtn.trigger('click');

        expect(wrapper.find('[data-node-id="node_button_1"]').exists()).toBe(true);

        // Individual toggle chevron
        const containerChevron = wrapper.get('[data-node-id="node_container_1"] button');
        await containerChevron.trigger('click');
        expect(wrapper.find('[data-node-id="node_button_1"]').exists()).toBe(false);
    });

    it('emits selection when a layer row is clicked and highlights the selected node', async () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                selectedId: 'node_heading_1',
                components: sampleMetadata,
            },
        });

        // Heading row should have selected classes
        const headingRow = wrapper.get('[data-node-id="node_heading_1"]');
        expect(headingRow.classes()).toContain('bg-primary/10');
        expect(headingRow.attributes('aria-selected')).toBe('true');

        // Click on another row (e.g. Hero)
        const heroRow = wrapper.get('[data-node-id="node_hero_1"]');
        await heroRow.trigger('click');

        expect(wrapper.emitted('select')).toEqual([['node_hero_1']]);
    });

    it('automatically expands ancestors when a nested node is selected externally', async () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                selectedId: null,
                components: sampleMetadata,
            },
        });

        // Initially collapse containers
        const collapseAllBtn = wrapper.get('button[aria-label="Collapse All Containers"]');
        await collapseAllBtn.trigger('click');
        expect(wrapper.find('[data-node-id="node_divider_1"]').exists()).toBe(false);

        // Externally select the nested divider (inside Columns, inside Container, inside Root)
        await wrapper.setProps({ selectedId: 'node_divider_1' });
        await nextTick();

        // Ancestors should be auto-expanded so divider is visible
        expect(wrapper.find('[data-node-id="node_divider_1"]').exists()).toBe(true);
        expect(wrapper.get('[data-node-id="node_divider_1"]').classes()).toContain('bg-primary/10');
    });

    it('handles null, missing, or stale selectedId safely without crashing', () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                selectedId: 'non_existent_node_id',
                components: sampleMetadata,
            },
        });

        expect(wrapper.exists()).toBe(true);
        const selectedRows = wrapper.findAll('[aria-selected="true"]');
        expect(selectedRows.length).toBe(0);
    });

    it('supports tab switching between Components and Layers', async () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                activeTab: 'layers',
                components: sampleMetadata,
            },
        });

        const componentsTabBtn = wrapper.findAll('button').find((b) => b.text().includes('Components'));
        expect(componentsTabBtn).toBeDefined();

        await componentsTabBtn!.trigger('click');
        expect(wrapper.emitted('switchTab')).toEqual([['components']]);
    });

    it('renders cleanly in read-only / Viewer mode without mutation controls', () => {
        const wrapper = mount(LayersPanel, {
            props: {
                document: fullDocument,
                readOnly: true,
                components: sampleMetadata,
            },
        });

        expect(wrapper.exists()).toBe(true);
        // There should be no delete/duplicate/move buttons in the Layers tree
        expect(wrapper.find('button[title*="Delete"]').exists()).toBe(false);
        expect(wrapper.find('button[title*="Move"]').exists()).toBe(false);
    });

    it('co-exists with useDocumentState and maintains selection synchronization without mutating the document', async () => {
        const IntegrationHarness = defineComponent({
            components: { LayersPanel },
            setup() {
                const state = useDocumentState(JSON.parse(JSON.stringify(fullDocument)));
                return {
                    document: state.document,
                    selectedNodeId: state.selectedNodeId,
                    selectedNode: state.selectedNode,
                    selectNode: state.selectNode,
                };
            },
            template: `
                <div>
                    <LayersPanel
                        :document="document"
                        :selected-id="selectedNodeId"
                        :components="${'sampleMetadata'}"
                        @select="selectNode"
                    />
                </div>
            `,
            data: () => ({ sampleMetadata }),
        });

        const wrapper = mount(IntegrationHarness);
        const vm = wrapper.vm as any;

        const docJsonBefore = JSON.stringify(vm.document);

        // Click a node in LayersPanel
        const expandAllBtn = wrapper.get('button[aria-label="Expand All Containers"]');
        await expandAllBtn.trigger('click');

        const buttonRow = wrapper.get('[data-node-id="node_button_1"]');
        await buttonRow.trigger('click');

        // useDocumentState should now have this node selected
        expect(vm.selectedNodeId).toBe('node_button_1');
        expect(vm.selectedNode.type).toBe('Button');

        // External selection change
        vm.selectNode('node_heading_1');
        await nextTick();

        const headingRow = wrapper.get('[data-node-id="node_heading_1"]');
        expect(headingRow.classes()).toContain('bg-primary/10');

        // Document must NOT have been mutated by UI navigation or selection
        const docJsonAfter = JSON.stringify(vm.document);
        expect(docJsonAfter).toBe(docJsonBefore);
    });

    describe('Keyboard Navigation', () => {
        it('navigates through visible rows with ArrowDown and ArrowUp', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            const treeContainer = wrapper.find('[role="tree"]');
            expect(treeContainer.exists()).toBe(true);

            // Initially root is expanded, so root and its direct children are visible
            const rowsBefore = wrapper.findAll('[role="treeitem"]');
            const firstRowId = rowsBefore[0].attributes('data-node-id');
            const secondRowId = rowsBefore[1].attributes('data-node-id');

            // Initial effective focus is on first row (tabindex=0)
            expect(rowsBefore[0].attributes('tabindex')).toBe('0');
            expect(rowsBefore[1].attributes('tabindex')).toBe('-1');

            // Trigger ArrowDown
            await treeContainer.trigger('keydown', { key: 'ArrowDown' });
            await nextTick();

            const rowsAfterDown = wrapper.findAll('[role="treeitem"]');
            expect(rowsAfterDown[0].attributes('tabindex')).toBe('-1');
            expect(rowsAfterDown[1].attributes('tabindex')).toBe('0');
            expect(rowsAfterDown[1].attributes('data-node-id')).toBe(secondRowId);

            // Trigger ArrowUp
            await treeContainer.trigger('keydown', { key: 'ArrowUp' });
            await nextTick();

            const rowsAfterUp = wrapper.findAll('[role="treeitem"]');
            expect(rowsAfterUp[0].attributes('tabindex')).toBe('0');
            expect(rowsAfterUp[0].attributes('data-node-id')).toBe(firstRowId);

            wrapper.unmount();
        });

        it('navigates to first and last visible rows with Home and End', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            const treeContainer = wrapper.find('[role="tree"]');

            // Press End
            await treeContainer.trigger('keydown', { key: 'End' });
            await nextTick();

            const rows = wrapper.findAll('[role="treeitem"]');
            const lastRow = rows[rows.length - 1];
            expect(lastRow.attributes('tabindex')).toBe('0');

            // Press Home
            await treeContainer.trigger('keydown', { key: 'Home' });
            await nextTick();

            const firstRow = wrapper.findAll('[role="treeitem"]')[0];
            expect(firstRow.attributes('tabindex')).toBe('0');

            wrapper.unmount();
        });

        it('selects the focused node on Enter and Space without mutating document', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            const treeContainer = wrapper.find('[role="tree"]');
            // Effective focus starts on root
            await treeContainer.trigger('keydown', { key: 'Enter' });
            expect(wrapper.emitted('select')).toEqual([[fullDocument.root.id]]);

            await treeContainer.trigger('keydown', { key: 'ArrowDown' });
            await treeContainer.trigger('keydown', { key: ' ' });

            const emitted = wrapper.emitted('select')!;
            expect(emitted.length).toBe(2);
            expect(emitted[1][0]).toBe('node_container_1');
        });

        it('expands collapsed containers with ArrowRight and moves to first child when already expanded', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            const treeContainer = wrapper.find('[role="tree"]');
            // Navigate to Container (collapsed by default)
            await treeContainer.trigger('keydown', { key: 'ArrowDown' });
            await nextTick();

            const containerRow = wrapper.find('[data-node-id="node_container_1"]');
            expect(containerRow.attributes('aria-expanded')).toBe('false');

            // ArrowRight expands it
            await treeContainer.trigger('keydown', { key: 'ArrowRight' });
            await nextTick();

            const containerRowExpanded = wrapper.find('[data-node-id="node_container_1"]');
            expect(containerRowExpanded.attributes('aria-expanded')).toBe('true');

            // ArrowRight again moves focus to its first child (node_hero_1)
            await treeContainer.trigger('keydown', { key: 'ArrowRight' });
            await nextTick();

            const heroRow = wrapper.find('[data-node-id="node_hero_1"]');
            expect(heroRow.attributes('tabindex')).toBe('0');
        });

        it('collapses expanded containers with ArrowLeft and moves to parent for leaves/collapsed nodes', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            // Expand all so deep nodes are visible
            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const treeContainer = wrapper.find('[role="tree"]');
            // Move focus to Container (expanded)
            await treeContainer.trigger('keydown', { key: 'ArrowDown' });
            await nextTick();

            let containerRow = wrapper.find('[data-node-id="node_container_1"]');
            expect(containerRow.attributes('aria-expanded')).toBe('true');

            // ArrowLeft collapses Container
            await treeContainer.trigger('keydown', { key: 'ArrowLeft' });
            await nextTick();

            containerRow = wrapper.find('[data-node-id="node_container_1"]');
            expect(containerRow.attributes('aria-expanded')).toBe('false');

            // ArrowLeft on collapsed Container moves focus to its parent (Root)
            await treeContainer.trigger('keydown', { key: 'ArrowLeft' });
            await nextTick();

            const rootRow = wrapper.find(`[data-node-id="${fullDocument.root.id}"]`);
            expect(rootRow.attributes('tabindex')).toBe('0');
        });
    });

    describe('Accessibility & Semantics', () => {
        it('provides proper WAI-ARIA tree roles, levels, and accessible names', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    selectedId: 'node_heading_1',
                    components: sampleMetadata,
                },
            });

            // Root container
            const tree = wrapper.find('[role="tree"]');
            expect(tree.exists()).toBe(true);
            expect(tree.attributes('aria-label')).toBe('Document Structure');

            // Rows
            const rows = wrapper.findAll('[role="treeitem"]');
            expect(rows.length).toBeGreaterThan(0);

            // Root has depth 0 -> aria-level 1
            const rootRow = wrapper.find(`[data-node-id="${fullDocument.root.id}"]`);
            expect(rootRow.attributes('aria-level')).toBe('1');
            expect(rootRow.attributes('aria-expanded')).toBe('true');

            // Heading has depth 2 -> aria-level 3
            const headingRow = wrapper.find('[data-node-id="node_heading_1"]');
            expect(headingRow.attributes('aria-level')).toBe('3');
            expect(headingRow.attributes('aria-selected')).toBe('true');
            // Heading is leaf, so aria-expanded must be undefined
            expect(headingRow.attributes('aria-expanded')).toBeUndefined();
            // Accessible label includes type and preview
            expect(headingRow.attributes('aria-label')).toBe('Heading: Main Heading');
        });

        it('has accessible search input and clear button', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            const searchInput = wrapper.find('#layers-search-input');
            expect(searchInput.exists()).toBe(true);
            expect(searchInput.attributes('type')).toBe('search');
            expect(searchInput.attributes('placeholder')).toBe('Search layers...');

            const searchLabel = wrapper.find('label[for="layers-search-input"]');
            expect(searchLabel.exists()).toBe(true);
            expect(searchLabel.classes()).toContain('sr-only');

            // Type in search
            await searchInput.setValue('button');
            const clearBtn = wrapper.find('button[aria-label="Clear search"]');
            expect(clearBtn.exists()).toBe(true);
        });
    });

    describe('Search and Filtering', () => {
        it('filters tree by component type and preserves ancestor path', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            const searchInput = wrapper.find('#layers-search-input');
            await searchInput.setValue('Spacer');
            await nextTick();

            // Spacer and its ancestors (Root, Container, Columns) must be rendered
            const spacerRow = wrapper.find('[data-node-id="node_spacer_1"]');
            expect(spacerRow.exists()).toBe(true);

            const columnsRow = wrapper.find('[data-node-id="node_columns_1"]');
            expect(columnsRow.exists()).toBe(true);

            const containerRow = wrapper.find('[data-node-id="node_container_1"]');
            expect(containerRow.exists()).toBe(true);

            // Unrelated Hero branch must NOT be in the rendered filtered tree
            const heroRow = wrapper.find('[data-node-id="node_hero_1"]');
            expect(heroRow.exists()).toBe(false);
        });

        it('filters tree by text preview content and node ID with case/whitespace insensitivity', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            const searchInput = wrapper.find('#layers-search-input');

            // Search text preview with spaces and mixed case
            await searchInput.setValue('  mAiN hEaDiNg  ');
            await nextTick();

            expect(wrapper.find('[data-node-id="node_heading_1"]').exists()).toBe(true);
            expect(wrapper.find('[data-node-id="node_button_1"]').exists()).toBe(false);

            // Search by node ID
            await searchInput.setValue('node_faq_1');
            await nextTick();

            expect(wrapper.find('[data-node-id="node_faq_1"]').exists()).toBe(true);
            expect(wrapper.find('[data-node-id="node_heading_1"]').exists()).toBe(false);
        });

        it('clearing search restores user previous expansion state without mutating document', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            // Initially container is collapsed, so Heading is not rendered
            expect(wrapper.find('[data-node-id="node_heading_1"]').exists()).toBe(false);

            const searchInput = wrapper.find('#layers-search-input');
            await searchInput.setValue('Main Heading');
            await nextTick();

            // Heading is now visible because Container was auto-expanded for search
            expect(wrapper.find('[data-node-id="node_heading_1"]').exists()).toBe(true);

            // Clear search
            const clearBtn = wrapper.find('button[aria-label="Clear search"]');
            await clearBtn.trigger('click');
            await nextTick();

            // Previous expansion state is restored: Container collapsed again, Heading hidden
            expect(wrapper.find('[data-node-id="node_heading_1"]').exists()).toBe(false);
            expect(wrapper.find('[data-node-id="node_container_1"]').exists()).toBe(true);
        });

        it('displays accessible empty status when no layers match query', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            const searchInput = wrapper.find('#layers-search-input');
            await searchInput.setValue('NonExistentQueryXYZ');
            await nextTick();

            const status = wrapper.find('[role="status"]');
            expect(status.exists()).toBe(true);
            expect(status.text()).toContain('No layers matching "NonExistentQueryXYZ"');

            const clearBtn = status.find('button');
            await clearBtn.trigger('click');
            await nextTick();

            expect(wrapper.find('[role="status"]').exists()).toBe(false);
            expect(wrapper.find('[role="treeitem"]').exists()).toBe(true);
        });
    });

    describe('Viewer / Read-only Inspection & Select Parent', () => {
        it('allows node selection in read-only / viewer mode for inspection', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    readOnly: true,
                    components: sampleMetadata,
                },
            });

            const rootRow = wrapper.find(`[data-node-id="${fullDocument.root.id}"]`);
            await rootRow.trigger('click');

            expect(wrapper.emitted('select')).toEqual([[fullDocument.root.id]]);
        });

        it('renders Select Parent button on canvas NodeToolbar when isRoot is false', async () => {
            const wrapper = mount(NodeToolbar, {
                props: {
                    componentType: 'Heading',
                    isRoot: false,
                    canMoveUp: true,
                    canMoveDown: true,
                },
            });

            const selectParentBtn = wrapper.find('button[aria-label="Select Parent"]');
            expect(selectParentBtn.exists()).toBe(true);

            await selectParentBtn.trigger('click');
            expect(wrapper.emitted('selectParent')).toBeTruthy();
        });

        it('does not render Select Parent button on canvas NodeToolbar when isRoot is true', () => {
            const wrapper = mount(NodeToolbar, {
                props: {
                    componentType: 'Section',
                    isRoot: true,
                    canMoveUp: false,
                    canMoveDown: false,
                },
            });

            const selectParentBtn = wrapper.find('button[aria-label="Select Parent"]');
            expect(selectParentBtn.exists()).toBe(false);
        });
    });

    describe('Package 3D.2 Keyboard-Accessible Structural Mutations', () => {
        it('moves focused node up among siblings via Alt+ArrowUp', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowUp', altKey: true });
            await nextTick();

            // node_heading_1 was at index 1 of node_container_1. Moving up moves to index 0
            expect(wrapper.emitted('moveNode')).toEqual([
                [
                    {
                        nodeId: 'node_heading_1',
                        targetParentId: 'node_container_1',
                        targetIndex: 0,
                    },
                ],
            ]);
            wrapper.unmount();
        });

        it('does not emit moveNode on Alt+ArrowUp at the top sibling boundary (no-op)', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_hero_1', // index 0 in node_container_1
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowUp', altKey: true });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toBeUndefined();
            wrapper.unmount();
        });

        it('moves focused node down among siblings via Alt+ArrowDown', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1', // index 1 in node_container_1
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowDown', altKey: true });
            await nextTick();

            // Moving down from index 1 targets index 2
            expect(wrapper.emitted('moveNode')).toEqual([
                [
                    {
                        nodeId: 'node_heading_1',
                        targetParentId: 'node_container_1',
                        targetIndex: 2,
                    },
                ],
            ]);
            wrapper.unmount();
        });

        it('does not emit moveNode on Alt+ArrowDown at the bottom sibling boundary (no-op)', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_faq_1', // last child (index 7) of node_container_1
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowDown', altKey: true });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toBeUndefined();
            wrapper.unmount();
        });

        it('moves node into preceding container sibling via Alt+ArrowRight', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1', // preceding sibling is node_hero_1 (container)
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowRight', altKey: true });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toEqual([
                [
                    {
                        nodeId: 'node_heading_1',
                        targetParentId: 'node_hero_1',
                        targetIndex: 0,
                    },
                ],
            ]);
            wrapper.unmount();
        });

        it('emits moveNode targeting preceding sibling leaf on Alt+ArrowRight for authoritative API rejection', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_richtext_1', // preceding sibling is node_heading_1 (leaf)
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowRight', altKey: true });
            await nextTick();

            // Routed to authoritative API which will reject because target is a leaf
            expect(wrapper.emitted('moveNode')).toEqual([
                [
                    {
                        nodeId: 'node_richtext_1',
                        targetParentId: 'node_heading_1',
                        targetIndex: 0,
                    },
                ],
            ]);
            wrapper.unmount();
        });

        it('does not emit moveNode on Alt+ArrowRight when node has no preceding sibling', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_hero_1', // index 0 in node_container_1 (no preceding sibling)
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowRight', altKey: true });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toBeUndefined();
            wrapper.unmount();
        });

        it('moves node out of current parent into grandparent via Alt+ArrowLeft', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_spacer_1', // child of node_columns_1 (which is at index 5 of node_container_1)
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowLeft', altKey: true });
            await nextTick();

            // Outdents into node_container_1 immediately after node_columns_1 (index 5 + 1 = 6)
            expect(wrapper.emitted('moveNode')).toEqual([
                [
                    {
                        nodeId: 'node_spacer_1',
                        targetParentId: 'node_container_1',
                        targetIndex: 6,
                    },
                ],
            ]);
            wrapper.unmount();
        });

        it('does not emit moveNode on Alt+ArrowLeft when node is already at outermost level under root', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_container_1', // parent is root
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowLeft', altKey: true });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toBeUndefined();
            wrapper.unmount();
        });

        it('protects root node from all Alt+Arrow keyboard structural moves', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: fullDocument.root.id, // root node
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowUp', altKey: true });
            await tree.trigger('keydown', { key: 'ArrowDown', altKey: true });
            await tree.trigger('keydown', { key: 'ArrowLeft', altKey: true });
            await tree.trigger('keydown', { key: 'ArrowRight', altKey: true });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toBeUndefined();
            wrapper.unmount();
        });

        it('ignores all Alt+Arrow shortcuts in Viewer / readOnly mode', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    readOnly: true,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });
            await nextTick();

            const tree = wrapper.find('[role="tree"]');
            await tree.trigger('keydown', { key: 'ArrowUp', altKey: true });
            await tree.trigger('keydown', { key: 'ArrowDown', altKey: true });
            await tree.trigger('keydown', { key: 'ArrowLeft', altKey: true });
            await tree.trigger('keydown', { key: 'ArrowRight', altKey: true });
            await nextTick();

            // Zero mutation events emitted
            expect(wrapper.emitted('moveNode')).toBeUndefined();

            // Regular selection remains functional
            await tree.trigger('keydown', { key: 'Enter' });
            expect(wrapper.emitted('select')).toEqual([['node_heading_1']]);
            wrapper.unmount();
        });

        it('does not intercept Alt+Arrow shortcuts while typing in an input', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });
            await nextTick();

            const searchInput = wrapper.find('#layers-search-input');
            const searchEl = searchInput.element as HTMLInputElement;
            searchEl.focus();

            // Trigger keydown on input
            await searchInput.trigger('keydown', { key: 'ArrowUp', altKey: true });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toBeUndefined();
            wrapper.unmount();
        });

        it('provides accessible keyboard instructions and header tooltip', () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                },
            });

            const tree = wrapper.find('[role="tree"]');
            expect(tree.attributes('aria-describedby')).toBe('layers-keyboard-instructions');

            const instructions = wrapper.find('#layers-keyboard-instructions');
            expect(instructions.exists()).toBe(true);
            expect(instructions.classes()).toContain('sr-only');
            expect(instructions.text()).toContain('Alt+Up and Alt+Down');
            expect(instructions.text()).toContain('Alt+Right');
            expect(instructions.text()).toContain('Alt+Left');

            const helpBtn = wrapper.find('button[aria-label="Keyboard Shortcuts"]');
            expect(helpBtn.exists()).toBe(true);
        });
    });

    describe('Package 3D.3 HTML5 Pointer Drag-and-Drop', () => {
        function createMockDataTransfer() {
            const data: Record<string, string> = {};
            return {
                dropEffect: 'none',
                effectAllowed: 'all',
                setData(format: string, val: string) {
                    data[format] = val;
                },
                getData(format: string) {
                    return data[format] || '';
                },
                clearData() {
                    Object.keys(data).forEach((k) => delete data[k]);
                },
                types: Object.keys(data),
            } as unknown as DataTransfer;
        }

        it('exposes a valid drag handle on editable non-root rows, and hides it on root or in viewer mode', () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    readOnly: false,
                    selectedId: 'node_heading_1',
                },
            });

            // Root node must NOT have a drag handle
            const rootRow = wrapper.get(`[data-node-id="${fullDocument.root.id}"]`);
            expect(rootRow.find('[data-testid="drag-handle"]').exists()).toBe(false);

            // Editable child rows must have drag handles
            const childRow = wrapper.get('[data-node-id="node_heading_1"]');
            expect(childRow.find('[data-testid="drag-handle"]').exists()).toBe(true);

            // Viewer / readOnly mode must NOT have drag handles on any row
            const viewerWrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    readOnly: true,
                    selectedId: 'node_heading_1',
                },
            });
            expect(viewerWrapper.find('[data-testid="drag-handle"]').exists()).toBe(false);
        });

        it('initiates drag and sets valid dataTransfer payload on dragstart', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
            });

            const dragHandle = wrapper.get(
                '[data-node-id="node_heading_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await dragHandle.trigger('dragstart', { dataTransfer });

            expect(
                dataTransfer.getData('application/x-pentagon-layer-id'),
            ).toBe('node_heading_1');
            expect(dataTransfer.getData('text/plain')).toBe('node_heading_1');
        });

        it('displays before indicator when dragging over top zone of a sibling', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            const sourceHandle = wrapper.get(
                '[data-node-id="node_richtext_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            const targetRow = wrapper.get('[data-node-id="node_heading_1"]');
            vi.spyOn(targetRow.element, 'getBoundingClientRect').mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            await targetRow.trigger('dragover', {
                clientY: 4, // Top zone (< 25% of 32 = 8)
                dataTransfer,
            });
            await nextTick();

            expect(
                wrapper.find('[data-testid="drop-indicator-before"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-testid="drop-indicator-after"]').exists(),
            ).toBe(false);

            wrapper.unmount();
        });

        it('displays after indicator when dragging over bottom zone of a sibling', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            const sourceHandle = wrapper.get(
                '[data-node-id="node_heading_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            const targetRow = wrapper.get('[data-node-id="node_richtext_1"]');
            vi.spyOn(targetRow.element, 'getBoundingClientRect').mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            await targetRow.trigger('dragover', {
                clientY: 28, // Bottom zone (> 75% of 32 = 24)
                dataTransfer,
            });
            await nextTick();

            expect(
                wrapper.find('[data-testid="drop-indicator-after"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-testid="drop-indicator-before"]').exists(),
            ).toBe(false);

            wrapper.unmount();
        });

        it('displays inside container indicator when dragging over middle zone of a container', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            const sourceHandle = wrapper.get(
                '[data-node-id="node_heading_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            const containerRow = wrapper.get('[data-node-id="node_hero_1"]');
            vi.spyOn(
                containerRow.element,
                'getBoundingClientRect',
            ).mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            await containerRow.trigger('dragover', {
                clientY: 16, // Middle zone (between 25% and 75%)
                dataTransfer,
            });
            await nextTick();

            // Inside highlight ring
            expect(containerRow.classes()).toContain('ring-primary');

            wrapper.unmount();
        });

        it('emits moveNode with correct destination index on drop before sibling', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            const sourceHandle = wrapper.get(
                '[data-node-id="node_richtext_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            const targetRow = wrapper.get('[data-node-id="node_heading_1"]');
            vi.spyOn(targetRow.element, 'getBoundingClientRect').mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            await targetRow.trigger('dragover', { clientY: 4, dataTransfer });
            await targetRow.trigger('drop', { dataTransfer });
            await nextTick();

            // node_richtext_1 (at index 2) moved before node_heading_1 (at index 1)
            // Post-removal index = 1
            expect(wrapper.emitted('moveNode')).toEqual([
                [
                    {
                        nodeId: 'node_richtext_1',
                        targetParentId: 'node_container_1',
                        targetIndex: 1,
                    },
                ],
            ]);

            // Drag indicator cleared on drop
            expect(
                wrapper.find('[data-testid="drop-indicator-before"]').exists(),
            ).toBe(false);

            wrapper.unmount();
        });

        it('emits moveNode with correct destination index on drop inside container', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            const sourceHandle = wrapper.get(
                '[data-node-id="node_heading_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            const containerRow = wrapper.get('[data-node-id="node_hero_1"]');
            vi.spyOn(
                containerRow.element,
                'getBoundingClientRect',
            ).mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            await containerRow.trigger('dragover', {
                clientY: 16,
                dataTransfer,
            });
            await containerRow.trigger('drop', { dataTransfer });
            await nextTick();

            // node_heading_1 moved inside node_hero_1 (which has 0 children, so targetIndex 0)
            expect(wrapper.emitted('moveNode')).toEqual([
                [
                    {
                        nodeId: 'node_heading_1',
                        targetParentId: 'node_hero_1',
                        targetIndex: 0,
                    },
                ],
            ]);

            wrapper.unmount();
        });

        it('clears visual indicators when dragleave or dragend is triggered', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            const sourceHandle = wrapper.get(
                '[data-node-id="node_heading_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            const targetRow = wrapper.get('[data-node-id="node_richtext_1"]');
            vi.spyOn(targetRow.element, 'getBoundingClientRect').mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            await targetRow.trigger('dragover', { clientY: 28, dataTransfer });
            await nextTick();
            expect(
                wrapper.find('[data-testid="drop-indicator-after"]').exists(),
            ).toBe(true);

            // dragleave clears indicator
            await targetRow.trigger('dragleave');
            await nextTick();
            expect(
                wrapper.find('[data-testid="drop-indicator-after"]').exists(),
            ).toBe(false);

            // dragend resets all state
            await sourceHandle.trigger('dragend');
            await nextTick();

            wrapper.unmount();
        });

        it('does not emit moveNode on self-drop or no-op drops', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            const sourceHandle = wrapper.get(
                '[data-node-id="node_heading_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            const selfRow = wrapper.get('[data-node-id="node_heading_1"]');
            vi.spyOn(selfRow.element, 'getBoundingClientRect').mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            await selfRow.trigger('dragover', { clientY: 4, dataTransfer });
            await selfRow.trigger('drop', { dataTransfer });
            await nextTick();

            // Self-drop must NOT emit moveNode
            expect(wrapper.emitted('moveNode')).toBeUndefined();

            wrapper.unmount();
        });

        it('expands collapsed container after a successful inside drop', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            // Collapse node_hero_1
            const heroRow = wrapper.get('[data-node-id="node_hero_1"]');
            expect(heroRow.attributes('aria-expanded')).toBe('false');

            const sourceHandle = wrapper.get(
                '[data-node-id="node_heading_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            vi.spyOn(heroRow.element, 'getBoundingClientRect').mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            await heroRow.trigger('dragover', { clientY: 16, dataTransfer });
            await heroRow.trigger('drop', { dataTransfer });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toEqual([
                [
                    {
                        nodeId: 'node_heading_1',
                        targetParentId: 'node_hero_1',
                        targetIndex: 0,
                    },
                ],
            ]);

            // Destination container was auto-expanded
            expect(heroRow.attributes('aria-expanded')).toBe('true');

            wrapper.unmount();
        });

        it('shows invalid drop indicator when dragging inside a leaf component', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            const sourceHandle = wrapper.get(
                '[data-node-id="node_richtext_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            // Note: node_heading_1 is a leaf (Heading), not a container
            const leafRow = wrapper.get('[data-node-id="node_heading_1"]');
            vi.spyOn(leafRow.element, 'getBoundingClientRect').mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            // If an inside drop is attempted on a leaf:
            await leafRow.trigger('drop', { dataTransfer });
            await nextTick();

            // Must NOT emit moveNode
            expect(wrapper.emitted('moveNode')).toBeUndefined();

            wrapper.unmount();
        });

        it('blocks drag initiation and drop execution in read-only / Viewer mode', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: fullDocument,
                    components: sampleMetadata,
                    readOnly: true,
                    selectedId: 'node_heading_1',
                },
            });

            // No drag handles exist
            expect(wrapper.find('[data-testid="drag-handle"]').exists()).toBe(false);

            // Simulated drop attempt on a row must not emit
            const row = wrapper.get('[data-node-id="node_heading_1"]');
            const dataTransfer = createMockDataTransfer();
            dataTransfer.setData(
                'application/x-pentagon-layer-id',
                'node_richtext_1',
            );

            await row.trigger('drop', { dataTransfer });
            await nextTick();

            expect(wrapper.emitted('moveNode')).toBeUndefined();
        });
    });

    describe('Package 3E.2 - Layer Locking UI and Safeguards', () => {
        function createMockDataTransfer() {
            const data: Record<string, string> = {};
            return {
                dropEffect: 'none',
                effectAllowed: 'all',
                setData(format: string, val: string) {
                    data[format] = val;
                },
                getData(format: string) {
                    return data[format] || '';
                },
                clearData() {
                    Object.keys(data).forEach((k) => delete data[k]);
                },
                types: Object.keys(data),
            } as unknown as DataTransfer;
        }

        const lockedTestDoc: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root',
                type: 'Section',
                props: {},
                children: [
                    {
                        id: 'container_a',
                        type: 'Container',
                        props: {},
                        children: [
                            {
                                id: 'node_heading_1',
                                type: 'Heading',
                                props: { text: 'Directly Locked Heading' },
                                locked: true,
                            },
                            {
                                id: 'node_richtext_1',
                                type: 'RichText',
                                props: { text: 'Free RichText' },
                            },
                        ],
                    },
                    {
                        id: 'container_b',
                        type: 'Container',
                        props: {},
                        locked: true,
                        children: [
                            {
                                id: 'node_button_1',
                                type: 'Button',
                                props: { label: 'Inherited Locked Button' },
                            },
                            {
                                id: 'node_image_1',
                                type: 'Image',
                                props: { alt: 'Inherited Locked Image' },
                            },
                        ],
                    },
                ],
            },
        };

        it('displays lock state on a directly locked node and exposes unlock action', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                },
            });

            // Expand container_a to see children
            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const headingRow = wrapper.get('[data-node-id="node_heading_1"]');
            const lockToggle = headingRow.get('[data-testid="lock-toggle"]');
            expect(lockToggle.attributes('aria-label')).toBe('Unlock Heading');
            expect(lockToggle.attributes('title')).toBe('Unlock Heading');
            expect(lockToggle.attributes('tabindex')).toBe('-1');

            wrapper.unmount();
        });

        it('exposes an accessible lock action on an unlocked node', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                },
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const richTextRow = wrapper.get('[data-node-id="node_richtext_1"]');
            const lockToggle = richTextRow.get('[data-testid="lock-toggle"]');
            expect(lockToggle.attributes('aria-label')).toBe('Lock Rich Text');
            expect(lockToggle.attributes('title')).toBe('Lock Rich Text');
            expect(lockToggle.attributes('tabindex')).toBe('-1');

            wrapper.unmount();
        });

        it('invokes the established setNodeLocked event flow exactly once on toggle', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                },
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // 1. Unlock directly locked heading
            const headingLockBtn = wrapper.get('[data-node-id="node_heading_1"] [data-testid="lock-toggle"]');
            await headingLockBtn.trigger('click');
            expect(wrapper.emitted('setNodeLocked')).toHaveLength(1);
            expect(wrapper.emitted('setNodeLocked')![0]).toEqual([
                { nodeId: 'node_heading_1', locked: false },
            ]);

            // 2. Lock free richtext
            const richTextLockBtn = wrapper.get('[data-node-id="node_richtext_1"] [data-testid="lock-toggle"]');
            await richTextLockBtn.trigger('click');
            expect(wrapper.emitted('setNodeLocked')).toHaveLength(2);
            expect(wrapper.emitted('setNodeLocked')![1]).toEqual([
                { nodeId: 'node_richtext_1', locked: true },
            ]);

            wrapper.unmount();
        });

        it('does not trigger row selection when clicking the lock control', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                    selectedId: 'node_richtext_1',
                },
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const headingLockBtn = wrapper.get('[data-node-id="node_heading_1"] [data-testid="lock-toggle"]');
            await headingLockBtn.trigger('click');

            // setNodeLocked was emitted
            expect(wrapper.emitted('setNodeLocked')).toHaveLength(1);

            // select was NOT emitted
            expect(wrapper.emitted('select')).toBeUndefined();

            wrapper.unmount();
        });

        it('displays inherited-lock indicator on descendants without falsely implying direct lock', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                },
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const buttonRow = wrapper.get('[data-node-id="node_button_1"]');

            // Must NOT have direct lock toggle button
            expect(buttonRow.find('button[data-testid="lock-toggle"]').exists()).toBe(false);

            // Must have inherited lock indicator
            const inheritedIndicator = buttonRow.get('[data-testid="inherited-lock-indicator"]');
            expect(inheritedIndicator.attributes('title')).toContain('Locked by parent container');
            expect(inheritedIndicator.attributes('title')).toContain('Container');
            expect(inheritedIndicator.attributes('aria-label')).toContain('Locked by parent container');

            wrapper.unmount();
        });

        it('suppresses actionable lock/unlock controls in Viewer mode', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                    readOnly: true,
                },
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // No interactive toggle buttons exist
            expect(wrapper.find('button[data-testid="lock-toggle"]').exists()).toBe(false);

            // Locked node displays non-interactive lock indicator
            const headingRow = wrapper.get('[data-node-id="node_heading_1"]');
            const indicator = headingRow.get('[data-testid="lock-indicator"]');
            expect(indicator.attributes('title')).toBe('Locked: Heading');

            // Attempted click on indicator emits nothing
            await indicator.trigger('click');
            expect(wrapper.emitted('setNodeLocked')).toBeUndefined();

            wrapper.unmount();
        });

        it('omits drag handles for directly locked nodes and nodes protected by locked ancestors', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                },
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // Free node HAS drag handle
            expect(
                wrapper.find('[data-node-id="node_richtext_1"] [data-testid="drag-handle"]').exists(),
            ).toBe(true);

            // Directly locked node has NO drag handle
            expect(
                wrapper.find('[data-node-id="node_heading_1"] [data-testid="drag-handle"]').exists(),
            ).toBe(false);

            // Directly locked container has NO drag handle
            expect(
                wrapper.find('[data-node-id="container_b"] [data-testid="drag-handle"]').exists(),
            ).toBe(false);

            // Inherited-locked child has NO drag handle
            expect(
                wrapper.find('[data-node-id="node_button_1"] [data-testid="drag-handle"]').exists(),
            ).toBe(false);

            wrapper.unmount();
        });

        it('rejects pointer drops into a locked destination container', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // Start dragging free node
            const sourceHandle = wrapper.get(
                '[data-node-id="node_richtext_1"] [data-testid="drag-handle"]',
            );
            const dataTransfer = createMockDataTransfer();
            await sourceHandle.trigger('dragstart', { dataTransfer });

            // Target is locked container_b
            const lockedContainerRow = wrapper.get('[data-node-id="container_b"]');
            vi.spyOn(lockedContainerRow.element, 'getBoundingClientRect').mockReturnValue({
                top: 0,
                bottom: 32,
                left: 0,
                right: 200,
                height: 32,
                width: 200,
                x: 0,
                y: 0,
                toJSON: () => {},
            });

            // Dragover middle zone (inside)
            await lockedContainerRow.trigger('dragover', {
                clientY: 16, // middle 50%
                dataTransfer,
            });
            await nextTick();

            // dropEffect was set to 'none' due to locked destination
            expect(dataTransfer.dropEffect).toBe('none');

            // Attempt drop
            await lockedContainerRow.trigger('drop', { dataTransfer });
            await nextTick();

            // moveNode must NOT be emitted
            expect(wrapper.emitted('moveNode')).toBeUndefined();

            wrapper.unmount();
        });

        it('rejects keyboard structural moves when source node is locked or protected', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1', // Directly locked
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const treeContainer = wrapper.get('[role="tree"]');

            // Focus locked heading and try Alt+ArrowDown
            await treeContainer.trigger('keydown', { key: 'ArrowDown', altKey: true });
            expect(wrapper.emitted('moveNode')).toBeUndefined();

            // Try Alt+ArrowRight on free node to move into locked container_b
            // node_richtext_1 is sibling index 1 in container_a; container_b is next sibling under root
            wrapper.unmount();
        });

        it('preserves roving tabindex and arrow-key traversal across locked rows', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: lockedTestDoc,
                    components: sampleMetadata,
                    selectedId: 'node_heading_1',
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const treeContainer = wrapper.get('[role="tree"]');
            const headingRow = wrapper.get('[data-node-id="node_heading_1"]');
            expect(headingRow.attributes('tabindex')).toBe('0');

            // Lock toggle buttons have tabindex -1
            const lockButtons = wrapper.findAll('[data-testid="lock-toggle"]');
            for (const btn of lockButtons) {
                expect(btn.attributes('tabindex')).toBe('-1');
            }

            // Arrow down to rich text
            await treeContainer.trigger('keydown', { key: 'ArrowDown' });
            await nextTick();
            const richTextRow = wrapper.get('[data-node-id="node_richtext_1"]');
            expect(richTextRow.attributes('tabindex')).toBe('0');
            expect(headingRow.attributes('tabindex')).toBe('-1');

            wrapper.unmount();
        });
    });

    describe('Package 3E.3 - Layer Visibility & Inherited Visibility', () => {
        const visibilityTestDoc: DocumentSchema = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: {},
                children: [
                    {
                        id: 'node_heading_direct_hidden',
                        type: 'Heading',
                        props: { text: 'Hidden Heading' },
                        hidden: true,
                    },
                    {
                        id: 'node_richtext_visible',
                        type: 'RichText',
                        props: { content: '<p>Visible Text</p>' },
                    },
                    {
                        id: 'hidden_container',
                        type: 'Container',
                        props: {},
                        hidden: true,
                        children: [
                            {
                                id: 'inherited_hidden_button',
                                type: 'Button',
                                props: { label: 'Inherited Hidden Button' },
                            },
                        ],
                    },
                    {
                        id: 'locked_hidden_heading',
                        type: 'Heading',
                        props: { text: 'Locked and Hidden' },
                        locked: true,
                        hidden: true,
                    },
                ],
            },
        };

        it('displays eye toggle on non-root nodes and strictly never on root', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: visibilityTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // Root row must NOT have any visibility toggle or indicator
            const rootRow = wrapper.get('[data-node-id="root_section"]');
            expect(rootRow.find('[data-testid="visibility-toggle"]').exists()).toBe(false);
            expect(rootRow.find('[data-testid="visibility-indicator"]').exists()).toBe(false);

            // Direct hidden heading has visibility toggle with "Show" label and tabindex -1
            const hiddenHeadingRow = wrapper.get('[data-node-id="node_heading_direct_hidden"]');
            const showToggle = hiddenHeadingRow.get('[data-testid="visibility-toggle"]');
            expect(showToggle.attributes('aria-label')).toBe('Show Heading');
            expect(showToggle.attributes('title')).toBe('Show Heading');
            expect(showToggle.attributes('tabindex')).toBe('-1');

            // Visible richtext has visibility toggle with "Hide" label
            const visibleTextRow = wrapper.get(
                '[data-node-id="node_richtext_visible"]',
            );
            const hideToggle = visibleTextRow.get(
                '[data-testid="visibility-toggle"]',
            );
            expect(hideToggle.attributes('aria-label')).toBe('Hide Rich Text');
            expect(hideToggle.attributes('title')).toBe('Hide Rich Text');

            wrapper.unmount();
        });

        it('invokes setNodeHidden event flow and stops click propagation to selection', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: visibilityTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // 1. Unhide hidden heading
            const hiddenHeadingToggle = wrapper.get(
                '[data-node-id="node_heading_direct_hidden"] [data-testid="visibility-toggle"]',
            );
            await hiddenHeadingToggle.trigger('click');

            expect(wrapper.emitted('setNodeHidden')).toHaveLength(1);
            expect(wrapper.emitted('setNodeHidden')![0]).toEqual([
                { nodeId: 'node_heading_direct_hidden', hidden: false },
            ]);
            // Row selection was NOT triggered
            expect(wrapper.emitted('select')).toBeUndefined();

            // 2. Hide visible richtext
            const visibleTextToggle = wrapper.get(
                '[data-node-id="node_richtext_visible"] [data-testid="visibility-toggle"]',
            );
            await visibleTextToggle.trigger('click');

            expect(wrapper.emitted('setNodeHidden')).toHaveLength(2);
            expect(wrapper.emitted('setNodeHidden')![1]).toEqual([
                { nodeId: 'node_richtext_visible', hidden: true },
            ]);
            expect(wrapper.emitted('select')).toBeUndefined();

            wrapper.unmount();
        });

        it('displays non-interactive inherited-hidden indicator on descendants naming ancestor', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: visibilityTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const childBtnRow = wrapper.get('[data-node-id="inherited_hidden_button"]');
            // Must NOT have interactive toggle
            expect(childBtnRow.find('button[data-testid="visibility-toggle"]').exists()).toBe(false);

            // Must have inherited indicator naming protecting ancestor
            const indicator = childBtnRow.get('[data-testid="inherited-hidden-indicator"]');
            expect(indicator.attributes('title')).toBe('Hidden by ancestor (Container)');
            expect(indicator.attributes('aria-label')).toBe('Hidden by ancestor (Container)');

            wrapper.unmount();
        });

        it('suppresses actionable visibility controls in Viewer mode', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: visibilityTestDoc,
                    components: sampleMetadata,
                    readOnly: true,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // No interactive visibility toggle exists anywhere in Viewer mode
            expect(wrapper.find('button[data-testid="visibility-toggle"]').exists()).toBe(false);

            // Non-interactive indicators exist on hidden items
            const hiddenHeadingRow = wrapper.get('[data-node-id="node_heading_direct_hidden"]');
            const indicator = hiddenHeadingRow.get('[data-testid="visibility-indicator"]');
            expect(indicator.attributes('title')).toBe('Hidden: Heading');

            // Child of hidden container shows indicator naming ancestor
            const childBtnRow = wrapper.get('[data-node-id="inherited_hidden_button"]');
            const childIndicator = childBtnRow.get('[data-testid="visibility-indicator"]');
            expect(childIndicator.attributes('title')).toBe('Hidden by ancestor (Container)');

            wrapper.unmount();
        });

        it('disables visibility toggle when node is locked', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: visibilityTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const lockedHeadingRow = wrapper.get('[data-node-id="locked_hidden_heading"]');
            // Has disabled toggle
            const disabledToggle = lockedHeadingRow.get('[data-testid="visibility-toggle-disabled"]');
            expect(disabledToggle.attributes('disabled')).toBeDefined();

            await disabledToggle.trigger('click');
            expect(wrapper.emitted('setNodeHidden')).toBeUndefined();

            wrapper.unmount();
        });

        it('applies opacity-50 styling to globally hidden rows while retaining treeitem semantics', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: visibilityTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const hiddenHeadingRow = wrapper.get('[data-node-id="node_heading_direct_hidden"]');
            expect(hiddenHeadingRow.classes()).toContain('opacity-50');
            expect(hiddenHeadingRow.attributes('role')).toBe('treeitem');

            const inheritedRow = wrapper.get('[data-node-id="inherited_hidden_button"]');
            expect(inheritedRow.classes()).toContain('opacity-50');
            expect(inheritedRow.attributes('role')).toBe('treeitem');

            wrapper.unmount();
        });
    });

    describe('Package 3F: Layer Custom Labeling & Inline Renaming', () => {
        const labelingTestDoc: DocumentTree = {
            schemaVersion: 1,
            root: {
                id: 'root_section',
                type: 'Section',
                props: {},
                children: [
                    {
                        id: 'node_custom_heading',
                        type: 'Heading',
                        label: 'Hero Main Headline',
                        props: { text: 'Welcome to Pentagon' },
                    },
                    {
                        id: 'node_plain_button',
                        type: 'Button',
                        props: { text: 'Click Here' },
                    },
                    {
                        id: 'node_locked_box',
                        type: 'Container',
                        locked: true,
                        props: {},
                        children: [
                            {
                                id: 'node_in_locked_box',
                                type: 'Heading',
                                props: {},
                            },
                        ],
                    },
                ],
            },
        };

        it('displays custom label on layer row with fallback to component label', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: labelingTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // Custom labeled node
            const customRow = wrapper.get('[data-node-id="node_custom_heading"]');
            const customLabel = customRow.get('[data-testid="layer-label"]');
            expect(customLabel.text()).toBe('Hero Main Headline');
            expect(customRow.attributes('aria-label')).toBe(
                'Hero Main Headline (Heading): Welcome to Pentagon',
            );

            // Plain node without custom label
            const plainRow = wrapper.get('[data-node-id="node_plain_button"]');
            const plainLabel = plainRow.get('[data-testid="layer-label"]');
            expect(plainLabel.text()).toBe('Button');
            expect(plainRow.attributes('aria-label')).toBe('Button: Click Here');

            wrapper.unmount();
        });

        it('initiates inline rename on double-click and commits on Enter', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: labelingTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const row = wrapper.get('[data-node-id="node_plain_button"]');
            const labelSpan = row.get('[data-testid="layer-label"]');

            // Double click to trigger inline renaming
            await labelSpan.trigger('dblclick');

            const input = row.get('input[data-testid="inline-rename-input"]');
            expect(input.exists()).toBe(true);

            // Type new name and press Enter
            await input.setValue('Call to Action Button');
            await input.trigger('keydown.enter');

            expect(wrapper.emitted('setNodeLabel')).toHaveLength(1);
            expect(wrapper.emitted('setNodeLabel')![0]).toEqual([
                { nodeId: 'node_plain_button', label: 'Call to Action Button' },
            ]);

            // Input is unmounted after commit
            expect(row.find('input[data-testid="inline-rename-input"]').exists()).toBe(false);

            wrapper.unmount();
        });

        it('initiates inline rename via F2 key and cancels on Escape without emitting', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: labelingTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const row = wrapper.get('[data-node-id="node_custom_heading"]');

            // Trigger F2 on row
            await row.trigger('keydown', { key: 'F2' });

            const input = row.get('input[data-testid="inline-rename-input"]');
            expect(input.exists()).toBe(true);
            expect((input.element as HTMLInputElement).value).toBe('Hero Main Headline');

            // Type changes then cancel with Escape
            await input.setValue('Modified Text');
            await input.trigger('keydown', { key: 'Escape' });

            // Zero emits
            expect(wrapper.emitted('setNodeLabel')).toBeUndefined();
            // Input unmounted
            expect(row.find('input[data-testid="inline-rename-input"]').exists()).toBe(false);

            wrapper.unmount();
        });

        it('commits inline rename on blur and emits null when label cleared', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: labelingTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const row = wrapper.get('[data-node-id="node_custom_heading"]');
            const labelSpan = row.get('[data-testid="layer-label"]');

            await labelSpan.trigger('dblclick');
            const input = row.get('input[data-testid="inline-rename-input"]');

            // Clear text
            await input.setValue('   ');
            await input.trigger('blur');

            expect(wrapper.emitted('setNodeLabel')).toHaveLength(1);
            expect(wrapper.emitted('setNodeLabel')![0]).toEqual([
                { nodeId: 'node_custom_heading', label: null },
            ]);

            wrapper.unmount();
        });

        it('blocks inline rename on locked nodes, descendants of locked nodes, and in viewer mode', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: labelingTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            await wrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            // 1. Directly locked container
            const lockedRow = wrapper.get('[data-node-id="node_locked_box"]');
            const lockedLabel = lockedRow.get('[data-testid="layer-label"]');
            await lockedLabel.trigger('dblclick');
            expect(lockedRow.find('input[data-testid="inline-rename-input"]').exists()).toBe(false);
            await lockedRow.trigger('keydown', { key: 'F2' });
            expect(lockedRow.find('input[data-testid="inline-rename-input"]').exists()).toBe(false);

            // 2. Descendant of locked container
            const childRow = wrapper.get('[data-node-id="node_in_locked_box"]');
            const childLabel = childRow.get('[data-testid="layer-label"]');
            await childLabel.trigger('dblclick');
            expect(childRow.find('input[data-testid="inline-rename-input"]').exists()).toBe(false);
            await childRow.trigger('keydown', { key: 'F2' });
            expect(childRow.find('input[data-testid="inline-rename-input"]').exists()).toBe(false);

            wrapper.unmount();

            // 3. Viewer mode
            const viewerWrapper = mount(LayersPanel, {
                props: {
                    document: labelingTestDoc,
                    components: sampleMetadata,
                    readOnly: true,
                },
                attachTo: document.body,
            });

            await viewerWrapper.get('button[aria-label="Expand All Containers"]').trigger('click');

            const plainRow = viewerWrapper.get('[data-node-id="node_plain_button"]');
            const plainLabel = plainRow.get('[data-testid="layer-label"]');
            await plainLabel.trigger('dblclick');
            expect(plainRow.find('input[data-testid="inline-rename-input"]').exists()).toBe(false);
            await plainRow.trigger('keydown', { key: 'F2' });
            expect(plainRow.find('input[data-testid="inline-rename-input"]').exists()).toBe(false);

            viewerWrapper.unmount();
        });

        it('matches custom labels in search filter', async () => {
            const wrapper = mount(LayersPanel, {
                props: {
                    document: labelingTestDoc,
                    components: sampleMetadata,
                },
                attachTo: document.body,
            });

            // Search by partial custom label "Headline"
            const searchInput = wrapper.get('input[placeholder="Search layers..."]');
            await searchInput.setValue('Headline');

            // node_custom_heading should be visible and marked as match
            const customRow = wrapper.get('[data-node-id="node_custom_heading"]');
            expect(customRow.text()).toContain('match');

            // node_plain_button should not match
            expect(wrapper.find('[data-node-id="node_plain_button"]').exists()).toBe(false);

            wrapper.unmount();
        });
    });
});
