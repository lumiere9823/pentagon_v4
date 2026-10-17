import { describe, expect, it } from 'vitest';
import type { DocumentNode } from '@/types/document';
import {
    COMPONENT_DISPLAY_MODES,
    RESPONSIVE_DISPLAY_CLASSES,
    getInheritedPropInfo,
    isPropOverridden,
    isResponsiveProp,
    resolveAllResponsiveProps,
    resolveResponsiveProp,
} from '@/lib/responsive';

describe('responsive.ts resolver', () => {
    it('resolves base prop value on desktop', () => {
        const node: DocumentNode = {
            id: 'node_head_1',
            type: 'Heading',
            props: { text: 'Title', align: 'left', level: 2 },
            styles: {
                tablet: { align: 'center' },
                mobile: { align: 'right' },
            },
        };

        expect(resolveResponsiveProp(node, 'align', 'desktop')).toBe('left');
    });

    it('resolves tablet override on tablet, falling back to desktop when unset', () => {
        const node: DocumentNode = {
            id: 'node_head_1',
            type: 'Heading',
            props: { align: 'left' },
            styles: {
                tablet: { align: 'center' },
            },
        };

        expect(resolveResponsiveProp(node, 'align', 'tablet')).toBe('center');

        // Missing override on tablet falls back to desktop base
        const nodeNoOverride: DocumentNode = {
            id: 'node_head_2',
            type: 'Heading',
            props: { align: 'left' },
        };
        expect(resolveResponsiveProp(nodeNoOverride, 'align', 'tablet')).toBe('left');
    });

    it('implements full cascade on mobile: mobile -> tablet -> desktop', () => {
        // Case A: Mobile explicit override
        const nodeA: DocumentNode = {
            id: 'node_col_1',
            type: 'Columns',
            props: { count: 4, gap: 'large' },
            styles: {
                tablet: { count: 3 },
                mobile: { count: 2 },
            },
        };
        expect(resolveResponsiveProp(nodeA, 'count', 'mobile')).toBe(2);

        // Case B: Mobile inherits from Tablet
        const nodeB: DocumentNode = {
            id: 'node_col_2',
            type: 'Columns',
            props: { count: 4, gap: 'large' },
            styles: {
                tablet: { count: 3 },
            },
        };
        expect(resolveResponsiveProp(nodeB, 'count', 'mobile')).toBe(3);

        // Case C: Mobile inherits from Desktop (when neither mobile nor tablet has override)
        const nodeC: DocumentNode = {
            id: 'node_col_3',
            type: 'Columns',
            props: { count: 4, gap: 'large' },
        };
        expect(resolveResponsiveProp(nodeC, 'count', 'mobile')).toBe(4);
    });

    it('correctly tracks isPropOverridden across viewports', () => {
        const node: DocumentNode = {
            id: 'node_btn_1',
            type: 'Button',
            props: { variant: 'primary', size: 'md' },
            styles: {
                tablet: { size: 'lg' },
            },
        };

        expect(isPropOverridden(node, 'size', 'desktop')).toBe(false);
        expect(isPropOverridden(node, 'size', 'tablet')).toBe(true);
        expect(isPropOverridden(node, 'size', 'mobile')).toBe(false);
        expect(isPropOverridden(node, 'variant', 'tablet')).toBe(false);
    });

    it('identifies inherited prop info source', () => {
        const node: DocumentNode = {
            id: 'node_sec_1',
            type: 'Section',
            props: { padding: 'medium' },
            styles: {
                tablet: { padding: 'large' },
            },
        };

        // On mobile with no mobile override: inherits from tablet
        const infoMobile = getInheritedPropInfo(node, 'padding', 'mobile');
        expect(infoMobile.value).toBe('large');
        expect(infoMobile.source).toBe('tablet');

        // On tablet: inherits from desktop
        const infoTablet = getInheritedPropInfo(node, 'padding', 'tablet');
        expect(infoTablet.value).toBe('medium');
        expect(infoTablet.source).toBe('desktop');
    });

    it('resolves display property override correctly', () => {
        const node: DocumentNode = {
            id: 'node_img_1',
            type: 'Image',
            props: { src: 'pic.jpg' },
            styles: {
                mobile: { display: 'hidden' },
            },
        };

        expect(resolveResponsiveProp(node, 'display', 'desktop')).toBeUndefined();
        expect(resolveResponsiveProp(node, 'display', 'tablet')).toBeUndefined();
        expect(resolveResponsiveProp(node, 'display', 'mobile')).toBe('hidden');
    });

    it('verifies all 12 components have valid intrinsic display modes', () => {
        expect(COMPONENT_DISPLAY_MODES.Section).toBe('block');
        expect(COMPONENT_DISPLAY_MODES.Container).toBe('block');
        expect(COMPONENT_DISPLAY_MODES.Heading).toBe('block');
        expect(COMPONENT_DISPLAY_MODES.RichText).toBe('block');
        expect(COMPONENT_DISPLAY_MODES.Image).toBe('block');
        expect(COMPONENT_DISPLAY_MODES.Button).toBe('inline-block');
        expect(COMPONENT_DISPLAY_MODES.Hero).toBe('flex');
        expect(COMPONENT_DISPLAY_MODES.Columns).toBe('grid');
        expect(COMPONENT_DISPLAY_MODES.CardGrid).toBe('grid');
        expect(COMPONENT_DISPLAY_MODES.FAQ).toBe('block');
        expect(COMPONENT_DISPLAY_MODES.Spacer).toBe('flex');
        expect(COMPONENT_DISPLAY_MODES.Divider).toBe('block');
    });

    it('verifies static responsive display class tokens exist for build scanning', () => {
        expect(RESPONSIVE_DISPLAY_CLASSES.grid.mobileHidden).toBe('hidden md:grid');
        expect(RESPONSIVE_DISPLAY_CLASSES.flex.tabletHidden).toBe('flex md:hidden lg:flex');
        expect(RESPONSIVE_DISPLAY_CLASSES['inline-block'].mobileHidden).toBe('hidden md:inline-block');
        expect(RESPONSIVE_DISPLAY_CLASSES.block.mobileAndTabletHidden).toBe('hidden lg:block');
    });

    it('checks isResponsiveProp against canonical allowlist', () => {
        expect(isResponsiveProp('Heading', 'align')).toBe(true);
        expect(isResponsiveProp('Heading', 'text')).toBe(false);
        expect(isResponsiveProp('Heading', 'level')).toBe(false);
        expect(isResponsiveProp('Heading', 'display')).toBe(true);
        expect(isResponsiveProp('Columns', 'count')).toBe(true);
        expect(isResponsiveProp('Columns', 'gap')).toBe(true);
        expect(isResponsiveProp('RichText', 'content')).toBe(false);
        expect(isResponsiveProp('RichText', 'display')).toBe(true);
    });

    it('merges all resolved responsive props', () => {
        const node: DocumentNode = {
            id: 'node_cardgrid_1',
            type: 'CardGrid',
            props: { columns: 4, gap: 'medium' },
            styles: {
                tablet: { columns: 3 },
                mobile: { columns: 2, gap: 'small', display: 'visible' },
            },
        };

        const mobileProps = resolveAllResponsiveProps(node, 'mobile');
        expect(mobileProps.columns).toBe(2);
        expect(mobileProps.gap).toBe('small');
        expect(mobileProps.display).toBe('visible');

        const tabletProps = resolveAllResponsiveProps(node, 'tablet');
        expect(tabletProps.columns).toBe(3);
        expect(tabletProps.gap).toBe('medium');

        const desktopProps = resolveAllResponsiveProps(node, 'desktop');
        expect(desktopProps.columns).toBe(4);
        expect(desktopProps.gap).toBe('medium');
    });

    it('safely handles missing styles and unconfigured viewports', () => {
        const nodeWithoutStyles: DocumentNode = {
            id: 'node_basic',
            type: 'Heading',
            props: { text: 'Hello', align: 'center' },
        };

        expect(resolveResponsiveProp(nodeWithoutStyles, 'align', 'tablet')).toBe('center');
        expect(resolveResponsiveProp(nodeWithoutStyles, 'align', 'mobile')).toBe('center');
        expect(isPropOverridden(nodeWithoutStyles, 'align', 'mobile')).toBe(false);
        expect(getInheritedPropInfo(nodeWithoutStyles, 'align', 'mobile')).toEqual({
            value: 'center',
            source: 'desktop',
        });
        expect(resolveAllResponsiveProps(nodeWithoutStyles, 'mobile')).toEqual({
            text: 'Hello',
            align: 'center',
        });
    });

    it('ensures each component maps to a supported intrinsic layout mode', () => {
        const supportedModes = new Set(['block', 'inline-block', 'flex', 'grid']);
        for (const [component, mode] of Object.entries(COMPONENT_DISPLAY_MODES)) {
            expect(supportedModes.has(mode)).toBe(true);
            expect(RESPONSIVE_DISPLAY_CLASSES[mode as keyof typeof RESPONSIVE_DISPLAY_CLASSES]).toBeDefined();
        }
    });
});
