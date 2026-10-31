import primitiveContract from '../../page-builder/style-primitives.json';

export type StylePrimitiveId = keyof typeof primitiveContract;

interface StylePrimitiveDefinition {
    category: 'spacing' | 'dimensions' | 'typography' | 'color';
    type: 'string';
    values: string[];
    responsive: boolean;
}

export const STYLE_PRIMITIVES = primitiveContract as Record<
    StylePrimitiveId,
    StylePrimitiveDefinition
>;

export function isStylePrimitiveId(value: unknown): value is StylePrimitiveId {
    return (
        typeof value === 'string' &&
        Object.prototype.hasOwnProperty.call(STYLE_PRIMITIVES, value)
    );
}

export function matchesStylePrimitiveContract(value: unknown): boolean {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return false;
    }

    const received = value as Record<string, unknown>;
    const expectedIds = Object.keys(STYLE_PRIMITIVES);
    if (Object.keys(received).length !== expectedIds.length) return false;

    return expectedIds.every((id) => {
        const expected = STYLE_PRIMITIVES[id as StylePrimitiveId];
        const candidate = received[id];
        if (!candidate || typeof candidate !== 'object') return false;
        const definition = candidate as Record<string, unknown>;

        return (
            definition.category === expected.category &&
            definition.type === expected.type &&
            definition.responsive === expected.responsive &&
            Array.isArray(definition.values) &&
            definition.values.length === expected.values.length &&
            definition.values.every(
                (item, index) => item === expected.values[index],
            )
        );
    });
}

export function isPrimitiveSchemaCompatible(schema: {
    type?: string;
    enum?: unknown[];
    stylePrimitive?: unknown;
}): boolean {
    if (schema.stylePrimitive === undefined) return true;
    if (!isStylePrimitiveId(schema.stylePrimitive)) return false;

    const primitive = STYLE_PRIMITIVES[schema.stylePrimitive];
    return (
        schema.type === primitive.type &&
        Array.isArray(schema.enum) &&
        schema.enum.length === primitive.values.length &&
        schema.enum.every((value, index) => value === primitive.values[index])
    );
}

export function isPrimitiveResponsive(schema: {
    type?: string;
    enum?: unknown[];
    stylePrimitive?: unknown;
}): boolean {
    if (schema.stylePrimitive === undefined) return true;
    if (!isPrimitiveSchemaCompatible(schema)) return false;

    return STYLE_PRIMITIVES[schema.stylePrimitive as StylePrimitiveId]
        .responsive;
}

export function blockSpacing(value: unknown): string {
    return value === 'none'
        ? '0'
        : value === 'medium'
          ? '1rem'
          : value === 'large'
            ? '1.5rem'
            : '0.5rem';
}

export function maxWidth(value: unknown): string {
    return (
        (
            {
                sm: '640px',
                md: '768px',
                lg: '1024px',
                xl: '1280px',
                full: '100%',
            } as Record<string, string>
        )[String(value)] ?? '100%'
    );
}

export function fontSize(value: unknown, semanticValue: string): string {
    return (
        (
            {
                small: '0.875rem',
                medium: '1rem',
                large: '1.25rem',
                xlarge: '1.875rem',
            } as Record<string, string>
        )[String(value)] ?? semanticValue
    );
}

export function textColor(value: unknown): string {
    return value === 'soft'
        ? 'color-mix(in oklab, var(--foreground) 90%, transparent)'
        : value === 'muted'
          ? 'var(--muted-foreground)'
          : value === 'primary'
            ? 'var(--primary)'
            : 'var(--foreground)';
}

export function backgroundPresentation(value: unknown): {
    backgroundColor: string;
    color: string;
} {
    if (value === 'muted') {
        return {
            backgroundColor:
                'color-mix(in oklab, var(--muted) 50%, transparent)',
            color: 'var(--foreground)',
        };
    }
    if (value === 'primary') {
        return {
            backgroundColor:
                'color-mix(in oklab, var(--primary) 10%, transparent)',
            color: 'var(--foreground)',
        };
    }
    if (value === 'dark') {
        return {
            backgroundColor: 'var(--color-zinc-900, #18181b)',
            color: 'var(--color-zinc-100, #f4f4f5)',
        };
    }

    return {
        backgroundColor: 'transparent',
        color: 'var(--foreground)',
    };
}
