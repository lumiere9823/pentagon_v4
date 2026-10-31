<script setup lang="ts">
import { ref, toRef, watch } from 'vue';
import { useModalFocus } from '@/composables/useModalFocus';
import {
    History,
    RotateCcw,
    X,
    Clock,
    User,
    CheckCircle2,
    AlertCircle,
    Loader2,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { DocumentSchema } from '@/types/document';
import { requestJson } from '@/lib/httpClient';

interface RevisionSummary {
    id: number;
    page_id: number;
    revision_number: number;
    schema_version: number;
    document_hash: string;
    change_summary: string | null;
    is_autosave: boolean;
    created_at: string;
    creator: { id: number; name: string } | null;
}

const props = defineProps<{
    open: boolean;
    websiteId: number;
    pageId: number;
    canEdit: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (
        e: 'restored',
        payload: { document_json: DocumentSchema; lock_version: number },
    ): void;
}>();

const revisions = ref<RevisionSummary[]>([]);
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);
const rollingBackId = ref<number | null>(null);
const modalRef = ref<HTMLElement | null>(null);

useModalFocus(toRef(props, 'open'), modalRef, {
    onClose: () => emit('update:open', false),
});

const fetchRevisions = async () => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await requestJson<any>(
            `/websites/${props.websiteId}/pages/${props.pageId}/revisions?limit=50`,
            {},
        );

        if (response.ok) {
            revisions.value = response.data.revisions || [];
        } else {
            errorMessage.value = 'Failed to load revision history.';
        }
    } catch (err: any) {
        errorMessage.value =
            err?.message || 'Network error while loading revisions.';
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            fetchRevisions();
        }
    },
);

const handleRestore = async (revision: RevisionSummary) => {
    if (!props.canEdit) return;

    const confirmed = window.confirm(
        `Are you sure you want to restore Revision #${revision.revision_number}? This will create a new revision with the restored content without deleting past revisions.`,
    );
    if (!confirmed) return;

    rollingBackId.value = revision.id;
    errorMessage.value = null;

    try {
        const response = await requestJson<any>(
            `/websites/${props.websiteId}/pages/${props.pageId}/revisions/${revision.id}/rollback`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            },
        );
        const data = response.data;

        if (response.ok && data.success) {
            emit('restored', {
                document_json: data.document_json,
                lock_version: data.lock_version,
            });
            emit('update:open', false);
        } else {
            errorMessage.value =
                data.message || 'Failed to rollback to this revision.';
        }
    } catch (err: any) {
        errorMessage.value = err?.message || 'Network error during rollback.';
    } finally {
        rollingBackId.value = null;
    }
};

const formatDate = (dateStr: string) => {
    try {
        const d = new Date(dateStr);
        return d.toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    } catch {
        return dateStr;
    }
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        @click.self="emit('update:open', false)"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            class="relative flex max-h-[85vh] w-full max-w-2xl flex-col rounded-xl border bg-background shadow-2xl focus:outline-hidden"
            role="dialog"
            aria-modal="true"
            aria-labelledby="revisions-modal-title"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b px-5 py-4">
                <div class="flex items-center gap-2">
                    <History class="h-5 w-5 text-primary" />
                    <div>
                        <h2
                            id="revisions-modal-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Revision History
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Immutable snapshots of previous document states
                        </p>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 rounded-full"
                    aria-label="Close revision history"
                    @click="emit('update:open', false)"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>

            <!-- Error Banner -->
            <div
                v-if="errorMessage"
                class="flex items-center gap-2 bg-destructive/10 px-5 py-2.5 text-xs text-destructive"
            >
                <AlertCircle class="h-4 w-4 shrink-0" />
                <span>{{ errorMessage }}</span>
            </div>

            <!-- Content List -->
            <div class="flex-1 overflow-y-auto px-5 py-4">
                <div
                    v-if="isLoading"
                    class="flex h-40 items-center justify-center gap-2 text-xs text-muted-foreground"
                >
                    <Loader2 class="h-4 w-4 animate-spin text-primary" />
                    <span>Loading revisions...</span>
                </div>

                <div
                    v-else-if="revisions.length === 0"
                    class="flex h-40 flex-col items-center justify-center text-center text-xs text-muted-foreground"
                >
                    <History class="h-8 w-8 opacity-30" />
                    <p class="mt-2 font-medium">No revisions recorded yet.</p>
                    <p class="text-[11px] text-muted-foreground/80">
                        Revisions are created automatically upon manual saves or
                        periodic editing milestones.
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="(rev, idx) in revisions"
                        :key="rev.id"
                        class="flex items-center justify-between rounded-lg border bg-card p-3.5 transition-colors hover:bg-accent/40"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-secondary text-xs font-semibold text-foreground"
                            >
                                #{{ rev.revision_number }}
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs font-medium text-foreground"
                                    >
                                        {{
                                            rev.change_summary ||
                                            (rev.is_autosave
                                                ? 'Autosave checkpoint'
                                                : 'Manual snapshot')
                                        }}
                                    </span>
                                    <Badge
                                        :variant="
                                            rev.is_autosave
                                                ? 'secondary'
                                                : 'default'
                                        "
                                        class="px-1.5 py-0 text-[10px]"
                                    >
                                        {{
                                            rev.is_autosave
                                                ? 'Autosave'
                                                : 'Manual'
                                        }}
                                    </Badge>
                                    <Badge
                                        v-if="idx === 0"
                                        variant="outline"
                                        class="border-green-500/40 px-1.5 py-0 text-[10px] text-green-600 dark:text-green-400"
                                    >
                                        Latest
                                    </Badge>
                                </div>
                                <div
                                    class="flex items-center gap-3 text-[11px] text-muted-foreground"
                                >
                                    <span class="flex items-center gap-1">
                                        <Clock class="h-3 w-3" />
                                        {{ formatDate(rev.created_at) }}
                                    </span>
                                    <span
                                        v-if="rev.creator"
                                        class="flex items-center gap-1"
                                    >
                                        <User class="h-3 w-3" />
                                        {{ rev.creator.name }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <Button
                                v-if="canEdit"
                                variant="outline"
                                size="sm"
                                :disabled="rollingBackId !== null || idx === 0"
                                class="h-7 gap-1 text-xs"
                                @click="handleRestore(rev)"
                            >
                                <Loader2
                                    v-if="rollingBackId === rev.id"
                                    class="h-3 w-3 animate-spin"
                                />
                                <RotateCcw v-else class="h-3 w-3" />
                                <span>Restore</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-between border-t bg-muted/20 px-5 py-3"
            >
                <span class="text-[11px] text-muted-foreground">
                    Restoring a revision creates a new revision with that
                    content; past revisions are never deleted.
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-xs"
                    @click="emit('update:open', false)"
                >
                    Close
                </Button>
            </div>
        </div>
    </div>
</template>
