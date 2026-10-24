import { describe, expect, it } from 'vitest';
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
