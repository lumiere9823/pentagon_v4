import type {
    CoreComponentType,
    DocumentNode,
    ViewportMode,
} from '@/types/document';

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
    Heading: ['align'],
    RichText: [],
    Image: ['aspectRatio'],
    Button: ['size', 'variant'],
    Hero: ['align'],
    Columns: ['count', 'gap'],
    CardGrid: ['columns', 'gap'],
    FAQ: [],
    Spacer: ['size'],
    Divider: ['style', 'color'],
};

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
