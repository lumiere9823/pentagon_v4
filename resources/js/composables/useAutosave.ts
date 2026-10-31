import { computed, onMounted, onUnmounted, ref, type Ref } from 'vue';
import type { DocumentSchema } from '@/types/document';
import { requestJson } from '@/lib/httpClient';

export type SaveStatus = 'saved' | 'saving' | 'dirty' | 'error' | 'conflict';

export interface ConflictDetails {
    current_lock_version: number;
    updated_at?: string;
}

export interface UseAutosaveOptions {
    websiteId: number;
    pageId: number;
    initialLockVersion: number;
    getCurrentDocument: () => DocumentSchema;
    getSeoMetadata?: () => Record<string, unknown> | null | undefined;
    debounceMs?: number;
    maxRetries?: number;
    onSaveSuccess?: (data: {
        lock_version: number;
        updated_at?: string;
        revision_id?: number;
        revision_number?: number;
    }) => void;
    onConflict?: (details: ConflictDetails) => void;
}

export function useAutosave(options: UseAutosaveOptions) {
    const debounceMs = options.debounceMs ?? 2000;
    const maxRetries = options.maxRetries ?? 3;

    const lockVersion: Ref<number> = ref(options.initialLockVersion);
    const saveStatus: Ref<SaveStatus> = ref('saved');
    const lastSavedAt: Ref<Date | null> = ref(null);
    const errorMessage: Ref<string | null> = ref(null);
    const conflictData: Ref<ConflictDetails | null> = ref(null);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let retryTimer: ReturnType<typeof setTimeout> | null = null;
    let retryCount = 0;
    let inFlightSave = false;
    const pendingChangeAfterSave = ref(false);

    const isDirty = computed(
        () =>
            saveStatus.value === 'dirty' ||
            saveStatus.value === 'error' ||
            saveStatus.value === 'conflict' ||
            pendingChangeAfterSave.value,
    );
    const isSaving = computed(() => saveStatus.value === 'saving');

    const clearTimers = () => {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        if (retryTimer) {
            clearTimeout(retryTimer);
            retryTimer = null;
        }
    };

    /**
     * Mark the document as dirty and restart the autosave debounce timer.
     */
    const notifyChange = () => {
        if (saveStatus.value === 'conflict') {
            // Cannot autosave while unresolved conflict exists
            return;
        }

        if (inFlightSave) {
            pendingChangeAfterSave.value = true;
            return;
        }

        saveStatus.value = 'dirty';
        errorMessage.value = null;

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            void saveNow(undefined, true);
        }, debounceMs);
    };

    /**
     * Immediately execute save request (manual save or debounced autosave flush).
     */
    const saveNow = async (
        changeSummary?: string,
        isAutosave = false,
    ): Promise<boolean> => {
        clearTimers();

        if (inFlightSave) {
            pendingChangeAfterSave.value = true;
            return false;
        }

        if (saveStatus.value === 'conflict') {
            return false;
        }

        inFlightSave = true;
        saveStatus.value = 'saving';
        errorMessage.value = null;

        const payload: Record<string, unknown> = {
            document_json: options.getCurrentDocument(),
            lock_version: lockVersion.value,
            change_summary: changeSummary ?? null,
            is_autosave: isAutosave,
        };
        if (options.getSeoMetadata) {
            payload.seo_metadata = options.getSeoMetadata();
        }

        try {
            const response = await requestJson<any>(
                `/websites/${options.websiteId}/pages/${options.pageId}/content`,
                {
                    method: 'PUT',
                    body: payload,
                },
            );
            const data = response.data;

            if (response.ok && data.success) {
                lockVersion.value = data.lock_version;
                saveStatus.value = 'saved';
                lastSavedAt.value = data.updated_at
                    ? new Date(data.updated_at)
                    : new Date();
                retryCount = 0;
                conflictData.value = null;

                if (options.onSaveSuccess) {
                    options.onSaveSuccess(data);
                }

                inFlightSave = false;

                if (pendingChangeAfterSave.value) {
                    pendingChangeAfterSave.value = false;
                    if (!isAutosave) {
                        return saveNow(changeSummary, false);
                    }
                    notifyChange();
                }

                return true;
            }

            if (response.status === 409) {
                // Optimistic Concurrency Conflict (D-026)
                saveStatus.value = 'conflict';
                conflictData.value = {
                    current_lock_version: data.current_lock_version,
                    updated_at: data.updated_at,
                };

                if (options.onConflict) {
                    options.onConflict(conflictData.value);
                }

                inFlightSave = false;
                pendingChangeAfterSave.value = false;
                return false;
            }

            // Other validation or server error
            saveStatus.value = 'error';
            errorMessage.value = data.message || 'Failed to save page changes.';
            inFlightSave = false;

            if (isAutosave && retryCount < maxRetries) {
                scheduleRetry();
            }

            return false;
        } catch (err: any) {
            saveStatus.value = 'error';
            errorMessage.value =
                err?.message || 'Network error while saving page.';
            inFlightSave = false;

            if (isAutosave && retryCount < maxRetries) {
                scheduleRetry();
            }

            return false;
        }
    };

    const scheduleRetry = () => {
        retryCount++;
        const backoffMs = Math.min(1000 * Math.pow(2, retryCount - 1), 8000);
        retryTimer = setTimeout(() => {
            void saveNow(undefined, true);
        }, backoffMs);
    };

    /**
     * Resolves a conflict by adopting the server's lock version (e.g. for overwrite)
     * or reloading the server document.
     */
    const forceUpdateLockVersion = (newVersion: number) => {
        lockVersion.value = newVersion;
        saveStatus.value = 'dirty';
        conflictData.value = null;
        errorMessage.value = null;
    };

    // Navigation and visibility handlers
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
        if (inFlightSave || isDirty.value) {
            e.preventDefault();
            e.returnValue = '';
        }
    };

    const handleVisibilityChange = () => {
        if (
            typeof document !== 'undefined' &&
            document.visibilityState === 'hidden'
        ) {
            if (saveStatus.value === 'dirty' || pendingChangeAfterSave.value) {
                void saveNow(undefined, true);
            }
        }
    };

    onMounted(() => {
        if (typeof window !== 'undefined') {
            window.addEventListener('beforeunload', handleBeforeUnload);
            document.addEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        }
    });

    onUnmounted(() => {
        clearTimers();
        if (typeof window !== 'undefined') {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        }
    });

    return {
        lockVersion,
        saveStatus,
        isDirty,
        isSaving,
        lastSavedAt,
        errorMessage,
        conflictData,
        notifyChange,
        saveNow,
        forceUpdateLockVersion,
    };
}
