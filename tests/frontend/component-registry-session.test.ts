import { describe, expect, it, vi } from 'vitest';
import { createComponentRegistrySession } from '@/composables/useComponentRegistry';
import { STYLE_PRIMITIVES } from '@/lib/stylePrimitives';

const registryPayload = {
    stylePrimitives: STYLE_PRIMITIVES,
    aliases: { 'Rich Section': 'Section' },
    components: {
        Section: {
            type: 'Section',
            label: 'Section',
            category: 'layout',
            props: {},
        },
    },
    responsive: {
        breakpoints: { tablet: 768, desktop: 1024 },
        overridableProps: {
            Section: ['padding', 'background'],
        },
    },
};

describe('component registry editor session', () => {
    it('deduplicates concurrent consumers within one editor session', async () => {
        const fetcher = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            statusText: 'OK',
            json: async () => registryPayload,
        });
        const session = createComponentRegistrySession(fetcher);

        await Promise.all([session.load(), session.load()]);

        expect(fetcher).toHaveBeenCalledOnce();
        expect(session.components.value).toEqual(registryPayload.components);
        expect(session.resolveMetadata('Rich Section')?.type).toBe('Section');
        expect(session.isLoading.value).toBe(false);
        expect(session.errorMessage.value).toBeNull();
    });

    it('does not share registry state across editor sessions', async () => {
        const fetcher = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            statusText: 'OK',
            json: async () => registryPayload,
        });

        await Promise.all([
            createComponentRegistrySession(fetcher).load(),
            createComponentRegistrySession(fetcher).load(),
        ]);

        expect(fetcher).toHaveBeenCalledTimes(2);
    });

    it('exposes errors and retries with a new request', async () => {
        const fetcher = vi
            .fn()
            .mockResolvedValueOnce({
                ok: false,
                status: 503,
                statusText: 'Unavailable',
                json: async () => ({}),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                statusText: 'OK',
                json: async () => registryPayload,
            });
        const session = createComponentRegistrySession(fetcher);

        await session.load();
        expect(session.errorMessage.value).toContain('503 Unavailable');

        await session.retry();
        expect(fetcher).toHaveBeenCalledTimes(2);
        expect(session.errorMessage.value).toBeNull();
        expect(session.components.value).toEqual(registryPayload.components);
    });
});
