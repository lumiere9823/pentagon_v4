/**
 * Inspector & Property Coercion Helpers
 */

export interface PropSchemaDefinition {
    type?: string;
    required?: boolean;
    default?: unknown;
    enum?: unknown[];
    maxLength?: number;
    isUrl?: boolean;
    isHtml?: boolean;
    min?: number;
    max?: number;
}

/**
 * Coerces raw form input values to their schema-declared types.
 * Specifically ensures integer properties (e.g. Columns.count, CardGrid.columns, Heading.level)
 * are stored as numbers rather than strings emitted by HTML <select> elements (F-01).
 */
export function coercePropValue(
    schema: PropSchemaDefinition | undefined,
    rawValue: unknown,
): unknown {
    if (!schema) {
        return rawValue;
    }

    if (schema.type === 'integer') {
        const parsed = parseInt(String(rawValue), 10);
        return Number.isNaN(parsed) ? rawValue : parsed;
    }

    if (schema.type === 'boolean') {
        return Boolean(rawValue);
    }

    return rawValue;
}

/**
 * Determines whether a node action (duplicate, delete, move) is permitted.
 * Guarantees that the document root cannot be deleted or duplicated (F-04),
 * and viewers cannot mutate node structures.
 */
export function canPerformNodeAction(
    action: 'delete' | 'duplicate' | 'move',
    isRoot: boolean,
    readOnly: boolean,
): boolean {
    if (readOnly) {
        return false;
    }

    if (isRoot) {
        return false;
    }

    return true;
}
