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
