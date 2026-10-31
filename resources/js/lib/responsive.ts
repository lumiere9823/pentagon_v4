import type {
    ComponentMetadata,
    CoreComponentType,
    DocumentNode,
    ViewportMode,
} from '@/types/document';
import {
    backgroundPresentation,
    blockSpacing,
    fontSize,
    isPrimitiveResponsive,
    maxWidth,
    textColor,
} from '@/lib/stylePrimitives';

/**
 * Authoritative map of responsive overridable presentation properties per component type.
 * Note: 'display' ('visible' | 'hidden') is permitted across all components.
 */
export const RESPONSIVE_OVERRIDABLE_PROPS: Record<
    CoreComponentType,
    readonly string[]
> = {
    Section: ['padding', 'background'],
    Container: ['maxWidth', 'centered'],
    Heading: ['align', 'blockSpacing', 'maxWidth', 'fontSize', 'textColor'],
    RichText: ['align', 'blockSpacing', 'maxWidth', 'fontSize', 'textColor'],
    Image: ['aspectRatio'],
    Button: ['size', 'variant'],
    Hero: ['align', 'background'],
    Columns: ['count', 'gap'],
    CardGrid: ['columns', 'gap'],
    FAQ: [],
    Spacer: ['size'],
    Divider: ['style', 'color'],
};

export const RESPONSIVE_BREAKPOINTS: Record<'tablet' | 'desktop', number> = {
    tablet: 768,
    desktop: 1024,
};

export interface EditorResponsivePresentation {
    root: Record<string, string | number>;
    content: Record<string, string | number>;
}

export function configureResponsiveContract(
    contract: {
        breakpoints: { tablet: number; desktop: number };
        overridableProps: Partial<Record<CoreComponentType, string[]>>;
    },
    components?: Partial<Record<CoreComponentType, ComponentMetadata>>,
): void {
    RESPONSIVE_BREAKPOINTS.tablet = contract.breakpoints.tablet;
    RESPONSIVE_BREAKPOINTS.desktop = contract.breakpoints.desktop;

    for (const type of Object.keys(
        RESPONSIVE_OVERRIDABLE_PROPS,
    ) as CoreComponentType[]) {
        const props = contract.overridableProps[type];
        if (Array.isArray(props)) {
            RESPONSIVE_OVERRIDABLE_PROPS[type] = props.filter((prop) => {
                if (!components) return true;
                const schema = components?.[type]?.props[prop];
                return schema !== undefined && isPrimitiveResponsive(schema);
            });
        }
    }
}

/**
 * Intrinsic root display mode for each of the 12 core components.
 */
export const COMPONENT_DISPLAY_MODES: Record<
    CoreComponentType,
    'block' | 'inline-block' | 'flex' | 'grid'
> = {
    Section: 'block',
    Container: 'block',
    Heading: 'block',
    RichText: 'block',
    Image: 'block',
    Button: 'inline-block',
    Hero: 'flex',
    Columns: 'grid',
    CardGrid: 'grid',
    FAQ: 'block',
    Spacer: 'flex',
    Divider: 'block',
};

/**
 * Static Tailwind CSS v4 class tokens ensuring classes are included in production builds.
 */
export const RESPONSIVE_DISPLAY_CLASSES = {
    block: {
        mobileHidden: 'hidden md:block',
        tabletHidden: 'block md:hidden lg:block',
        mobileAndTabletHidden: 'hidden lg:block',
        desktopHidden: 'block lg:hidden',
    },
    'inline-block': {
        mobileHidden: 'hidden md:inline-block',
        tabletHidden: 'inline-block md:hidden lg:inline-block',
        mobileAndTabletHidden: 'hidden lg:inline-block',
        desktopHidden: 'inline-block lg:hidden',
    },
    flex: {
        mobileHidden: 'hidden md:flex',
        tabletHidden: 'flex md:hidden lg:flex',
        mobileAndTabletHidden: 'hidden lg:flex',
        desktopHidden: 'flex lg:hidden',
    },
    grid: {
        mobileHidden: 'hidden md:grid',
        tabletHidden: 'grid md:hidden lg:grid',
        mobileAndTabletHidden: 'hidden lg:grid',
        desktopHidden: 'grid lg:hidden',
    },
} as const;

/**
 * Checks whether a property key can be overridden responsively for a given component type.
 */
export function isResponsiveProp(
    type: CoreComponentType,
    propKey: string,
): boolean {
    if (propKey === 'display') {
        return true;
    }
    const allowed = RESPONSIVE_OVERRIDABLE_PROPS[type];
    return allowed ? allowed.includes(propKey) : false;
}

/**
 * Resolves the effective property value based on the cascade:
 * Desktop (Base props) -> Tablet -> Mobile
 */
