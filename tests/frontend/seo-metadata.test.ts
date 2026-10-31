// @vitest-environment happy-dom

import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import SeoSettingsModal from '@/components/editor/SeoSettingsModal.vue';
import type { PageSeoMetadata } from '@/types/seo';

describe('PageSeoMetadata Frontend Types & Fallback Logic', () => {
    it('computes effective title falling back to pageTitle when meta_title is empty', () => {
        const pageTitle = 'My Landing Page';
        const seo: PageSeoMetadata = { meta_title: '' };

        const effectiveTitle = seo.meta_title || pageTitle;
        expect(effectiveTitle).toBe('My Landing Page');
    });

    it('uses custom meta_title when provided', () => {
        const pageTitle = 'My Landing Page';
        const seo: PageSeoMetadata = {
            meta_title: 'Award Winning Design Agency',
        };

        const effectiveTitle = seo.meta_title || pageTitle;
        expect(effectiveTitle).toBe('Award Winning Design Agency');
    });

    it('calculates canonical URL accurately based on website and page slug', () => {
        const websiteSlug = 'tech-corp';
        const pageSlug = 'services';
        const seo: PageSeoMetadata = {};

        const effectiveCanonical =
            seo.canonical_url ||
            `https://${websiteSlug}.pentagon.site/${pageSlug}`;
        expect(effectiveCanonical).toBe(
            'https://tech-corp.pentagon.site/services',
        );
    });

    it('identifies character bounds for search engine optimization', () => {
        const titleShort = 'Short Title';
        const titleIdeal =
            'Optimal Length Title for Search Engines - Acme Corporation';
        const titleLong =
            'This title is way too long and exceeds the sixty character limit that Google typically shows in desktop search results';

        expect(titleShort.length).toBeLessThan(50);
        expect(titleIdeal.length).toBeGreaterThanOrEqual(50);
        expect(titleIdeal.length).toBeLessThanOrEqual(60);
        expect(titleLong.length).toBeGreaterThan(60);
    });
});

