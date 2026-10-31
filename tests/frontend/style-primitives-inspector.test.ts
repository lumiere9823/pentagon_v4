// @vitest-environment happy-dom

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import InspectorPanel from '@/components/editor/InspectorPanel.vue';
import type { ComponentMetadata, DocumentNode } from '@/types/document';

const headingMetadata: ComponentMetadata = {
    type: 'Heading',
    label: 'Heading',
    category: 'typography',
    allowChildren: false,
    canBeRoot: false,
    props: {
        text: { type: 'string', required: true },
        level: { type: 'integer', enum: [1, 2, 3, 4, 5, 6] },
        fontSize: {
            type: 'string',
            enum: ['semantic', 'small', 'medium', 'large', 'xlarge'],
            default: 'semantic',
            stylePrimitive: 'typography.fontSize',
        },
        textColor: {
            type: 'string',
            enum: ['foreground', 'soft', 'muted', 'primary'],
            default: 'foreground',
            stylePrimitive: 'color.foreground',
        },
    },
    inspector: [
        {
            key: 'fontSize',
            path: 'props.fontSize',
            label: 'Font Size',
            group: 'style',
            control: 'select',
            responsive: true,
            type: 'string',
            enum: ['semantic', 'small', 'medium', 'large', 'xlarge'],
            default: 'semantic',
            stylePrimitive: 'typography.fontSize',
        },
        {
            key: 'textColor',
            path: 'props.textColor',
            label: 'Text Color',
            group: 'style',
            control: 'select',
            responsive: true,
            type: 'string',
            enum: ['foreground', 'soft', 'muted', 'primary'],
            default: 'foreground',
            stylePrimitive: 'color.foreground',
        },
    ],
};

const heading: DocumentNode = {
    id: 'heading_inspector_primitive',
    type: 'Heading',
    props: {
        text: 'Heading',
        level: 2,
        fontSize: 'semantic',
        textColor: 'foreground',
    },
};

function mountInspector(viewport: 'desktop' | 'tablet' = 'desktop') {
    return mount(InspectorPanel, {
        props: {
            selectedNode: heading,
            componentMetadata: headingMetadata,
            currentViewport: viewport,
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
}

describe('style primitive Inspector controls', () => {
    it('renders from metadata and emits a bounded desktop mutation', async () => {
        const wrapper = mountInspector();
        const styleTab = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Style'));
        await styleTab?.trigger('click');

        expect(wrapper.text()).toContain('Font Size');
        expect(wrapper.text()).toContain('Text Color');
        await wrapper.get('#style-fontSize').setValue('large');

        expect(wrapper.emitted('updateProp')).toEqual([
            [{ key: 'fontSize', value: 'large' }],
        ]);
    });

    it('emits a sparse tablet override through the existing responsive route', async () => {
        const wrapper = mountInspector('tablet');
        const styleTab = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Style'));
        await styleTab?.trigger('click');
        await wrapper.get('#style-textColor').setValue('muted');

        expect(wrapper.emitted('updateStyle')).toEqual([
            [{ viewport: 'tablet', key: 'textColor', value: 'muted' }],
        ]);
    });
});
