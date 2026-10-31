import { describe, expect, it, vi } from 'vitest';
import { createPublishProtocol } from '@/lib/publishProtocol';

describe('save and publish protocol', () => {
    it('saves a dirty draft before publishing', async () => {
        const saveDraft = vi.fn().mockResolvedValue(true);
        const publish = vi.fn().mockResolvedValue({ success: true });
        const protocol = createPublishProtocol({ saveDraft, publish });

        await expect(protocol.execute(true)).resolves.toEqual({
            status: 'published',
        });
        expect(saveDraft.mock.invocationCallOrder[0]).toBeLessThan(
            publish.mock.invocationCallOrder[0],
        );
    });

    it('stops on save failure or optimistic-lock conflict', async () => {
        const publish = vi.fn();
        const protocol = createPublishProtocol({
            saveDraft: vi.fn().mockResolvedValue(false),
            publish,
        });

        await expect(protocol.execute(true)).resolves.toEqual({
            status: 'save_failed',
        });
        expect(publish).not.toHaveBeenCalled();
    });

    it('reports publish failure without claiming success', async () => {
        const protocol = createPublishProtocol({
            saveDraft: vi.fn().mockResolvedValue(true),
            publish: vi.fn().mockResolvedValue({
                success: false,
                message: 'Validation failed.',
            }),
        });

        await expect(protocol.execute(false)).resolves.toEqual({
            status: 'publish_failed',
            message: 'Validation failed.',
        });
    });

    it('reports exceptions and always releases the in-flight lock', async () => {
        const publish = vi
            .fn()
            .mockRejectedValueOnce(new Error('Network error'))
            .mockResolvedValueOnce({ success: true });
        const protocol = createPublishProtocol({
            saveDraft: vi.fn().mockResolvedValue(true),
            publish,
        });

        await expect(protocol.execute(false)).resolves.toEqual({
            status: 'exception',
            message: 'Network error',
        });
        await expect(protocol.execute(false)).resolves.toEqual({
            status: 'published',
        });
    });

    it('prevents repeated invocation while save or publish is in flight', async () => {
        let release!: (value: boolean) => void;
        const saveDraft = vi.fn().mockReturnValue(
            new Promise<boolean>((resolve) => {
                release = resolve;
            }),
        );
        const publish = vi.fn().mockResolvedValue({ success: true });
        const protocol = createPublishProtocol({ saveDraft, publish });

        const first = protocol.execute(true);
        await expect(protocol.execute(true)).resolves.toEqual({
            status: 'busy',
        });
        release(true);
        await expect(first).resolves.toEqual({ status: 'published' });
        expect(saveDraft).toHaveBeenCalledOnce();
        expect(publish).toHaveBeenCalledOnce();
    });
});