export function resolveResponsiveProp<T>(
    node: DocumentNode,
    propKey: string,
    currentViewport: ViewportMode,
): T {
    const baseValue = (node.props as Record<string, unknown>)[propKey] as T;

    if (currentViewport === 'desktop' || !node.styles) {
        return baseValue;
    }

    if (currentViewport === 'tablet') {
        const tabletOverride = node.styles.tablet?.[propKey] as T | undefined;
        return tabletOverride !== undefined ? tabletOverride : baseValue;
    }

    if (currentViewport === 'mobile') {
        const mobileOverride = node.styles.mobile?.[propKey] as T | undefined;
        if (mobileOverride !== undefined) {
            return mobileOverride;
        }

        const tabletOverride = node.styles.tablet?.[propKey] as T | undefined;
        if (tabletOverride !== undefined) {
            return tabletOverride;
        }

        return baseValue;
    }

    return baseValue;
}

/**
 * Checks if a property has an explicit override on the current viewport.
 */
export function isPropOverridden(
    node: DocumentNode,
    propKey: string,
    currentViewport: ViewportMode,
): boolean {
    if (currentViewport === 'desktop' || !node.styles) {
        return false;
    }

    if (currentViewport === 'tablet') {
        return node.styles.tablet?.[propKey] !== undefined;
    }

    if (currentViewport === 'mobile') {
        return node.styles.mobile?.[propKey] !== undefined;
    }

    return false;
}

/**
 * Gets the inherited value and its origin source for display in the Inspector.
 */
export function getInheritedPropInfo<T>(
    node: DocumentNode,
    propKey: string,
    currentViewport: ViewportMode,
): { value: T; source: 'desktop' | 'tablet' } {
    const baseValue = (node.props as Record<string, unknown>)[propKey] as T;

    if (currentViewport === 'mobile') {
        const tabletOverride = node.styles?.tablet?.[propKey] as T | undefined;
        if (tabletOverride !== undefined) {
            return { value: tabletOverride, source: 'tablet' };
        }
    }

    return { value: baseValue, source: 'desktop' };
}

/**
 * Computes all effective props for a node at the given viewport.
 */
export function resolveAllResponsiveProps(
    node: DocumentNode,
    currentViewport: ViewportMode,
): Record<string, unknown> {
    const resolved: Record<string, unknown> = { ...node.props };

    if (currentViewport === 'desktop' || !node.styles) {
        return resolved;
    }

    const overridableKeys = RESPONSIVE_OVERRIDABLE_PROPS[node.type] || [];
    const allKeys = [...overridableKeys, 'display'];

    for (const key of allKeys) {
        const val = resolveResponsiveProp(node, key, currentViewport);
        if (val !== undefined) {
            resolved[key] = val;
        }
    }

    return resolved;
}

function hasResponsiveOverride(node: DocumentNode, propKey: string): boolean {
    return (
        node.styles?.tablet?.[propKey] !== undefined ||
        node.styles?.mobile?.[propKey] !== undefined
    );
}

function usesExplicitColumnCount(
    node: DocumentNode,
    propKey: string,
    viewport: ViewportMode,
): boolean {
    if (viewport === 'desktop') {
        return true;
    }
    if (viewport === 'tablet') {
        return node.styles?.tablet?.[propKey] !== undefined;
    }
    return hasResponsiveOverride(node, propKey);
}

function compactViewport(viewport: ViewportMode): boolean {
    return viewport === 'mobile';
}

function sectionPadding(value: unknown, viewport: ViewportMode): string {
    const compact = compactViewport(viewport);
    switch (value) {
        case 'none':
            return '0';
        case 'small':
            return compact ? '1rem' : '1.5rem';
        case 'large':
            return compact ? '4rem' : '6rem';
        default:
            return compact ? '2rem' : '3rem';
    }
}

function columnsGap(value: unknown, viewport: ViewportMode): string {
    const compact = compactViewport(viewport);
    switch (value) {
        case 'small':
            return compact ? '0.75rem' : '1rem';
        case 'large':
            return compact ? '2rem' : '3rem';
        default:
            return compact ? '1.5rem' : '2rem';
    }
}

function cardGridGap(value: unknown, viewport: ViewportMode): string {
    const compact = compactViewport(viewport);
    switch (value) {
        case 'small':
            return compact ? '0.75rem' : '1rem';
        case 'large':
            return compact ? '2rem' : '2.5rem';
        default:
            return '1.5rem';
    }
}

function naturalColumnCount(
    type: 'Columns' | 'CardGrid',
    value: number,
    viewport: ViewportMode,
): number {
    if (viewport === 'mobile') {
        return 1;
    }
    if (type === 'CardGrid' && value === 4 && viewport === 'tablet') {
        return 2;
    }
    return value;
}

function headingFontSize(level: number, viewport: ViewportMode): string {
    const compact = viewport === 'mobile';
    const sizes: Record<number, [string, string]> = {
        1: ['1.875rem', '2.25rem'],
        2: ['1.5rem', '1.875rem'],
        3: ['1.25rem', '1.5rem'],
        4: ['1.125rem', '1.25rem'],
        5: ['1rem', '1.125rem'],
        6: ['0.875rem', '1rem'],
    };
    const [mobile, wider] = sizes[Math.max(1, Math.min(6, level))] ?? sizes[2];
    return compact ? mobile : wider;
}

/**
 * Inline presentation makes the simulated canvas independent from the browser window.
 * Only enum-validated document values are translated into CSS declarations.
 */
