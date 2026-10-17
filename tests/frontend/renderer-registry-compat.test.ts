import { describe, expect, it } from 'vitest';
import {
    CONTAINER_COMPONENT_TYPES,
    DEFAULT_COMPONENT_PROPS,
    DOCUMENT_LIMITS,
    type CoreComponentType,
} from '@/types/document';

describe('Renderer & Registry Compatibility (F-07)', () => {
    const allTwelveComponents: CoreComponentType[] = [
        'Section',
        'Container',
        'Heading',
        'RichText',
        'Image',
        'Button',
        'Hero',
        'Columns',
        'CardGrid',
        'FAQ',
        'Spacer',
        'Divider',
    ];

    it('contains all 12 core components in DEFAULT_COMPONENT_PROPS', () => {
        expect(allTwelveComponents.length).toBe(12);
        for (const type of allTwelveComponents) {
            expect(DEFAULT_COMPONENT_PROPS[type]).toBeDefined();
            expect(typeof DEFAULT_COMPONENT_PROPS[type]).toBe('object');
        }
    });

    it('correctly partitions container components and leaf components', () => {
        const expectedContainers: CoreComponentType[] = [
            'Section',
            'Container',
            'Hero',
            'Columns',
            'CardGrid',
        ];
        const expectedLeafs: CoreComponentType[] = [
            'Heading',
            'RichText',
            'Image',
            'Button',
            'FAQ',
            'Spacer',
            'Divider',
        ];

        for (const c of expectedContainers) {
            expect(CONTAINER_COMPONENT_TYPES.has(c)).toBe(true);
        }

        for (const l of expectedLeafs) {
            expect(CONTAINER_COMPONENT_TYPES.has(l)).toBe(false);
        }
    });

    it('verifies Columns and CardGrid default column counts are integers matching registry', () => {
        const columnsProps = DEFAULT_COMPONENT_PROPS.Columns;
        expect(typeof columnsProps.count).toBe('number');
        expect(Number.isInteger(columnsProps.count)).toBe(true);
        expect([2, 3, 4]).toContain(columnsProps.count);

        const cardGridProps = DEFAULT_COMPONENT_PROPS.CardGrid;
        expect(typeof cardGridProps.columns).toBe('number');
        expect(Number.isInteger(cardGridProps.columns)).toBe(true);
        expect([2, 3, 4]).toContain(cardGridProps.columns);
    });

    it('verifies Heading default level is an integer between 1 and 6', () => {
        const headingProps = DEFAULT_COMPONENT_PROPS.Heading;
        expect(typeof headingProps.level).toBe('number');
        expect(Number.isInteger(headingProps.level)).toBe(true);
        expect(headingProps.level).toBeGreaterThanOrEqual(1);
        expect(headingProps.level).toBeLessThanOrEqual(6);
    });

    it('verifies Image default props contain src, alt, and valid aspectRatio', () => {
        const imageProps = DEFAULT_COMPONENT_PROPS.Image;
        expect(typeof imageProps.src).toBe('string');
        expect(typeof imageProps.alt).toBe('string');
        expect(['auto', '16:9', '4:3', '1:1']).toContain(imageProps.aspectRatio);
    });

    it('verifies Button default props contain valid variant and size', () => {
        const buttonProps = DEFAULT_COMPONENT_PROPS.Button;
        expect(['primary', 'secondary', 'outline', 'ghost']).toContain(buttonProps.variant);
        expect(['sm', 'md', 'lg']).toContain(buttonProps.size);
        expect(['_self', '_blank']).toContain(buttonProps.target);
    });

    it('verifies FAQ default props contain valid array items repeater', () => {
        const faqProps = DEFAULT_COMPONENT_PROPS.FAQ;
        expect(Array.isArray(faqProps.items)).toBe(true);
        const firstItem = (faqProps.items as any[])[0];
        expect(firstItem).toBeDefined();
        expect(typeof firstItem.question).toBe('string');
        expect(typeof firstItem.answer).toBe('string');
    });

    it('verifies resource limits match canonical backend DocumentLimits', () => {
        expect(DOCUMENT_LIMITS.MAX_PAYLOAD_BYTES).toBe(524_288);
        expect(DOCUMENT_LIMITS.MAX_TREE_DEPTH).toBe(10);
        expect(DOCUMENT_LIMITS.MAX_TOTAL_NODES).toBe(250);
        expect(DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE).toBe(50);
        expect(DOCUMENT_LIMITS.MAX_RICH_TEXT_LENGTH).toBe(65_535);
        expect(DOCUMENT_LIMITS.NODE_ID_REGEX.test('node_hero_abc123xyz')).toBe(true);
    });
});
