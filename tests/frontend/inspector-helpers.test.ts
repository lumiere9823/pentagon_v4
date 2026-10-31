import { describe, expect, it } from 'vitest';
import {
    canPerformNodeAction,
    coercePropValue,
    getFaqItemValidationGuidance,
    getFieldValidationGuidance,
    getInspectorFields,
    type PropSchemaDefinition,
    validateInspectorValue,
} from '@/lib/inspectorHelpers';
import type {
    ComponentMetadata,
    InspectorPropertyMetadata,
} from '@/types/document';

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
        expect(coercePropValue(integerSchema, 'not-a-number')).toBe(
            'not-a-number',
        );
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

describe('Inspector metadata safety', () => {
    const selectField: InspectorPropertyMetadata = {
        key: 'level',
        path: 'props.level',
        label: 'Level',
        group: 'style',
        control: 'select',
        responsive: false,
        type: 'integer',
        enum: [1, 2, 3],
        default: 2,
    };

    it('only returns metadata linked to a declared property or the shared display path', () => {
        const metadata = {
            type: 'Heading',
            label: 'Heading',
            category: 'typography',
            allowChildren: false,
            canBeRoot: false,
            props: { level: { type: 'integer', enum: [1, 2, 3] } },
            inspector: [
                selectField,
                { ...selectField, key: 'unknown', path: 'props.unknown' },
                { ...selectField, control: 'unsupported' },
                {
                    ...selectField,
                    key: 'display',
                    path: 'styles.*.display',
                    label: 'Component Visibility',
                    responsive: true,
                    enum: ['visible', 'hidden'],
                },
            ],
        } as ComponentMetadata;

        expect(
            getInspectorFields(metadata, 'style').map((field) => field.key),
        ).toEqual(['level', 'display']);
        expect(getInspectorFields(undefined, 'content')).toEqual([]);
    });

    it.each([
        [
            'type',
            {
                type: 'integer',
                enum: [1, 2, 3],
                stylePrimitive: undefined,
                responsive: true,
            },
        ],
        [
            'enum ordering',
            {
                type: 'string',
                enum: ['small', 'semantic', 'medium', 'large', 'xlarge'],
                stylePrimitive: 'typography.fontSize',
                responsive: true,
            },
        ],
        [
            'style primitive',
            {
                type: 'string',
                enum: ['sm', 'md', 'lg', 'xl', 'full'],
                stylePrimitive: 'dimensions.maxWidth',
                responsive: true,
            },
        ],
        [
            'responsive eligibility',
            {
                type: 'string',
                enum: ['semantic', 'small', 'medium', 'large', 'xlarge'],
                stylePrimitive: 'typography.fontSize',
                responsive: false,
            },
        ],
    ] as const)(
        'rejects Inspector metadata that contradicts property %s',
        (_mismatch, propertySchema) => {
            const metadata = {
                type: 'Heading',
                label: 'Heading',
                category: 'typography',
                allowChildren: false,
                canBeRoot: false,
                props: { fontSize: propertySchema },
                inspector: [
                    {
                        key: 'fontSize',
                        path: 'props.fontSize',
                        label: 'Font Size',
                        group: 'style',
                        control: 'select',
                        responsive: true,
                        type: 'string',
                        enum: [
                            'semantic',
                            'small',
                            'medium',
                            'large',
                            'xlarge',
                        ],
                        stylePrimitive: 'typography.fontSize',
                    },
                ],
            } as unknown as ComponentMetadata;

            expect(getInspectorFields(metadata, 'style')).toEqual([]);
        },
    );

    it('rejects the reported individually valid but contradictory primitive descriptor', () => {
        const metadata = {
            type: 'Heading',
            label: 'Heading',
            category: 'typography',
            allowChildren: false,
            canBeRoot: false,
            props: {
                fontSize: {
                    type: 'string',
                    enum: ['semantic', 'small', 'medium', 'large', 'xlarge'],
                    stylePrimitive: 'typography.fontSize',
                    responsive: true,
                },
            },
            inspector: [
                {
                    key: 'fontSize',
                    path: 'props.fontSize',
                    label: 'Font Size',
                    group: 'style',
                    control: 'select',
                    responsive: true,
                    type: 'string',
                    enum: ['sm', 'md', 'lg', 'xl', 'full'],
                    stylePrimitive: 'dimensions.maxWidth',
                },
            ],
        } as unknown as ComponentMetadata;

        expect(getInspectorFields(metadata, 'style')).toEqual([]);
    });

    it('coerces supported values and rejects invalid enums, URLs, and collection bounds', () => {
        expect(validateInspectorValue(selectField, '3')).toEqual({
            valid: true,
            value: 3,
        });
        expect(validateInspectorValue(selectField, '9')).toEqual({
            valid: false,
        });
        expect(
            validateInspectorValue(
                {
                    ...selectField,
                    key: 'url',
                    path: 'props.url',
                    control: 'url',
                    type: 'string',
                    enum: undefined,
                    isUrl: true,
                    maxLength: 20,
                },
                'javascript:alert(1)',
            ),
        ).toEqual({ valid: false });
        expect(
            validateInspectorValue(
                {
                    ...selectField,
                    key: 'items',
                    path: 'props.items',
                    control: 'faq-list',
                    type: 'array',
                    enum: undefined,
                    maxItems: 1,
                },
                [{}, {}],
            ),
        ).toEqual({ valid: false });
    });
});

