import type {
    ComponentMetadata,
    InspectorControl,
    InspectorGroup,
    InspectorPropertyMetadata,
    PropSchemaDefinition,
} from '@/types/document';
import { isSafeUrl } from '@/lib/domSanitizer';
import { isPrimitiveSchemaCompatible } from '@/lib/stylePrimitives';

export type { PropSchemaDefinition } from '@/types/document';

const SUPPORTED_CONTROLS: ReadonlySet<InspectorControl> = new Set([
    'text',
    'textarea',
    'url',
    'media',
    'select',
    'toggle',
    'faq-list',
]);

function schemasAgree(
    field: InspectorPropertyMetadata,
    schema: PropSchemaDefinition,
): boolean {
    const fieldEnum = field.enum;
    const schemaEnum = schema.enum;
    const enumsAgree =
        fieldEnum === undefined && schemaEnum === undefined
            ? true
            : Array.isArray(fieldEnum) &&
              Array.isArray(schemaEnum) &&
              fieldEnum.length === schemaEnum.length &&
              fieldEnum.every((value, index) => value === schemaEnum[index]);
    const responsiveAgrees =
        typeof schema.responsive !== 'boolean' ||
        field.responsive === schema.responsive;

    return (
        field.type === schema.type &&
        enumsAgree &&
        field.stylePrimitive === schema.stylePrimitive &&
        responsiveAgrees
    );
}

