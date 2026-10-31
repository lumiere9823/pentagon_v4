import { afterEach, describe, expect, it, vi } from 'vitest';
import { requestJson } from '@/lib/httpClient';

describe('JSON HTTP client', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        delete (globalThis as { document?: unknown }).document;
    });

    it('serializes JSON with the existing XSRF and accept headers', async () => {
        (globalThis as { document?: unknown }).document = {
            cookie: 'XSRF-TOKEN=encoded%20token; theme=light',
            querySelector: () => null,
        };
        const fetcher = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            statusText: 'OK',
            json: async () => ({ success: true }),
        });

        const result = await requestJson<{ success: boolean }>(
            '/content',
            { method: 'PUT', body: { lock_version: 4 } },
            fetcher,
        );

        expect(fetcher).toHaveBeenCalledWith('/content', {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': 'encoded token',
            },
            body: JSON.stringify({ lock_version: 4 }),
        });
        expect(result.data).toEqual({ success: true });
        expect(result.status).toBe(200);
    });

    it('supports media request headers without changing response semantics', async () => {
        (globalThis as { document?: unknown }).document = {
            cookie: '',
            querySelector: () => ({ getAttribute: () => 'meta-token' }),
        };
        const fetcher = vi.fn().mockResolvedValue({
            ok: false,
            status: 409,
            statusText: 'Conflict',
            json: async () => ({ message: 'Asset is referenced.' }),
        });

        const result = await requestJson(
            '/media/1',
            {
                method: 'DELETE',
                csrf: 'both',
                requestedWith: true,
            },
            fetcher,
        );

        expect(result.ok).toBe(false);
        expect(result.status).toBe(409);
        expect(result.data).toEqual({ message: 'Asset is referenced.' });
        expect(fetcher.mock.calls[0][1].headers).toMatchObject({
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': 'meta-token',
            'X-CSRF-TOKEN': 'meta-token',
        });
    });
});
