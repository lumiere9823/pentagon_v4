import { describe, expect, it } from 'vitest';
import {
    canPerformNodeAction,
    coercePropValue,
    type PropSchemaDefinition,
} from '@/lib/inspectorHelpers';

describe('coercePropValue (F-01)', () => {
    it('coerces string values from HTML select elements to integer when schema type is integer', () => {
        const integerSchema: PropSchemaDefinition = {
            type: 'integer',
            enum: [2, 3, 4],
            default: 2,
        };

        // Columns.count or CardGrid.columns emitted from <select> as string "3"
        expect(coercePropValue(integerSchema, '3')).toBe(3);
        expect(coercePropValue(integerSchema, '4')).toBe(4);
        expect(coercePropValue(integerSchema, '2')).toBe(2);

        // Heading level emitted as string "1"
        const headingLevelSchema: PropSchemaDefinition = {
            type: 'integer',
            enum: [1, 2, 3, 4, 5, 6],
            default: 2,
        };
        expect(coercePropValue(headingLevelSchema, '1')).toBe(1);
        expect(coercePropValue(headingLevelSchema, '6')).toBe(6);
    });

    it('does not coerce string values when schema type is string', () => {
        const stringSchema: PropSchemaDefinition = {
            type: 'string',
            enum: ['primary', 'secondary', 'outline', 'ghost'],
            default: 'primary',
        };

        expect(coercePropValue(stringSchema, 'secondary')).toBe('secondary');
        expect(coercePropValue(stringSchema, 'outline')).toBe('outline');
    });

    it('coerces boolean values when schema type is boolean', () => {
        const booleanSchema: PropSchemaDefinition = {
            type: 'boolean',
            default: true,
        };

        expect(coercePropValue(booleanSchema, true)).toBe(true);
        expect(coercePropValue(booleanSchema, false)).toBe(false);
    });

    it('safely handles undefined schema or invalid inputs', () => {
        expect(coercePropValue(undefined, 'custom')).toBe('custom');
        const integerSchema: PropSchemaDefinition = { type: 'integer' };
        expect(coercePropValue(integerSchema, 'not-a-number')).toBe('not-a-number');
    });
});

describe('canPerformNodeAction (F-04)', () => {
    it('blocks delete and duplicate on root nodes regardless of node ID', () => {
        // Root node cannot be deleted
        expect(canPerformNodeAction('delete', true, false)).toBe(false);
        // Root node cannot be duplicated
        expect(canPerformNodeAction('duplicate', true, false)).toBe(false);
        // Root node cannot be moved
        expect(canPerformNodeAction('move', true, false)).toBe(false);
    });

    it('allows delete and duplicate on non-root nodes when in editor mode', () => {
        expect(canPerformNodeAction('delete', false, false)).toBe(true);
        expect(canPerformNodeAction('duplicate', false, false)).toBe(true);
        expect(canPerformNodeAction('move', false, false)).toBe(true);
    });

    it('blocks all node actions when in read-only viewer mode', () => {
        expect(canPerformNodeAction('delete', false, true)).toBe(false);
        expect(canPerformNodeAction('duplicate', false, true)).toBe(false);
        expect(canPerformNodeAction('move', false, true)).toBe(false);
    });
});
