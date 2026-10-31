// @vitest-environment happy-dom

import { computed, defineComponent, h, nextTick, ref } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import InspectorPanel from '@/components/editor/InspectorPanel.vue';
import type {
    ComponentMetadata,
    DocumentNode,
    ViewportMode,
} from '@/types/document';
import type { MediaAssetItem } from '@/types/media';

const imageMetadata: ComponentMetadata = {
    type: 'Image',
    label: 'Image',
    category: 'media',
    allowChildren: false,
    canBeRoot: false,
    props: {
        src: { type: 'string', isUrl: true, default: '' },
        alt: { type: 'string', default: '' },
        caption: { type: 'string', default: '' },
        aspectRatio: {
            type: 'string',
            enum: ['auto', '16:9', '4:3', '1:1'],
            default: 'auto',
        },
    },
    inspector: [
        {
            key: 'src',
            path: 'props.src',
            label: 'Source',
            group: 'content',
            control: 'media',
            responsive: false,
            type: 'string',
            isUrl: true,
            default: '',
        },
        {
            key: 'alt',
            path: 'props.alt',
            label: 'Alt Text',
            group: 'content',
            control: 'text',
            responsive: false,
            type: 'string',
            default: '',
        },
        {
            key: 'caption',
            path: 'props.caption',
            label: 'Caption',
            group: 'content',
            control: 'text',
            responsive: false,
            type: 'string',
            default: '',
        },
        {
            key: 'aspectRatio',
            path: 'props.aspectRatio',
            label: 'Aspect Ratio',
            group: 'style',
            control: 'select',
            responsive: true,
            type: 'string',
            enum: ['auto', '16:9', '4:3', '1:1'],
            default: 'auto',
        },
        {
            key: 'display',
            path: 'styles.*.display',
            label: 'Component Visibility',
            group: 'style',
            control: 'select',
            responsive: true,
            type: 'string',
            enum: ['visible', 'hidden'],
            default: 'visible',
        },
    ],
};

const buttonMetadata: ComponentMetadata = {
    type: 'Button',
    label: 'Button',
    category: 'interactive',
    allowChildren: false,
    canBeRoot: false,
    props: {
        label: { type: 'string', default: '' },
        url: { type: 'string', isUrl: true, default: '#' },
        variant: {
            type: 'string',
            enum: ['primary', 'secondary'],
            default: 'primary',
        },
        target: {
            type: 'string',
            enum: ['_self', '_blank'],
            default: '_self',
        },
    },
    inspector: [
        {
            key: 'label',
            path: 'props.label',
            label: 'Label',
            group: 'content',
            control: 'text',
            responsive: false,
            type: 'string',
            default: '',
        },
        {
            key: 'url',
            path: 'props.url',
            label: 'URL',
            group: 'content',
            control: 'url',
            responsive: false,
            type: 'string',
            isUrl: true,
            default: '#',
        },
        {
            key: 'variant',
            path: 'props.variant',
            label: 'Variant',
            group: 'style',
            control: 'select',
            responsive: true,
            type: 'string',
            enum: ['primary', 'secondary'],
            default: 'primary',
        },
        {
            key: 'target',
            path: 'props.target',
            label: 'Target',
            group: 'settings',
            control: 'select',
            responsive: false,
            type: 'string',
            enum: ['_self', '_blank'],
            default: '_self',
        },
        {
            key: 'display',
            path: 'styles.*.display',
            label: 'Component Visibility',
            group: 'style',
            control: 'select',
            responsive: true,
            type: 'string',
            enum: ['visible', 'hidden'],
            default: 'visible',
        },
    ],
};

const selectedAsset: MediaAssetItem = {
    id: 42,
    original_filename: 'selected-image.webp',
    url: '/storage/media/selected-image.webp',
    mime_type: 'image/webp',
    size_bytes: 1200,
    formatted_size: '1.2 KB',
    width: 1200,
    height: 800,
    alt_text: 'Selected image description',
    created_at: '2026-10-24T00:00:00Z',
};

const MediaLibraryModalStub = defineComponent({
    name: 'MediaLibraryModal',
    props: {
        open: { type: Boolean, required: true },
    },
    emits: ['update:open', 'select'],
    setup(props, { emit }) {
        return () =>
            props.open
                ? h('div', { role: 'dialog', 'aria-label': 'Media Library' }, [
                      h(
                          'button',
                          {
                              type: 'button',
                              onClick: () => emit('select', selectedAsset),
                          },
                          'Choose selected image',
                      ),
                      h(
                          'button',
                          {
                              type: 'button',
                              onClick: () => emit('update:open', false),
                          },
                          'Cancel media selection',
                      ),
                  ])
                : null;
    },
});

