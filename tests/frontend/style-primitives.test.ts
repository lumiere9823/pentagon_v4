// @vitest-environment happy-dom

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HeroRenderer from '@/components/editor/canvas/renderers/HeroRenderer.vue';
import { getInspectorFields } from '@/lib/inspectorHelpers';
import { buildEditorResponsivePresentation } from '@/lib/responsive';
import {
    STYLE_PRIMITIVES,
    backgroundPresentation,
    blockSpacing,
    fontSize,
    maxWidth,
    textColor,
} from '@/lib/stylePrimitives';
import type { ComponentMetadata, DocumentNode } from '@/types/document';

describe('shared style primitives', () => {
    it('maps every bounded value deterministically', () => {
        expect(Object.keys(STYLE_PRIMITIVES)).toContain('spacing.block');
        expect(['none', 'small', 'medium', 'large'].map(blockSpacing)).toEqual([
            '0',
            '0.5rem',
            '1rem',
            '1.5rem',
        ]);
        expect(['sm', 'md', 'lg', 'xl', 'full'].map(maxWidth)).toEqual([
            '640px',
            '768px',
            '1024px',
            '1280px',
            '100%',
        ]);
        expect(fontSize('semantic', '1.5rem')).toBe('1.5rem');
        expect(fontSize('large', '1.5rem')).toBe('1.25rem');
        expect(textColor('primary')).toBe('var(--primary)');
        expect(backgroundPresentation('dark')).toEqual({
            backgroundColor: 'var(--color-zinc-900, #18181b)',
            color: 'var(--color-zinc-100, #f4f4f5)',
        });
    });

    it('applies the same text primitives to Heading and RichText', () => {
        const sharedProps = {
            align: 'right',
            blockSpacing: 'large',
            maxWidth: 'md',
            fontSize: 'large',
            textColor: 'primary',
        };
        const heading: DocumentNode = {
            id: 'heading_primitive',
            type: 'Heading',
            props: { text: 'Heading', level: 2, ...sharedProps },
        };
        const richText: DocumentNode = {
            id: 'rich_primitive',
            type: 'RichText',
            props: { content: '<p>Text</p>', ...sharedProps },
        };

        expect(
            buildEditorResponsivePresentation(heading, 'desktop').root,
        ).toMatchObject({
            textAlign: 'right',
            marginBlock: '1.5rem',
            maxWidth: '768px',
            fontSize: '1.25rem',
            color: 'var(--primary)',
        });
        expect(
            buildEditorResponsivePresentation(richText, 'desktop').root,
        ).toMatchObject({
            textAlign: 'right',
            marginBlock: '1.5rem',
            maxWidth: '768px',
            fontSize: '1.25rem',
            color: 'var(--primary)',
        });
    });

    it('uses sparse responsive overrides and the selected editor viewport', () => {
        const heading: DocumentNode = {
            id: 'heading_responsive_primitive',
            type: 'Heading',
            props: {
                text: 'Heading',
                level: 2,
                align: 'left',
                blockSpacing: 'small',
                maxWidth: 'full',
                fontSize: 'semantic',
                textColor: 'foreground',
            },
            styles: {
                tablet: { fontSize: 'large', textColor: 'muted' },
                mobile: { blockSpacing: 'none' },
            },
        };

        expect(
            buildEditorResponsivePresentation(heading, 'desktop').root,
        ).toMatchObject({
            marginBlock: '0.5rem',
            fontSize: '1.875rem',
            color: 'var(--foreground)',
        });
        expect(
            buildEditorResponsivePresentation(heading, 'tablet').root,
        ).toMatchObject({
            marginBlock: '0.5rem',
            fontSize: '1.25rem',
            color: 'var(--muted-foreground)',
        });
        expect(
            buildEditorResponsivePresentation(heading, 'mobile').root,
        ).toMatchObject({
            marginBlock: '0',
            fontSize: '1.25rem',
            color: 'var(--muted-foreground)',
        });
    });

    it.each([
        ['transparent', 'text-foreground', 'text-muted-foreground'],
        ['muted', 'text-foreground', 'text-foreground'],
        ['primary', 'text-foreground', 'text-foreground'],
        ['dark', 'text-zinc-100', 'text-zinc-200'],
    ] as const)(
        'keeps Hero child foregrounds readable for the %s background',
        (background, titleClass, subtitleClass) => {
            const wrapper = mount(HeroRenderer, {
                props: {
                    title: 'Title',
                    subtitle: 'Subtitle',
                    background,
                },
            });

            expect(wrapper.get('h1').classes()).toContain(titleClass);
            expect(wrapper.get('p').classes()).toContain(subtitleClass);
        },
    );

    it('uses the responsive Hero background when selecting child foregrounds', () => {
        const hero: DocumentNode = {
            id: 'hero_responsive_foreground',
            type: 'Hero',
            props: {
                title: 'Title',
                subtitle: 'Subtitle',
                background: 'transparent',
            },
            styles: {
                tablet: { background: 'dark' },
                mobile: { background: 'primary' },
            },
        };

        for (const [viewport, expectedBackground, expectedSubtitleClass] of [
            ['desktop', 'transparent', 'text-muted-foreground'],
            ['tablet', 'dark', 'text-zinc-200'],
            ['mobile', 'primary', 'text-foreground'],
        ] as const) {
            const presentation = buildEditorResponsivePresentation(
                hero,
                viewport,
            );
            const wrapper = mount(HeroRenderer, {
                props: {
                    title: 'Title',
                    subtitle: 'Subtitle',
                    background: expectedBackground,
                    style: presentation.root,
                },
            });

            expect(wrapper.get('p').classes()).toContain(expectedSubtitleClass);
        }
    });

    it('fails closed for unknown or mismatched primitive metadata', () => {
        const metadata = {
            type: 'Heading',
            label: 'Heading',
            category: 'typography',
            allowChildren: false,
            canBeRoot: false,
            props: {
                fontSize: {
                    type: 'string',
                    enum: ['arbitrary'],
                    stylePrimitive: 'typography.fontSize',
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
                    enum: ['arbitrary'],
                    stylePrimitive: 'typography.fontSize',
                },
            ],
        } as unknown as ComponentMetadata;

        expect(getInspectorFields(metadata, 'style')).toEqual([]);
    });
});