describe('R-17: Client-side Validation Guidance (getFieldValidationGuidance & getFaqItemValidationGuidance)', () => {
    const textField: InspectorPropertyMetadata = {
        key: 'text',
        path: 'props.text',
        label: 'Headline Text',
        group: 'content',
        control: 'text',
        responsive: false,
        type: 'string',
        required: true,
        maxLength: 255,
    };

    const urlField: InspectorPropertyMetadata = {
        key: 'url',
        path: 'props.url',
        label: 'Button URL',
        group: 'content',
        control: 'url',
        responsive: false,
        type: 'string',
        required: true,
        isUrl: true,
        maxLength: 2048,
    };

    const optionalUrlField: InspectorPropertyMetadata = {
        key: 'ctaUrl',
        path: 'props.ctaUrl',
        label: 'CTA URL',
        group: 'content',
        control: 'url',
        responsive: false,
        type: 'string',
        required: false,
        isUrl: true,
        maxLength: 2048,
    };

    const imageField: InspectorPropertyMetadata = {
        key: 'src',
        path: 'props.src',
        label: 'Image Source',
        group: 'content',
        control: 'media',
        responsive: false,
        type: 'string',
        required: true,
        isUrl: true,
        maxLength: 2048,
    };

    describe('Required property guidance', () => {
        it('flags missing or whitespace-only strings for required text properties', () => {
            expect(getFieldValidationGuidance(textField, '')).toEqual({
                isValid: false,
                error: 'Headline Text is required.',
            });
            expect(getFieldValidationGuidance(textField, '   ')).toEqual({
                isValid: false,
                error: 'Headline Text is required.',
            });
            expect(getFieldValidationGuidance(textField, null)).toEqual({
                isValid: false,
                error: 'Headline Text is required.',
            });
            expect(getFieldValidationGuidance(textField, undefined)).toEqual({
                isValid: false,
                error: 'Headline Text is required.',
            });
        });

        it('gives specific publish-oriented error message for empty image src', () => {
            expect(getFieldValidationGuidance(imageField, '')).toEqual({
                isValid: false,
                error: 'Image source is required before publishing.',
            });
            expect(getFieldValidationGuidance(imageField, '   ')).toEqual({
                isValid: false,
                error: 'Image source is required before publishing.',
            });
        });

        it('passes valid non-empty string for required properties', () => {
            expect(
                getFieldValidationGuidance(textField, 'Welcome to Pentagon'),
            ).toEqual({
                isValid: true,
                error: null,
            });
        });

        it('passes empty string for optional properties', () => {
            expect(getFieldValidationGuidance(optionalUrlField, '')).toEqual({
                isValid: true,
                error: null,
            });
        });
    });

    describe('URL validation guidance (safe schemes vs unsafe schemes)', () => {
        it('accepts valid http, https, mailto, tel, relative paths, and anchor links', () => {
            expect(
                getFieldValidationGuidance(urlField, 'https://example.com'),
            ).toEqual({ isValid: true, error: null });
            expect(
                getFieldValidationGuidance(
                    urlField,
                    'http://localhost:8000/page',
                ),
            ).toEqual({ isValid: true, error: null });
            expect(
                getFieldValidationGuidance(urlField, '/storage/media/test.jpg'),
            ).toEqual({ isValid: true, error: null });
            expect(
                getFieldValidationGuidance(urlField, '#hero-section'),
            ).toEqual({ isValid: true, error: null });
            expect(
                getFieldValidationGuidance(
                    urlField,
                    'mailto:support@pentagon.test',
                ),
            ).toEqual({ isValid: true, error: null });
            expect(
                getFieldValidationGuidance(urlField, 'tel:+1234567890'),
            ).toEqual({ isValid: true, error: null });
        });

        it('rejects unsafe URL schemes like javascript:, data:, and vbscript:', () => {
            expect(
                getFieldValidationGuidance(urlField, 'javascript:alert(1)'),
            ).toEqual({
                isValid: false,
                error: 'Please enter a valid URL (http, https, mailto, tel, or relative path).',
            });
            expect(
                getFieldValidationGuidance(
                    urlField,
                    'data:text/html,<script>alert(1)</script>',
                ),
            ).toEqual({
                isValid: false,
                error: 'Please enter a valid URL (http, https, mailto, tel, or relative path).',
            });
            expect(
                getFieldValidationGuidance(urlField, 'vbscript:msgbox(1)'),
            ).toEqual({
                isValid: false,
                error: 'Please enter a valid URL (http, https, mailto, tel, or relative path).',
            });
        });

        it('rejects protocol-relative URLs (//evil.com) and strings containing control characters', () => {
            expect(
                getFieldValidationGuidance(urlField, '//evil.com/hack'),
            ).toEqual({
                isValid: false,
                error: 'Please enter a valid URL (http, https, mailto, tel, or relative path).',
            });
            expect(
                getFieldValidationGuidance(
                    urlField,
                    'https://example.com/\u0000bad',
                ),
            ).toEqual({
                isValid: false,
                error: 'Please enter a valid URL (http, https, mailto, tel, or relative path).',
            });
        });
    });

    describe('Max length bounds guidance', () => {
        it('flags values that exceed the declared maxLength', () => {
            const shortField: InspectorPropertyMetadata = {
                key: 'label',
                path: 'props.label',
                label: 'Short Label',
                group: 'content',
                control: 'text',
                responsive: false,
                type: 'string',
                maxLength: 5,
            };

            expect(
                getFieldValidationGuidance(shortField, '123456'),
            ).toEqual({
                isValid: false,
                error: 'Short Label exceeds maximum length of 5 characters.',
            });
            expect(
                getFieldValidationGuidance(shortField, '12345'),
            ).toEqual({
                isValid: true,
                error: null,
            });
        });
    });

    describe('FAQ item validation guidance (getFaqItemValidationGuidance)', () => {
        const itemSchema = {
            question: { required: true, maxLength: 255 },
            answer: { required: true, maxLength: 2000, isHtml: true },
        };

        it('flags missing question or missing answer in FAQ item', () => {
            expect(
                getFaqItemValidationGuidance(
                    { question: '', answer: '' },
                    itemSchema,
                ),
            ).toEqual({
                questionError: 'Question is required.',
                answerError: 'Answer is required.',
            });

            expect(
                getFaqItemValidationGuidance(
                    { question: 'What is Pentagon?', answer: '   ' },
                    itemSchema,
                ),
            ).toEqual({
                questionError: null,
                answerError: 'Answer is required.',
            });
        });

        it('flags character limits exceeded for question and answer', () => {
            const longQuestion = 'q'.repeat(256);
            const longAnswer = 'a'.repeat(2001);

            expect(
                getFaqItemValidationGuidance(
                    { question: longQuestion, answer: longAnswer },
                    itemSchema,
                ),
            ).toEqual({
                questionError: 'Question exceeds 255 characters.',
                answerError: 'Answer exceeds 2000 characters.',
            });
        });

        it('passes valid question and answer within length bounds', () => {
            expect(
                getFaqItemValidationGuidance(
                    {
                        question: 'How do I publish a page?',
                        answer: '<p>Click the Publish button in the top toolbar.</p>',
                    },
                    itemSchema,
                ),
            ).toEqual({
                questionError: null,
                answerError: null,
            });
        });
    });
});
