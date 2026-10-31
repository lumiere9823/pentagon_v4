import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { describe, expect, it } from 'vitest';
import InspectorNodeIdentity from '@/components/editor/inspector/InspectorNodeIdentity.vue';
import InspectorQuickActions from '@/components/editor/inspector/InspectorQuickActions.vue';

const node = {
    id: 'node_heading_1',
    type: 'Heading' as const,
    props: { text: 'Hello' },
    children: [],
};

describe('stable inspector groups', () => {
    it('renders selected node identity without owning document state', async () => {
        const html = await renderToString(
            createSSRApp({ render: () => h(InspectorNodeIdentity, { node }) }),
        );

        expect(html).toContain('node_heading_1');
        expect(html).toContain('Heading');
        expect(html).toContain('Children:');
    });

    it('only exposes destructive actions for editable non-root nodes', async () => {
        const editable = await renderToString(
            createSSRApp({
                render: () =>
                    h(InspectorQuickActions, {
                        node,
                        readOnly: false,
                        isRoot: false,
                    }),
            }),
        );
        const readOnly = await renderToString(
            createSSRApp({
                render: () =>
                    h(InspectorQuickActions, {
                        node,
                        readOnly: true,
                        isRoot: false,
                    }),
            }),
        );

        expect(editable).toContain('Duplicate');
        expect(editable).toContain('Delete');
        expect(readOnly).not.toContain('Duplicate');
        expect(readOnly).not.toContain('Delete');
    });
});
