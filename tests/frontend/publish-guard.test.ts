import { describe, expect, it, vi } from 'vitest';
import { publishAfterDraftSave } from '@/lib/publishGuard';

describe('publishAfterDraftSave', () => {
    it('publishes after the latest dirty draft is saved successfully', async () => {
        const saveDraft = vi.fn().mockResolvedValue(true);
        const publish = vi.fn().mockResolvedValue(undefined);

        await expect(
            publishAfterDraftSave({ needsSave: true, saveDraft, publish }),
        ).resolves.toBe(true);

        expect(saveDraft).toHaveBeenCalledOnce();
        expect(publish).toHaveBeenCalledOnce();
        expect(saveDraft.mock.invocationCallOrder[0]).toBeLessThan(
            publish.mock.invocationCallOrder[0],
        );
    });

    it('does not publish when draft save returns false', async () => {
        const saveDraft = vi.fn().mockResolvedValue(false);
        const publish = vi.fn().mockResolvedValue(undefined);

        await expect(
            publishAfterDraftSave({ needsSave: true, saveDraft, publish }),
        ).resolves.toBe(false);

        expect(publish).not.toHaveBeenCalled();
    });

    it('does not publish when optimistic locking reports a conflict', async () => {
        const conflictSave = vi.fn().mockResolvedValue(false);
        const publish = vi.fn().mockResolvedValue(undefined);

        await publishAfterDraftSave({
            needsSave: true,
            saveDraft: conflictSave,
            publish,
        });

        expect(conflictSave).toHaveBeenCalledOnce();
        expect(publish).not.toHaveBeenCalled();
    });

    it('does not publish when saving throws unexpectedly', async () => {
        const saveDraft = vi.fn().mockRejectedValue(new Error('Network error'));
        const publish = vi.fn().mockResolvedValue(undefined);

        await expect(
            publishAfterDraftSave({ needsSave: true, saveDraft, publish }),
        ).rejects.toThrow('Network error');
        expect(publish).not.toHaveBeenCalled();
    });
});
