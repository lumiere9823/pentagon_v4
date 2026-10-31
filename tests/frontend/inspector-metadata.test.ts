// @vitest-environment happy-dom

import { computed, defineComponent, nextTick, ref } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import InspectorPanel from '@/components/editor/InspectorPanel.vue';
import { useDocumentState } from '@/composables/useDocumentState';
import type {
    ComponentMetadata,
    DocumentSchema,
    ViewportMode,
} from '@/types/document';

const buttonMetadata: ComponentMetadata = {
    type: 'Button',
    label: 'Button',
    category: 'interactive',
    allowChildren: false,
    canBeRoot: false,
    props: {
        label: { type: 'string', required: true, maxLength: 100 },
        url: { type: 'string', required: true, isUrl: true },
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
            label: 'Button Label',
            group: 'content',
            control: 'text',
            responsive: false,
            type: 'string',
            required: true,
            maxLength: 100,
        },
        {
            key: 'url',
            path: 'props.url',
            label: 'Destination URL',
            group: 'content',
            control: 'url',
            responsive: false,
            type: 'string',
            required: true,
            isUrl: true,
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

const initialDocument: DocumentSchema = {
    schemaVersion: 1,
    root: {
        id: 'root_section',
        type: 'Section',
        props: { padding: 'medium', background: 'transparent' },
        children: [
            {
                id: 'node_button',
                type: 'Button',
                props: {
                    label: 'Original',
                    url: '#original',
                    variant: 'primary',
                    size: 'md',
                    target: '_self',
                },
            },
        ],
    },
};

const MutationHarness = defineComponent({
    components: { InspectorPanel },
    setup() {
        const state = useDocumentState(
            JSON.parse(JSON.stringify(initialDocument)) as DocumentSchema,
        );
        const viewport = ref<ViewportMode>('desktop');
        state.selectNode('node_button');

        return {
            document: state.document,
            selectedNode: computed(() => state.selectedNode.value),
            viewport,
            updateProp: (payload: { key: string; value: unknown }) =>
                state.updateNodeProps('node_button', {
                    [payload.key]: payload.value,
                }),
            updateStyle: (payload: {
                viewport: 'tablet' | 'mobile';
                key: string;
                value: unknown;
            }) =>
                state.updateNodeStyle(
                    'node_button',
                    payload.viewport,
                    payload.key,
                    payload.value,
                ),
            resetStyle: (payload: {
                viewport: 'tablet' | 'mobile';
                key: string;
            }) =>
                state.resetNodeStyle(
                    'node_button',
                    payload.viewport,
                    payload.key,
                ),
        };
    },
    template: `
        <div>
            <button type="button" @click="viewport = 'desktop'">Desktop viewport</button>
            <button type="button" @click="viewport = 'tablet'">Tablet viewport</button>
            <InspectorPanel
                :selected-node="selectedNode"
                :component-metadata="${'buttonMetadata'}"
                :current-viewport="viewport"
                @update-prop="updateProp"
                @update-style="updateStyle"
                @reset-style="resetStyle"
            />
        </div>
    `,
    data: () => ({ buttonMetadata }),
});

function mountHarness(): VueWrapper {
    return mount(MutationHarness, {
        global: {
            stubs: {
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

describe('metadata-driven Inspector mutations', () => {
    it('renders all groups from metadata and writes through document mutation methods', async () => {
        const wrapper = mountHarness();

        expect(wrapper.text()).toContain('Button Label');
        await wrapper.get('#prop-label').setValue('Metadata label');
        await buttonByText(wrapper, 'Style').trigger('click');
        await wrapper.get('#style-variant').setValue('secondary');
        await buttonByText(wrapper, 'Settings').trigger('click');
        await wrapper.get('#setting-target').setValue('_blank');

        const state = wrapper.vm as unknown as { document: DocumentSchema };
        const button = state.document.root.children?.[0];
        expect(button?.props).toMatchObject({
            label: 'Metadata label',
            variant: 'secondary',
            target: '_blank',
        });
    });

    it('rejects unsupported values and preserves responsive inheritance after reset', async () => {
        const wrapper = mountHarness();
        await buttonByText(wrapper, 'Style').trigger('click');

        await wrapper.get('#style-variant').setValue('unsupported');
        let state = wrapper.vm as unknown as { document: DocumentSchema };
        let button = state.document.root.children?.[0];
        expect(button?.props.variant).toBe('primary');

        await buttonByText(wrapper, 'Tablet viewport').trigger('click');
        await nextTick();
        await wrapper.get('#style-variant').setValue('secondary');

        state = wrapper.vm as unknown as { document: DocumentSchema };
        button = state.document.root.children?.[0];
        expect(button?.props.variant).toBe('primary');
        expect(button?.styles?.tablet?.variant).toBe('secondary');

        await buttonByText(wrapper, 'Reset').trigger('click');
        expect(button?.styles).toBeUndefined();
        expect(
            (wrapper.get('#style-variant').element as HTMLSelectElement).value,
        ).toBe('primary');
    });

    it('fails safely when metadata is missing, malformed, or points at unknown properties', () => {
        const invalidMetadata = {
            ...buttonMetadata,
            inspector: [
                {
                    ...buttonMetadata.inspector[0],
                    key: 'arbitrary',
                    path: 'props.arbitrary',
                },
                {
                    ...buttonMetadata.inspector[0],
                    control: 'unsupported',
                },
            ],
        } as unknown as ComponentMetadata;

        const wrapper = mount(InspectorPanel, {
            props: {
                selectedNode: initialDocument.root.children?.[0] ?? null,
                componentMetadata: invalidMetadata,
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

        expect(wrapper.text()).toContain('No content properties');
        expect(wrapper.find('input[id^="prop-"]').exists()).toBe(false);

        const unknown = mount(InspectorPanel, {
            props: {
                selectedNode: {
                    id: 'node_unknown',
                    type: 'UnknownComponent' as never,
                    props: {},
                },
                componentMetadata: null,
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
        expect(unknown.text()).toContain('No content properties');
    });
});