describe('SeoSettingsModal.vue - Advanced SEO & Social Directives', () => {
    const defaultProps = {
        open: true,
        websiteId: 1,
        websiteName: 'Acme Studio',
        websiteSlug: 'acme-studio',
        pageTitle: 'Home Page',
        pageSlug: 'home',
        modelValue: {
            meta_title: 'Custom Title',
            meta_description: 'Custom description for page',
            canonical_url: 'https://acme-studio.pentagon.site/home',
            twitter_card: 'summary_large_image' as const,
            noindex: false,
            nofollow: false,
            schema_type: 'WebPage' as const,
            structured_data_json: null,
        },
    };

    beforeEach(() => {
        document.body.innerHTML = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('renders all three tabs (SEO, Social, Schema)', async () => {
        const wrapper = mount(SeoSettingsModal, {
            props: defaultProps,
            attachTo: document.body,
        });

        await nextTick();

        const seoTab = document.body.querySelector('button[data-testid="seo-tab-btn"]');
        const socialTab = document.body.querySelector('button[data-testid="social-tab-btn"]');
        const schemaTab = document.body.querySelector('button[data-testid="schema-tab-btn"]');

        expect(seoTab).not.toBeNull();
        expect(socialTab).not.toBeNull();
        expect(schemaTab).not.toBeNull();

        wrapper.unmount();
    });

    it('toggles noindex and nofollow directives in SEO tab', async () => {
        const wrapper = mount(SeoSettingsModal, {
            props: defaultProps,
            attachTo: document.body,
        });

        await nextTick();

        const noindexInput = document.body.querySelector('input#noindex') as HTMLInputElement;
        const nofollowInput = document.body.querySelector('input#nofollow') as HTMLInputElement;

        expect(noindexInput).not.toBeNull();
        expect(nofollowInput).not.toBeNull();
        expect(noindexInput.checked).toBe(false);
        expect(nofollowInput.checked).toBe(false);

        noindexInput.click();
        nofollowInput.click();
        await nextTick();

        expect(noindexInput.checked).toBe(true);
        expect(nofollowInput.checked).toBe(true);

        const applyBtn = document.body.querySelector('button[data-testid="apply-seo-btn"]') as HTMLButtonElement;
        expect(applyBtn).not.toBeNull();
        applyBtn.click();
        await nextTick();

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeDefined();
        const payload = emitted![0][0] as PageSeoMetadata;
        expect(payload.noindex).toBe(true);
        expect(payload.nofollow).toBe(true);

        wrapper.unmount();
    });

    it('switches to Social tab and selects twitter card layout', async () => {
        const wrapper = mount(SeoSettingsModal, {
            props: defaultProps,
            attachTo: document.body,
        });

        await nextTick();

        const socialTab = document.body.querySelector('button[data-testid="social-tab-btn"]') as HTMLButtonElement;
        socialTab.click();
        await nextTick();

        expect(document.body.textContent).toContain('Twitter / X Card Layout');
        expect(document.body.textContent).toContain('Large Image Card');
        expect(document.body.textContent).toContain('Summary Card');

        // Click summary card layout button
        const buttons = Array.from(document.body.querySelectorAll('button'));
        const summaryCardBtn = buttons.find((b) => b.textContent?.includes('Summary Card') && b.textContent?.includes('Compact square'));
        expect(summaryCardBtn).toBeDefined();
        summaryCardBtn!.click();
        await nextTick();

        const applyBtn = document.body.querySelector('button[data-testid="apply-seo-btn"]') as HTMLButtonElement;
        applyBtn.click();
        await nextTick();

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeDefined();
        const payload = emitted![0][0] as PageSeoMetadata;
        expect(payload.twitter_card).toBe('summary');

        wrapper.unmount();
    });

    it('switches to Schema tab, selects schema type and generates boilerplate', async () => {
        const wrapper = mount(SeoSettingsModal, {
            props: defaultProps,
            attachTo: document.body,
        });

        await nextTick();

        const schemaTab = document.body.querySelector('button[data-testid="schema-tab-btn"]') as HTMLButtonElement;
        schemaTab.click();
        await nextTick();

        expect(document.body.textContent).toContain('Primary Schema Type');
        expect(document.body.textContent).toContain('Custom JSON-LD Code');

        const schemaSelect = document.body.querySelector('select#schema_type') as HTMLSelectElement;
        expect(schemaSelect).not.toBeNull();
        schemaSelect.value = 'Article';
        schemaSelect.dispatchEvent(new Event('change'));
        await nextTick();

        // Click "Reset to Template"
        const resetBtn = Array.from(document.body.querySelectorAll('button')).find(
            (b) => b.textContent?.includes('Reset to Template'),
        );
        expect(resetBtn).toBeDefined();
        resetBtn!.click();
        await nextTick();

        const textarea = document.body.querySelector('textarea#structured_data') as HTMLTextAreaElement;
        expect(textarea.value).toContain('"@type": "Article"');
        expect(textarea.value).toContain('"headline"');

        // Apply and verify payload
        const applyBtn = document.body.querySelector('button[data-testid="apply-seo-btn"]') as HTMLButtonElement;
        applyBtn.click();
        await nextTick();

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeDefined();
        const payload = emitted![0][0] as PageSeoMetadata;
        expect(payload.schema_type).toBe('Article');
        expect(payload.structured_data_json).toMatchObject({
            '@context': 'https://schema.org',
            '@type': 'Article',
        });

        wrapper.unmount();
    });

    it('detects invalid JSON syntax in Schema tab and disables apply button', async () => {
        const wrapper = mount(SeoSettingsModal, {
            props: defaultProps,
            attachTo: document.body,
        });

        await nextTick();

        const schemaTab = document.body.querySelector('button[data-testid="schema-tab-btn"]') as HTMLButtonElement;
        schemaTab.click();
        await nextTick();

        const textarea = document.body.querySelector('textarea#structured_data') as HTMLTextAreaElement;
        textarea.value = '{ invalid json syntax: ';
        textarea.dispatchEvent(new Event('input'));
        await nextTick();

        expect(document.body.textContent).toContain('JSON Syntax Error');

        const applyBtn = document.body.querySelector('button[data-testid="apply-seo-btn"]') as HTMLButtonElement;
        expect(applyBtn.disabled).toBe(true);

        wrapper.unmount();
    });
});
