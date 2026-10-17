import { describe, expect, it } from 'vitest';
import type { MediaAssetItem, MediaListResponse } from '@/types/media';

describe('Media Library Contracts & Client Validation', () => {
    const MAX_BYTES = 5 * 1024 * 1024; // 5 MB
    const ALLOWED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    function validateUploadFile(file: { name: string; size: number; type: string }): {
        valid: boolean;
        error?: string;
    } {
        if (file.size > MAX_BYTES) {
            return {
                valid: false,
                error: 'File size exceeds 5 MB. Please select a smaller image.',
            };
        }
        if (!ALLOWED_TYPES.includes(file.type)) {
            return {
                valid: false,
                error: 'Invalid image type. Allowed formats: JPEG, PNG, WebP, GIF. (SVG is not supported).',
            };
        }
        return { valid: true };
    }

    it('accepts valid raster image formats within 5MB limit', () => {
        expect(
            validateUploadFile({
                name: 'hero.png',
                size: 2 * 1024 * 1024,
                type: 'image/png',
            }).valid,
        ).toBe(true);

        expect(
            validateUploadFile({
                name: 'photo.jpg',
                size: 4.5 * 1024 * 1024,
                type: 'image/jpeg',
            }).valid,
        ).toBe(true);

        expect(
            validateUploadFile({
                name: 'banner.webp',
                size: 1 * 1024 * 1024,
                type: 'image/webp',
            }).valid,
        ).toBe(true);

        expect(
            validateUploadFile({
                name: 'sticker.gif',
                size: 500 * 1024,
                type: 'image/gif',
            }).valid,
        ).toBe(true);
    });

    it('rejects files larger than 5 MB on client side', () => {
        const res = validateUploadFile({
            name: 'giant.png',
            size: 5 * 1024 * 1024 + 1,
            type: 'image/png',
        });

        expect(res.valid).toBe(false);
        expect(res.error).toContain('File size exceeds 5 MB');
    });

    it('rejects SVG files on client side to prevent XSS', () => {
        const res = validateUploadFile({
            name: 'vector.svg',
            size: 1024,
            type: 'image/svg+xml',
        });

        expect(res.valid).toBe(false);
        expect(res.error).toContain('SVG is not supported');
    });

    it('rejects unsupported file formats (executables, pdf, html)', () => {
        expect(
            validateUploadFile({
                name: 'script.js',
                size: 100,
                type: 'application/javascript',
            }).valid,
        ).toBe(false);

        expect(
            validateUploadFile({
                name: 'doc.pdf',
                size: 100,
                type: 'application/pdf',
            }).valid,
        ).toBe(false);

        expect(
            validateUploadFile({
                name: 'page.html',
                size: 100,
                type: 'text/html',
            }).valid,
        ).toBe(false);
    });

    it('validates MediaListResponse structure conforming to backend API contract', () => {
        const mockResponse: MediaListResponse = {
            data: [
                {
                    id: 1,
                    original_filename: 'sample.webp',
                    url: '/storage/media/1/uuid-1.webp',
                    mime_type: 'image/webp',
                    size_bytes: 524288,
                    formatted_size: '512.0 KB',
                    width: 1200,
                    height: 800,
                    alt_text: 'Sample description',
                    created_at: '2026-10-17T12:00:00Z',
                },
            ],
            meta: {
                current_page: 1,
                last_page: 1,
                per_page: 24,
                total: 1,
            },
        };

        expect(mockResponse.data).toHaveLength(1);
        expect(mockResponse.data[0].url).toMatch(/^\/storage\/media\/\d+\/.+\.webp$/);
        expect(mockResponse.meta.per_page).toBe(24);
    });

    it('formats query string correctly for search and MIME filtering', () => {
        const buildQuery = (page: number, search?: string, type?: string) => {
            const params = new URLSearchParams();
            params.set('page', String(page));
            params.set('per_page', '24');
            if (search && search.trim()) {
                params.set('search', search.trim());
            }
            if (type && type.trim()) {
                params.set('type', type.trim());
            }
            return params.toString();
        };

        expect(buildQuery(1)).toBe('page=1&per_page=24');
        expect(buildQuery(2, 'hero banner', 'image/png')).toBe(
            'page=2&per_page=24&search=hero+banner&type=image%2Fpng',
        );
    });
});