const Harness = defineComponent({
    components: { InspectorPanel },
    setup() {
        const nodes = ref<Record<string, DocumentNode>>({
            image: {
                id: 'node_image_1',
                type: 'Image',
                props: {
                    src: '/storage/media/original.webp',
                    alt: 'Original alt',
                    caption: 'Original caption',
                    aspectRatio: 'auto',
                },
            },
            button: {
                id: 'node_button_1',
                type: 'Button',
                props: {
                    label: 'Original button',
                    url: '#original',
                    variant: 'primary',
                    target: '_self',
                },
            },
        });
        const selectedKey = ref<'image' | 'button'>('image');
        const viewport = ref<ViewportMode>('desktop');
        const mounted = ref(true);
        const selectedNode = computed(() => nodes.value[selectedKey.value]);
        const metadata = computed(() =>
            selectedKey.value === 'image' ? imageMetadata : buttonMetadata,
        );

        function updateProp(payload: { key: string; value: unknown }) {
            selectedNode.value.props[payload.key] = payload.value;
        }

        function updateStyle(payload: {
            viewport: 'tablet' | 'mobile';
            key: string;
            value: unknown;
        }) {
            selectedNode.value.styles ??= {};
            selectedNode.value.styles[payload.viewport] ??= {};
            selectedNode.value.styles[payload.viewport]![payload.key] =
                payload.value;
        }

        function resetStyle(payload: {
            viewport: 'tablet' | 'mobile';
            key: string;
        }) {
            delete selectedNode.value.styles?.[payload.viewport]?.[payload.key];
        }

        return {
            metadata,
            mounted,
            nodes,
            resetStyle,
            selectedKey,
            selectedNode,
            updateProp,
            updateStyle,
            viewport,
        };
    },
    template: `
        <div>
            <button type="button" @click="selectedKey = selectedKey === 'image' ? 'button' : 'image'">
                Change selected node
            </button>
            <button type="button" @click="viewport = 'desktop'">Desktop viewport</button>
            <button type="button" @click="viewport = 'tablet'">Tablet viewport</button>
            <button type="button" @click="viewport = 'mobile'">Mobile viewport</button>
            <button type="button" @click="mounted = !mounted">Toggle Inspector</button>
            <InspectorPanel
                v-if="mounted"
                :selected-node="selectedNode"
                :component-metadata="metadata"
                :current-viewport="viewport"
                :website-id="7"
                @update-prop="updateProp"
                @update-style="updateStyle"
                @reset-style="resetStyle"
            />
        </div>
    `,
});

function mountHarness(): VueWrapper {
    return mount(Harness, {
        global: {
            stubs: {
                MediaLibraryModal: MediaLibraryModalStub,
                Tooltip: { template: '<div><slot /></div>' },
                TooltipContent: { template: '<div><slot /></div>' },
                TooltipProvider: { template: '<div><slot /></div>' },
                TooltipTrigger: { template: '<div><slot /></div>' },
            },
        },
    });
}

function buttonByText(wrapper: VueWrapper, text: string) {
    const button = wrapper
        .findAll('button')
        .find((candidate) => candidate.text().includes(text));
    if (!button) throw new Error(`Button not found: ${text}`);
    return button;
}

