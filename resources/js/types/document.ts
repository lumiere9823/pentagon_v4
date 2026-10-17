/**
 * Document Engine & Component Registry Schema v1 Types
 */

export type CoreComponentType =
    | 'Section'
    | 'Container'
    | 'Heading'
    | 'RichText'
    | 'Image'
    | 'Button'
    | 'Hero'
    | 'Columns'
    | 'CardGrid'
    | 'FAQ'
    | 'Spacer'
    | 'Divider';

export interface DocumentNode<TProps = Record<string, unknown>> {
    id: string;
    type: CoreComponentType;
    props: TProps;
    children?: DocumentNode[];
}

export interface PageSettings {
    title?: string | null;
    description?: string | null;
}

export interface DocumentSchema {
    schemaVersion: 1;
    root: DocumentNode;
    pageSettings?: PageSettings;
}

export interface ComponentMetadata {
    type: CoreComponentType;
    label: string;
    category: 'layout' | 'typography' | 'media' | 'interactive' | 'content';
    allowChildren: boolean;
    canBeRoot: boolean;
    props: Record<string, unknown>;
}

export interface ValidationErrorResponse {
    path: string;
    code: string;
    message: string;
}

export interface ComponentRegistryResponse {
    schemaVersion: number;
    components: Record<string, ComponentMetadata>;
    limits: {
        maxPayloadBytes: number;
        maxTreeDepth: number;
        maxTotalNodes: number;
        maxChildrenPerNode: number;
    };
}

export type ViewportMode = 'desktop' | 'tablet' | 'mobile';

export interface ViewportPreset {
    id: ViewportMode;
    label: string;
    width: number;
    widthCss: string;
    description: string;
}

export type InsertPosition = 'inside' | 'before' | 'after';

export interface TreeMutationResult {
    success: boolean;
    error?: string;
}

export const DOCUMENT_LIMITS = {
    MAX_PAYLOAD_BYTES: 524_288,
    MAX_TREE_DEPTH: 10,
    MAX_TOTAL_NODES: 250,
    MAX_CHILDREN_PER_NODE: 50,
    MAX_RICH_TEXT_LENGTH: 65_535,
    MAX_SHORT_STRING_LENGTH: 500,
    MAX_URL_LENGTH: 2_048,
    NODE_ID_REGEX: /^[a-zA-Z0-9_-]{3,64}$/,
} as const;

export const CONTAINER_COMPONENT_TYPES: Set<CoreComponentType> = new Set([
    'Section',
    'Container',
    'Hero',
    'Columns',
    'CardGrid',
]);

export const DEFAULT_COMPONENT_PROPS: Record<
    CoreComponentType,
    Record<string, unknown>
> = {
    Section: {
        padding: 'medium',
        background: 'transparent',
    },
    Container: {
        maxWidth: 'lg',
        centered: true,
    },
    Heading: {
        text: 'New Heading',
        level: 2,
        align: 'left',
    },
    RichText: {
        content: '<p>Start typing rich text here...</p>',
    },
    Image: {
        src: 'https://images.unsplash.com/photo-1579546929518-9e396f3cc809',
        alt: 'Sample image',
        caption: '',
        aspectRatio: 'auto',
    },
    Button: {
        label: 'Click Here',
        url: '#',
        variant: 'primary',
        size: 'md',
        target: '_self',
    },
    Hero: {
        title: 'Hero Headline',
        subtitle: 'A compelling subheadline introducing your content.',
        align: 'center',
        ctaText: 'Learn More',
        ctaUrl: '#',
    },
    Columns: {
        count: 2,
        gap: 'medium',
    },
    CardGrid: {
        columns: 3,
        gap: 'medium',
    },
    FAQ: {
        items: [
            {
                question: 'What is this page builder?',
                answer: '<p>A high-performance visual page builder for SaaS applications.</p>',
            },
        ],
    },
    Spacer: {
        size: 'medium',
    },
    Divider: {
        style: 'solid',
        color: 'muted',
    },
};