export function getInspectorFields(
    metadata: ComponentMetadata | null | undefined,
    group: InspectorGroup,
): InspectorPropertyMetadata[] {
    if (!metadata || !Array.isArray(metadata.inspector)) return [];

    const seenPaths = new Set<string>();

    return metadata.inspector.filter((field) => {
        if (
            !field ||
            field.group !== group ||
            typeof field.key !== 'string' ||
            field.key === '' ||
            typeof field.label !== 'string' ||
            field.label === '' ||
            typeof field.responsive !== 'boolean' ||
            !SUPPORTED_CONTROLS.has(field.control)
        ) {
            return false;
        }

        const compatible =
            (field.control === 'select' &&
                Array.isArray(field.enum) &&
                field.enum.length > 0) ||
            (field.control === 'toggle' && field.type === 'boolean') ||
            (field.control === 'faq-list' && field.type === 'array') ||
            ((
                ['text', 'textarea', 'url', 'media'] as InspectorControl[]
            ).includes(field.control) &&
                field.type === 'string');
        if (
            !compatible ||
            !isPrimitiveSchemaCompatible(field) ||
            seenPaths.has(field.path)
        ) {
            return false;
        }

        if (field.path === 'styles.*.display') {
            const valid =
                field.key === 'display' &&
                field.group === 'style' &&
                field.control === 'select' &&
                field.responsive &&
                Array.isArray(field.enum);
            if (valid) seenPaths.add(field.path);
            return valid;
        }

        const propertySchema = metadata.props[field.key];
        const valid =
            field.path === `props.${field.key}` &&
            Object.prototype.hasOwnProperty.call(metadata.props, field.key) &&
            isPrimitiveSchemaCompatible(propertySchema) &&
            schemasAgree(field, propertySchema);
        if (valid) seenPaths.add(field.path);
        return valid;
    });
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

export function validateInspectorValue(
    field: InspectorPropertyMetadata,
    rawValue: unknown,
): { valid: true; value: unknown } | { valid: false } {
    const value = coercePropValue(field, rawValue);

    if (field.type === 'integer') {
        if (!Number.isInteger(value)) return { valid: false };
        const numericValue = value as number;
        if (field.min !== undefined && numericValue < field.min) {
            return { valid: false };
        }
        if (field.max !== undefined && numericValue > field.max) {
            return { valid: false };
        }
    } else if (field.type === 'boolean') {
        if (typeof rawValue !== 'boolean') return { valid: false };
    } else if (field.type === 'array') {
        if (!Array.isArray(value)) return { valid: false };
        if (field.maxItems !== undefined && value.length > field.maxItems) {
            return { valid: false };
        }
        if (field.itemSchema) {
            for (const item of value) {
                if (!item || typeof item !== 'object' || Array.isArray(item)) {
                    return { valid: false };
                }
                const record = item as Record<string, unknown>;
                for (const [key, rules] of Object.entries(field.itemSchema)) {
                    const itemValue = record[key];
                    if (rules.required && itemValue === undefined) {
                        return { valid: false };
                    }
                    if (
                        itemValue !== undefined &&
                        typeof itemValue !== 'string'
                    ) {
                        return { valid: false };
                    }
                    if (
                        typeof itemValue === 'string' &&
                        rules.maxLength !== undefined &&
                        itemValue.length > rules.maxLength
                    ) {
                        return { valid: false };
                    }
                }
            }
        }
    } else if (field.type === 'string') {
        if (typeof value !== 'string') return { valid: false };
        if (field.maxLength !== undefined && value.length > field.maxLength) {
            return { valid: false };
        }
        if (field.isUrl && !isSafeUrl(value)) {
            return { valid: false };
        }
    }

    if (field.enum && !field.enum.includes(value)) {
        return { valid: false };
    }

    return { valid: true, value };
}

export interface FieldValidationGuidance {
    isValid: boolean;
    error: string | null;
}

/**
 * Returns authoring validation guidance for a property field (R-17).
 * Identifies missing required props, invalid/unsafe URLs, and length bounds.
 */
export function getFieldValidationGuidance(
    field: InspectorPropertyMetadata,
    value: unknown,
): FieldValidationGuidance {
    const strVal =
        typeof value === 'string'
            ? value
            : typeof value === 'number' || typeof value === 'boolean'
              ? String(value)
              : '';
    const trimmed = strVal.trim();

    // 1. Check required property
    if (field.required && trimmed === '') {
        if (field.control === 'media' || field.key === 'src') {
            return {
                isValid: false,
                error: 'Image source is required before publishing.',
            };
        }
        return {
            isValid: false,
            error: `${field.label} is required.`,
        };
    }

    // 2. Check URL validity if non-empty
    if ((field.control === 'url' || field.isUrl) && trimmed !== '') {
        if (!isSafeUrl(trimmed)) {
            return {
                isValid: false,
                error: 'Please enter a valid URL (http, https, mailto, tel, or relative path).',
            };
        }
    }

    // 3. Check maxLength
    if (field.maxLength !== undefined && strVal.length > field.maxLength) {
        return {
            isValid: false,
            error: `${field.label} exceeds maximum length of ${field.maxLength} characters.`,
        };
    }

    return { isValid: true, error: null };
}

export interface FaqItemValidationGuidance {
    questionError: string | null;
    answerError: string | null;
}

/**
 * Returns authoring validation guidance for an individual FAQ item (R-17).
 */
export function getFaqItemValidationGuidance(
    item: { question?: string; answer?: string } | undefined | null,
    itemSchema?: Record<string, PropSchemaDefinition>,
): FaqItemValidationGuidance {
    if (!item) {
        return {
            questionError: 'Question is required.',
            answerError: 'Answer is required.',
        };
    }

    const q = (item.question ?? '').trim();
    const a = (item.answer ?? '').trim();
    const qMax = itemSchema?.question?.maxLength ?? 255;
    const aMax = itemSchema?.answer?.maxLength ?? 2000;

    let questionError: string | null = null;
    let answerError: string | null = null;

    if (q === '') {
        questionError = 'Question is required.';
    } else if (item.question && item.question.length > qMax) {
        questionError = `Question exceeds ${qMax} characters.`;
    }

    if (a === '') {
        answerError = 'Answer is required.';
    } else if (item.answer && item.answer.length > aMax) {
        answerError = `Answer exceeds ${aMax} characters.`;
    }

    return { questionError, answerError };
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