describe('Inspector interactions', () => {
    it('preserves document edits and node identity across groups, media selection, and viewport changes', async () => {
        const wrapper = mountHarness();

        await buttonByText(wrapper, 'Settings').trigger('click');
        expect(wrapper.text()).toContain('node_image_1');
        await buttonByText(wrapper, 'Content').trigger('click');
        await wrapper.get('#prop-caption').setValue('Unsaved caption');
        await buttonByText(wrapper, 'Style').trigger('click');
        await buttonByText(wrapper, 'Tablet viewport').trigger('click');
        await wrapper.get('#style-aspectRatio').setValue('16:9');
        await buttonByText(wrapper, 'Content').trigger('click');

        expect(
            (wrapper.get('#prop-caption').element as HTMLInputElement).value,
        ).toBe('Unsaved caption');

        await buttonByText(wrapper, 'Choose from Library').trigger('click');
        expect(wrapper.get('[role="dialog"]').exists()).toBe(true);
        await buttonByText(wrapper, 'Choose selected image').trigger('click');

        const state = wrapper.vm as unknown as {
            nodes: Record<string, DocumentNode>;
        };
        expect(state.nodes.image.id).toBe('node_image_1');
        expect(state.nodes.image.props.caption).toBe('Unsaved caption');
        expect(state.nodes.image.props.src).toBe(selectedAsset.url);
        expect(state.nodes.image.props.alt).toBe('Original alt');
        expect(state.nodes.image.styles?.tablet?.aspectRatio).toBe('16:9');
    });

    it('keeps current values when groups reopen and isolates transient group state when the selected node changes', async () => {
        const wrapper = mountHarness();

        await wrapper.get('#prop-caption').setValue('Image draft');
        await buttonByText(wrapper, 'Choose from Library').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        await buttonByText(wrapper, 'Change selected node').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        await buttonByText(wrapper, 'Settings').trigger('click');
        expect(wrapper.text()).toContain('node_button_1');
        await wrapper.get('#setting-target').setValue('_blank');
        await buttonByText(wrapper, 'Content').trigger('click');
        expect(wrapper.find('#prop-label').exists()).toBe(true);
        await wrapper.get('#prop-label').setValue('Button draft');

        await buttonByText(wrapper, 'Change selected node').trigger('click');
        await buttonByText(wrapper, 'Settings').trigger('click');
        expect(wrapper.text()).toContain('node_image_1');
        await buttonByText(wrapper, 'Content').trigger('click');
        expect(
            (wrapper.get('#prop-caption').element as HTMLInputElement).value,
        ).toBe('Image draft');

        const state = wrapper.vm as unknown as {
            nodes: Record<string, DocumentNode>;
        };
        expect(state.nodes.button.props.target).toBe('_blank');
        expect(state.nodes.button.props.label).toBe('Button draft');
    });

    it('preserves parent-owned edits but resets transient UI after unmount and remount', async () => {
        const wrapper = mountHarness();

        await wrapper.get('#prop-caption').setValue('Survives remount');
        await buttonByText(wrapper, 'Settings').trigger('click');
        await buttonByText(wrapper, 'Content').trigger('click');
        await buttonByText(wrapper, 'Choose from Library').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);

        await buttonByText(wrapper, 'Toggle Inspector').trigger('click');
        expect(
            wrapper.find('[aria-label="Component Inspector"]').exists(),
        ).toBe(false);
        await buttonByText(wrapper, 'Toggle Inspector').trigger('click');
        await nextTick();

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.find('#prop-caption').exists()).toBe(true);
        expect(
            (wrapper.get('#prop-caption').element as HTMLInputElement).value,
        ).toBe('Survives remount');
        const state = wrapper.vm as unknown as {
            nodes: Record<string, DocumentNode>;
        };
        expect(state.nodes.image.id).toBe('node_image_1');
    });

    it('ignores a late media selection after the selected node changes', async () => {
        const wrapper = mountHarness();

        await buttonByText(wrapper, 'Choose from Library').trigger('click');
        const modal = wrapper.findComponent(MediaLibraryModalStub);
        expect(modal.exists()).toBe(true);

        await buttonByText(wrapper, 'Change selected node').trigger('click');
        modal.vm.$emit('select', selectedAsset);
        await nextTick();

        const state = wrapper.vm as unknown as {
            nodes: Record<string, DocumentNode>;
        };
        expect(state.nodes.image.props.src).toBe(
            '/storage/media/original.webp',
        );
        expect(state.nodes.button.props).not.toHaveProperty('src');
    });

    describe('Package 3E.2 - Inspector Read-Only Locking Safeguards', () => {
        const lockedImageNode: DocumentNode = {
            id: 'node_locked_img',
            type: 'Image',
            props: {
                src: 'https://example.com/locked.jpg',
                alt: 'Locked Alt',
                caption: 'Locked Caption',
            },
            locked: true,
        };

        const inheritedImageNode: DocumentNode = {
            id: 'node_inherited_img',
            type: 'Image',
            props: {
                src: 'https://example.com/inherited.jpg',
                alt: 'Inherited Alt',
                caption: 'Inherited Caption',
            },
        };

        it('displays accessible lock banner when inspecting directly locked node', () => {
            const wrapper = mount(InspectorPanel, {
                props: {
                    selectedNode: lockedImageNode,
                    componentMetadata: imageMetadata,
                    isLocked: true,
                    isAncestorLocked: false,
                    websiteId: 10,
                },
            });

            const banner = wrapper.get('[data-testid="inspector-lock-banner"]');
            expect(banner.attributes('role')).toBe('status');
            expect(banner.attributes('aria-live')).toBe('polite');
            expect(banner.text()).toContain('This component is locked. Unlock it in the Layers panel to make changes.');

            wrapper.unmount();
        });

        it('displays accessible lock banner identifying protecting ancestor for inherited lock', () => {
            const wrapper = mount(InspectorPanel, {
                props: {
                    selectedNode: inheritedImageNode,
                    componentMetadata: imageMetadata,
                    isLocked: false,
                    isAncestorLocked: true,
                    lockedAncestorLabel: 'Hero Section',
                    websiteId: 10,
                },
            });

            const banner = wrapper.get('[data-testid="inspector-lock-banner"]');
            expect(banner.text()).toContain('This component is locked by its ancestor (Hero Section).');
            expect(banner.text()).toContain('Unlock it in the Layers panel to make changes.');

            wrapper.unmount();
        });

        it('disables property inputs, textareas, and buttons for directly locked node', async () => {
            const wrapper = mount(InspectorPanel, {
                props: {
                    selectedNode: lockedImageNode,
                    componentMetadata: imageMetadata,
                    isLocked: true,
                    websiteId: 10,
                },
            });

            // Alt input is disabled
            const altInput = wrapper.get('#prop-alt');
            expect(altInput.attributes('disabled')).toBeDefined();

            // Caption input is disabled
            const captionInput = wrapper.get('#prop-caption');
            expect(captionInput.attributes('disabled')).toBeDefined();

            // Choose from library button is disabled
            const libraryBtn = wrapper.get('button:has(.lucide-image)');
            expect(libraryBtn.attributes('disabled')).toBeDefined();

            // Attempting input change emits nothing
            await altInput.setValue('New text');
            expect(wrapper.emitted('updateProp')).toBeUndefined();

            wrapper.unmount();
        });

        it('disables property and style controls for nodes protected by locked ancestor', async () => {
            const wrapper = mount(InspectorPanel, {
                props: {
                    selectedNode: inheritedImageNode,
                    componentMetadata: imageMetadata,
                    isLocked: false,
                    isAncestorLocked: true,
                    lockedAncestorLabel: 'Container',
                    currentViewport: 'tablet',
                    websiteId: 10,
                },
            });

            // Input is disabled
            const altInput = wrapper.get('#prop-alt');
            expect(altInput.attributes('disabled')).toBeDefined();

            // Switch to Style tab
            await buttonByText(wrapper, 'Style').trigger('click');

            // Selects and inputs are disabled
            const selects = wrapper.findAll('select');
            for (const select of selects) {
                expect(select.attributes('disabled')).toBeDefined();
            }

            wrapper.unmount();
        });

        it('omits quick duplicate and delete action paths for locked nodes', () => {
            const wrapper = mount(InspectorPanel, {
                props: {
                    selectedNode: lockedImageNode,
                    componentMetadata: imageMetadata,
                    isLocked: true,
                    isRoot: false,
                },
            });

            // Header quick duplicate and delete are omitted
            expect(wrapper.find('button[aria-label="Duplicate component"]').exists()).toBe(false);
            expect(wrapper.find('button[aria-label="Delete component"]').exists()).toBe(false);

            // Bottom quick actions container is omitted
            expect(wrapper.findComponent({ name: 'InspectorQuickActions' }).find('button').exists()).toBe(false);

            wrapper.unmount();
        });

        it('keeps normal editing fully functional for unlocked nodes', async () => {
            const unlockedNode: DocumentNode = {
                id: 'node_unlocked_img',
                type: 'Image',
                props: {
                    src: 'https://example.com/unlocked.jpg',
                    alt: 'Unlocked Alt',
                    caption: 'Unlocked Caption',
                },
            };

            const wrapper = mount(InspectorPanel, {
                props: {
                    selectedNode: unlockedNode,
                    componentMetadata: imageMetadata,
                    isLocked: false,
                    isAncestorLocked: false,
                    websiteId: 10,
                },
            });

            // Lock banner does not exist
            expect(wrapper.find('[data-testid="inspector-lock-banner"]').exists()).toBe(false);

            // Alt input is enabled
            const altInput = wrapper.get('#prop-alt');
            expect(altInput.attributes('disabled')).toBeUndefined();

            await altInput.setValue('Updated Alt Text');
            expect(wrapper.emitted('updateProp')).toBeDefined();
            expect(wrapper.emitted('updateProp')![0]).toEqual([
                { key: 'alt', value: 'Updated Alt Text' },
            ]);

            wrapper.unmount();
        });
    });
});
