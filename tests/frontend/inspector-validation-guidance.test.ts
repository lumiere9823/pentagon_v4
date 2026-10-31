// @vitest-environment happy-dom

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import InspectorContentGroup from '@/components/editor/inspector/InspectorContentGroup.vue';
import type {
    DocumentNode,
    InspectorPropertyMetadata,
} from '@/types/document';

describe('Package 3H / Phase 3 R-17: Client-side Validation Guidance in Inspector', () => {
    const requiredTextField: InspectorPropertyMetadata = {
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

    const requiredUrlField: InspectorPropertyMetadata = {
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

    const imageMediaField: InspectorPropertyMetadata = {
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

    const textareaField: InspectorPropertyMetadata = {
        key: 'content',
        path: 'props.content',
        label: 'Body Content',
        group: 'content',
        control: 'textarea',
        responsive: false,
        type: 'string',
        required: true,
        maxLength: 65535,
    };

    const faqField: InspectorPropertyMetadata = {
        key: 'items',
        path: 'props.items',
        label: 'FAQ Items',
        group: 'content',
        control: 'faq-list',
        responsive: false,
        type: 'array',
        maxItems: 20,
        itemSchema: {
            question: { required: true, maxLength: 255 },
            answer: { required: true, maxLength: 2000 },
        },
    };

    describe('Required property indicators and validation error feedback', () => {
        it('renders a visible asterisk indicator and aria-required attribute on required props', () => {
            const node: DocumentNode = {
                id: 'heading_1',
                type: 'Heading',
                props: { text: 'Valid Headline' },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [requiredTextField],
                    readOnly: false,
                },
            });

            // Label contains asterisk indicator
            const label = wrapper.get('label');
            expect(label.text()).toContain('Headline Text');
            expect(label.find('.text-destructive').exists()).toBe(true);

            // Input has aria-required="true"
            const input = wrapper.get('input#prop-text');
            expect(input.attributes('aria-required')).toBe('true');
            expect(input.attributes('aria-invalid')).toBe('false');
            expect(wrapper.find('[data-testid="field-error"]').exists()).toBe(false);

            wrapper.unmount();
        });

        it('displays error message and marks aria-invalid when required field is empty', async () => {
            const node: DocumentNode = {
                id: 'heading_1',
                type: 'Heading',
                props: { text: '' }, // empty required text
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [requiredTextField],
                    readOnly: false,
                },
            });

            const input = wrapper.get('input#prop-text');
            expect(input.attributes('aria-invalid')).toBe('true');
            expect(input.attributes('aria-describedby')).toBe('prop-text-error');

            const error = wrapper.get('[data-testid="field-error"]');
            expect(error.attributes('role')).toBe('alert');
            expect(error.text()).toBe('Headline Text is required.');

            wrapper.unmount();
        });

        it('displays specific publish guidance error when image source is empty', () => {
            const node: DocumentNode = {
                id: 'image_1',
                type: 'Image',
                props: { src: '' },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [imageMediaField],
                    readOnly: false,
                },
            });

            const error = wrapper.get('[data-testid="field-error"]');
            expect(error.text()).toBe('Image source is required before publishing.');

            wrapper.unmount();
        });
    });

    describe('URL validation guidance (safe vs unsafe schemes)', () => {
        it('displays URL format guidance when an unsafe or invalid URL is provided', () => {
            const node: DocumentNode = {
                id: 'btn_1',
                type: 'Button',
                props: { url: 'javascript:alert(1)' },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [requiredUrlField],
                    readOnly: false,
                },
            });

            const input = wrapper.get('input#prop-url');
            expect(input.attributes('aria-invalid')).toBe('true');

            const error = wrapper.get('[data-testid="field-error"]');
            expect(error.text()).toBe(
                'Please enter a valid URL (http, https, mailto, tel, or relative path).',
            );

            wrapper.unmount();
        });

        it('clears error guidance when URL is valid and safe', () => {
            const node: DocumentNode = {
                id: 'btn_1',
                type: 'Button',
                props: { url: 'https://example.com/learn-more' },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [requiredUrlField],
                    readOnly: false,
                },
            });

            const input = wrapper.get('input#prop-url');
            expect(input.attributes('aria-invalid')).toBe('false');
            expect(wrapper.find('[data-testid="field-error"]').exists()).toBe(false);

            wrapper.unmount();
        });
    });

    describe('Live character counters on text and textarea controls', () => {
        it('renders character count / max length for text inputs', () => {
            const node: DocumentNode = {
                id: 'heading_1',
                type: 'Heading',
                props: { text: 'Hello' },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [requiredTextField],
                    readOnly: false,
                },
            });

            expect(wrapper.text()).toContain('5/255');
            wrapper.unmount();
        });

        it('renders character count and validation error on textarea controls', () => {
            const node: DocumentNode = {
                id: 'richtext_1',
                type: 'RichText',
                props: { content: '<p>Some content</p>' },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [textareaField],
                    readOnly: false,
                },
            });

            expect(wrapper.text()).toContain('19/65535');
            expect(wrapper.find('[data-testid="field-error"]').exists()).toBe(false);

            wrapper.unmount();
        });
    });

    describe('FAQ item list limits and item validation guidance', () => {
        it('displays current count and allows adding FAQ items when below limit', async () => {
            const node: DocumentNode = {
                id: 'faq_1',
                type: 'FAQ',
                props: {
                    items: [
                        { question: 'Q1', answer: 'A1' },
                        { question: 'Q2', answer: 'A2' },
                    ],
                },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [faqField],
                    readOnly: false,
                },
            });

            const count = wrapper.get('[data-testid="faq-count"]');
            expect(count.text()).toBe('(2/20)');

            const addBtn = wrapper.get('[data-testid="add-faq-btn"]');
            expect(addBtn.attributes('disabled')).toBeUndefined();

            await addBtn.trigger('click');
            expect(wrapper.emitted('updateProp')).toBeTruthy();
            const payload = wrapper.emitted('updateProp')![0][0] as any;
            expect(payload.key).toBe('items');
            expect(payload.value).toHaveLength(3);

            wrapper.unmount();
        });

        it('disables Add FAQ button and displays limit notice when 20 items reached', async () => {
            const fullItems = Array.from({ length: 20 }, (_, i) => ({
                question: `Question ${i + 1}`,
                answer: `Answer ${i + 1}`,
            }));

            const node: DocumentNode = {
                id: 'faq_1',
                type: 'FAQ',
                props: { items: fullItems },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [faqField],
                    readOnly: false,
                },
            });

            const count = wrapper.get('[data-testid="faq-count"]');
            expect(count.text()).toBe('(20/20)');

            const addBtn = wrapper.get('[data-testid="add-faq-btn"]');
            expect(addBtn.attributes('disabled')).toBeDefined();

            const notice = wrapper.get('[data-testid="faq-limit-notice"]');
            expect(notice.text()).toContain('Maximum limit of 20 FAQ items reached.');

            // Clicking disabled button emits nothing
            await addBtn.trigger('click');
            expect(wrapper.emitted('updateProp')).toBeFalsy();

            wrapper.unmount();
        });

        it('flags missing question or answer on individual FAQ items', () => {
            const node: DocumentNode = {
                id: 'faq_1',
                type: 'FAQ',
                props: {
                    items: [
                        { question: '', answer: 'Valid Answer' },
                        { question: 'Valid Question', answer: '' },
                    ],
                },
            };

            const wrapper = mount(InspectorContentGroup, {
                props: {
                    node,
                    fields: [faqField],
                    readOnly: false,
                },
            });

            const items = wrapper.findAll('[data-testid="faq-item"]');
            expect(items).toHaveLength(2);

            // Item 1 has question error
            const item1 = items[0];
            const qInput1 = item1.get('[data-testid="faq-question-input"]');
            expect(qInput1.attributes('aria-invalid')).toBe('true');
            expect(item1.get('[data-testid="faq-question-error"]').text()).toBe(
                'Question is required.',
            );
            expect(item1.find('[data-testid="faq-answer-error"]').exists()).toBe(false);

            // Item 2 has answer error
            const item2 = items[1];
            const aInput2 = item2.get('[data-testid="faq-answer-input"]');
            expect(aInput2.attributes('aria-invalid')).toBe('true');
            expect(item2.get('[data-testid="faq-answer-error"]').text()).toBe(
                'Answer is required.',
            );
            expect(item2.find('[data-testid="faq-question-error"]').exists()).toBe(false);

            wrapper.unmount();
        });
    });
});
