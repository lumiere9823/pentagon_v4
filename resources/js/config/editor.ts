import type { ViewportMode, ViewportPreset } from '@/types/document';

export const VIEWPORT_PRESETS: Record<ViewportMode, ViewportPreset> = {
    desktop: {
        id: 'desktop',
        label: 'Desktop',
        width: 1280,
        widthCss: 'w-full max-w-[1280px]',
        description: 'Standard desktop view (1280px canvas)',
    },
    tablet: {
        id: 'tablet',
        label: 'Tablet',
        width: 768,
        widthCss: 'w-[768px]',
        description: 'Tablet portrait view (768px canvas)',
    },
    mobile: {
        id: 'mobile',
        label: 'Mobile',
        width: 375,
        widthCss: 'w-[375px]',
        description: 'Mobile smartphone view (375px canvas)',
    },
};

export const COMPONENT_CATEGORIES = [
    { id: 'all', label: 'All' },
    { id: 'layout', label: 'Layout' },
    { id: 'typography', label: 'Typography' },
    { id: 'media', label: 'Media' },
    { id: 'interactive', label: 'Interactive' },
    { id: 'content', label: 'Content' },
] as const;

export type ComponentCategoryId = (typeof COMPONENT_CATEGORIES)[number]['id'];