export function buildEditorResponsivePresentation(
    node: DocumentNode,
    viewport: ViewportMode,
): EditorResponsivePresentation {
    const values = resolveAllResponsiveProps(node, viewport);
    const root: Record<string, string | number> = {};
    const content: Record<string, string | number> = {};

    switch (node.type) {
        case 'Section': {
            root.paddingBlock = sectionPadding(values.padding, viewport);
            Object.assign(root, backgroundPresentation(values.background));
            break;
        }
        case 'Container':
            root.maxWidth =
                (
                    {
                        sm: '640px',
                        md: '768px',
                        lg: '1024px',
                        xl: '1280px',
                        full: '100%',
                    } as Record<string, string>
                )[String(values.maxWidth)] || '1024px';
            root.marginInline = values.centered === false ? '0' : 'auto';
            root.paddingInline = viewport === 'mobile' ? '1rem' : '1.5rem';
            break;
        case 'Heading':
            root.textAlign = ['center', 'right'].includes(String(values.align))
                ? String(values.align)
                : 'left';
            root.marginBlock = blockSpacing(values.blockSpacing);
            root.maxWidth = maxWidth(values.maxWidth);
            root.fontSize = fontSize(
                values.fontSize,
                headingFontSize(Number(values.level ?? 2), viewport),
            );
            root.color = textColor(values.textColor ?? 'foreground');
            break;
        case 'RichText':
            root.textAlign = ['center', 'right'].includes(String(values.align))
                ? String(values.align)
                : 'left';
            root.marginBlock = blockSpacing(values.blockSpacing);
            root.maxWidth = maxWidth(values.maxWidth);
            root.fontSize = fontSize(values.fontSize, '1rem');
            root.color = textColor(values.textColor ?? 'soft');
            break;
        case 'Image':
            content.aspectRatio =
                values.aspectRatio === '16:9'
                    ? '16 / 9'
                    : values.aspectRatio === '4:3'
                      ? '4 / 3'
                      : values.aspectRatio === '1:1'
                        ? '1'
                        : 'auto';
            content.height = 'auto';
            content.maxHeight =
                values.aspectRatio === 'auto' ? '500px' : 'none';
            content.objectFit = 'cover';
            break;
        case 'Button':
            if (values.size === 'sm') {
                Object.assign(content, {
                    height: '2rem',
                    paddingInline: '0.75rem',
                    fontSize: '0.75rem',
                });
            } else if (values.size === 'lg') {
                Object.assign(content, {
                    height: '2.75rem',
                    paddingInline: '1.5rem',
                    fontSize: '1rem',
                });
            } else {
                Object.assign(content, {
                    height: '2.25rem',
                    paddingInline: '1rem',
                    fontSize: '0.875rem',
                });
            }
            break;
        case 'Hero':
            root.textAlign = ['left', 'right'].includes(String(values.align))
                ? String(values.align)
                : 'center';
            root.alignItems =
                values.align === 'left'
                    ? 'flex-start'
                    : values.align === 'right'
                      ? 'flex-end'
                      : 'center';
            root.paddingInline = viewport === 'mobile' ? '1rem' : '1.5rem';
            root.paddingBlock = viewport === 'mobile' ? '3rem' : '5rem';
            Object.assign(root, backgroundPresentation(values.background));
            root['--pb-hero-title-size'] =
                viewport === 'mobile' ? '1.875rem' : '3rem';
            root['--pb-hero-subtitle-size'] =
                viewport === 'mobile' ? '1rem' : '1.125rem';
            break;
        case 'Columns':
        case 'CardGrid': {
            const prop = node.type === 'Columns' ? 'count' : 'columns';
            const rawCount = Number(
                values[prop] ?? (node.type === 'Columns' ? 2 : 3),
            );
            const count = usesExplicitColumnCount(node, prop, viewport)
                ? rawCount
                : naturalColumnCount(node.type, rawCount, viewport);
            root.gridTemplateColumns = `repeat(${count}, minmax(0, 1fr))`;
            root.gap =
                node.type === 'Columns'
                    ? columnsGap(values.gap, viewport)
                    : cardGridGap(values.gap, viewport);
            break;
        }
        case 'Spacer': {
            const compact = compactViewport(viewport);
            root.height =
                values.size === 'small'
                    ? compact
                        ? '1rem'
                        : '1.5rem'
                    : values.size === 'large'
                      ? compact
                          ? '4rem'
                          : '5rem'
                      : values.size === 'xlarge'
                        ? compact
                            ? '6rem'
                            : '8rem'
                        : compact
                          ? '2rem'
                          : '3rem';
            break;
        }
        case 'Divider':
            content.borderStyle = ['dashed', 'dotted'].includes(
                String(values.style),
            )
                ? String(values.style)
                : 'solid';
            content.borderColor =
                values.color === 'primary'
                    ? 'color-mix(in oklab, var(--primary) 40%, transparent)'
                    : values.color === 'subtle'
                      ? 'color-mix(in oklab, var(--border) 40%, transparent)'
                      : 'var(--border)';
            break;
    }

    return { root, content };
}
