import { ref } from 'vue';
import { publishAfterDraftSave } from '@/lib/publishGuard';

export type PublishProtocolResult =
    | { status: 'published' }
    | { status: 'save_failed' }
    | { status: 'publish_failed'; message?: string }
    | { status: 'exception'; message: string }
    | { status: 'busy' };

export function createPublishProtocol(options: {
    saveDraft: () => Promise<boolean>;
    publish: () => Promise<{ success: boolean; message?: string }>;
}) {
    const isPublishing = ref(false);

    const execute = async (
        needsSave: boolean,
    ): Promise<PublishProtocolResult> => {
        if (isPublishing.value) return { status: 'busy' };
        isPublishing.value = true;

        try {
            let publishResult: { success: boolean; message?: string } = {
                success: false,
            };
            const continued = await publishAfterDraftSave({
                needsSave,
                saveDraft: options.saveDraft,
                publish: async () => {
                    publishResult = await options.publish();
                },
            });
            if (!continued) return { status: 'save_failed' };
            if (!publishResult.success) {
                return {
                    status: 'publish_failed',
                    message: publishResult.message,
                };
            }

            return { status: 'published' };
        } catch (error) {
            return {
                status: 'exception',
                message:
                    error instanceof Error
                        ? error.message
                        : 'Network error during publish.',
            };
        } finally {
            isPublishing.value = false;
        }
    };

    return { isPublishing, execute };
}
