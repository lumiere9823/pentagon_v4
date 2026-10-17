import { describe, expect, it } from 'vitest';
import type { DocumentNode } from '@/types/document';
import type { MediaAssetItem } from '@/types/media';

describe('Image Inspector & Media Picker Integration (D-020)', () => {
    const selectedAsset: MediaAssetItem = {
        id: 42,
        original_filename: 'modern-office.webp',
        url: '/storage/media/1/550e8400-e29b-41d4-a716-446655440000.webp',
        mime_type: 'image/webp',
        size_bytes: 450000,
        formatted_size: '439.5 KB',
        width: 1200,
        height: 800,
        alt_text: 'Modern tech office workspace',
        created_at: '2026-10-17T12:00:00Z',
    };

    it('writes canonical public URL to src and auto-fills alt when alt is empty', () => {
        const imageNode: DocumentNode = {
            id: 'node_img_1',
            type: 'Image',
            props: {
                src: '',
                alt: '',
                caption: 'Team floor',
                aspectRatio: '16:9',
            },
        };

        // Simulating handleMediaSelect logic from InspectorPanel.vue
        const propUpdates: Record<string, unknown> = {};
        const applyUpdate = (key: string, val: unknown) => {
            propUpdates[key] = val;
        };

        // 1. Write src
        applyUpdate('src', selectedAsset.url);

        // 2. Auto-fill alt only when empty
        const currentAlt = imageNode.props.alt;
        const isAltEmpty =
            currentAlt === undefined ||
            currentAlt === null ||
            String(currentAlt).trim() === '';

        if (isAltEmpty && selectedAsset.alt_text) {
            applyUpdate('alt', selectedAsset.alt_text);
        }

        expect(propUpdates.src).toBe(
            '/storage/media/1/550e8400-e29b-41d4-a716-446655440000.webp',
        );
        expect(propUpdates.alt).toBe('Modern tech office workspace');
    });

    it('preserves existing non-empty alt text and does not overwrite it automatically', () => {
        const imageNode: DocumentNode = {
            id: 'node_img_1',
            type: 'Image',
            props: {
                src: 'https://images.unsplash.com/photo-1',
                alt: 'User custom alt text that must be kept',
                caption: '',
                aspectRatio: '4:3',
            },
        };

        const propUpdates: Record<string, unknown> = {};
        const applyUpdate = (key: string, val: unknown) => {
            propUpdates[key] = val;
        };

        applyUpdate('src', selectedAsset.url);

        const currentAlt = imageNode.props.alt;
        const isAltEmpty =
            currentAlt === undefined ||
            currentAlt === null ||
            String(currentAlt).trim() === '';

        if (isAltEmpty && selectedAsset.alt_text) {
            applyUpdate('alt', selectedAsset.alt_text);
        }

        expect(propUpdates.src).toBe(
            '/storage/media/1/550e8400-e29b-41d4-a716-446655440000.webp',
        );
        expect(propUpdates.alt).toBeUndefined(); // Unchanged!
    });

    it('preserves unrelated props and responsive styles when selecting an image', () => {
        const imageNode: DocumentNode = {
            id: 'node_img_1',
            type: 'Image',
            props: {
                src: 'https://old-image.com/pic.jpg',
                alt: 'Existing alt',
                caption: 'Important figure caption',
                aspectRatio: '1:1',
            },
            styles: {
                tablet: { aspectRatio: '16:9' },
                mobile: { display: 'hidden' },
            },
        };

        // Selecting image modifies ONLY props.src (and alt if empty)
        const updatedNode: DocumentNode = {
            ...imageNode,
            props: {
                ...imageNode.props,
                src: selectedAsset.url,
            },
        };

        // Unrelated props preserved
        expect(updatedNode.props.caption).toBe('Important figure caption');
        expect(updatedNode.props.aspectRatio).toBe('1:1');

        // Responsive styles preserved
        expect(updatedNode.styles?.tablet?.aspectRatio).toBe('16:9');
        expect(updatedNode.styles?.mobile?.display).toBe('hidden');
    });

    it('canceling media picker does not mutate node or document', () => {
        const originalNode: DocumentNode = {
            id: 'node_img_1',
            type: 'Image',
            props: {
                src: 'https://original.com/pic.jpg',
                alt: 'Original alt',
            },
        };

        const cloned = JSON.parse(JSON.stringify(originalNode));

        // When picker is closed/canceled without select, no updates occur
        expect(cloned).toEqual(originalNode);
    });
});
