<script setup lang="ts">
import { ref, toRef, watch } from 'vue';
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    Globe,
    Loader2,
    RefreshCw,
    RotateCcw,
    Send,
    User,
    X,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useModalFocus } from '@/composables/useModalFocus';
import { requestJson } from '@/lib/httpClient';

export interface ReleaseSummary {
    id: number;
    page_id: number;
    release_number: number;
    status: 'active' | 'rolled_back' | string;
    created_at: string;
    creator?: { id: number; name: string; email?: string } | null;
    revision?: {
        id: number;
        revision_number: number;
        change_summary: string | null;
    } | null;
}

const props = defineProps<{
    open: boolean;
    websiteId: number;
    pageId: number;
    canEdit: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'rolledBack', release: ReleaseSummary): void;
}>();

const releases = ref<ReleaseSummary[]>([]);
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const rollingBackId = ref<number | null>(null);
const modalRef = ref<HTMLElement | null>(null);

useModalFocus(toRef(props, 'open'), modalRef, {
    onClose: () => emit('update:open', false),
});

const fetchReleases = async () => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await requestJson<{ releases: ReleaseSummary[] }>(
            `/websites/${props.websiteId}/pages/${props.pageId}/releases?limit=50`,
            {},
        );

        if (response.ok && response.data?.releases) {
            releases.value = response.data.releases;
        } else {
            errorMessage.value = 'Failed to load release history.';
        }
    } catch (err: any) {
        errorMessage.value =
            err?.message || 'Network error while loading releases.';
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            successMessage.value = null;
            fetchReleases();
        }
    },
    { immediate: true },
);

const handleRollback = async (release: ReleaseSummary) => {
    if (!props.canEdit || release.status === 'active') return;

    const confirmed = window.confirm(
        `Are you sure you want to rollback the public site to Release #${release.release_number}? Visitors will immediately see this release.`,
    );
    if (!confirmed) return;

    rollingBackId.value = release.id;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        const response = await requestJson<{
            success: boolean;
            message?: string;
            release?: ReleaseSummary;
        }>(
            `/websites/${props.websiteId}/pages/${props.pageId}/releases/${release.id}/rollback`,
            {
                method: 'POST',
            },
        );

        if (response.ok && response.data?.success) {
            successMessage.value =
                response.data.message ||
                `Successfully rolled back to Release #${release.release_number}.`;
            emit('rolledBack', release);
            await fetchReleases();
        } else {
            errorMessage.value =
                (response.data as any)?.message ||
                'Rollback failed. The release could not be restored.';
        }
    } catch (err: any) {
        errorMessage.value =
            err?.message || 'Network error during release rollback.';
    } finally {
        rollingBackId.value = null;
    }
};

const formatDate = (isoString: string) => {
    try {
        const date = new Date(isoString);
        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return isoString;
    }
};
</script>

<template>
    <teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="emit('update:open', false)"
        >
            <div
                ref="modalRef"
                role="dialog"
                aria-modal="true"
                aria-labelledby="releases-title"
                tabindex="-1"
                class="relative max-h-[85vh] w-full max-w-2xl space-y-4 overflow-y-auto rounded-xl border bg-background p-6 shadow-xl"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2">
                        <Globe class="h-5 w-5 text-primary" />
                        <h2 id="releases-title" class="text-base font-semibold">
                            Public Release History & Rollback
                        </h2>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-muted-foreground"
                        aria-label="Close dialog"
                        @click="emit('update:open', false)"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Live Region Messages -->
                <div
                    v-if="successMessage"
                    role="status"
                    class="flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-700 dark:text-emerald-400"
                >
                    <CheckCircle2 class="h-4 w-4 shrink-0" />
                    <span>{{ successMessage }}</span>
                </div>

                <div
                    v-if="errorMessage"
                    role="alert"
                    class="flex items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive"
                >
                    <AlertCircle class="h-4 w-4 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>

                <p class="text-xs text-muted-foreground">
                    Each publish creates an immutable, compiled public release.
                    You can instantly rollback public visitors to any previous
                    release without mutating draft work.
                </p>

                <!-- Loading State -->
                <div
                    v-if="isLoading"
                    class="flex items-center justify-center gap-2 py-12 text-muted-foreground"
                >
                    <Loader2 class="h-5 w-5 animate-spin" />
                    <span class="text-xs">Loading release history...</span>
                </div>

                <!-- Empty State -->
                <div
                    v-else-if="releases.length === 0"
                    class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-12 text-center text-muted-foreground"
                >
                    <Send
                        class="h-8 w-8 stroke-[1.5] text-muted-foreground/60"
                    />
                    <p class="text-sm font-medium">No releases published yet</p>
                    <p class="max-w-xs text-xs text-muted-foreground/80">
                        Publish your page from the editor to generate your first
                        immutable public release.
                    </p>
                </div>

                <!-- Releases List -->
                <div v-else class="space-y-2">
                    <div
                        v-for="rel in releases"
                        :key="rel.id"
                        class="flex items-center justify-between rounded-lg border p-3 text-xs transition-colors"
                        :class="
                            rel.status === 'active'
                                ? 'border-primary/40 bg-primary/5'
                                : 'bg-card hover:bg-muted/40'
                        "
                        data-testid="release-item"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-foreground">
                                    Release #{{ rel.release_number }}
                                </span>
                                <Badge
                                    v-if="rel.status === 'active'"
                                    variant="default"
                                    class="bg-emerald-600 px-1.5 py-0 text-[10px] text-white hover:bg-emerald-600"
                                >
                                    Live Active
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="px-1.5 py-0 text-[10px] text-muted-foreground"
                                >
                                    Past Release
                                </Badge>

                                <span
                                    v-if="rel.revision"
                                    class="text-[11px] text-muted-foreground"
                                >
                                    (from Revision #{{
                                        rel.revision.revision_number
                                    }})
                                </span>
                            </div>

                            <p
                                v-if="rel.revision?.change_summary"
                                class="text-muted-foreground italic"
                            >
                                "{{ rel.revision.change_summary }}"
                            </p>

                            <div
                                class="flex items-center gap-4 text-[11px] text-muted-foreground"
                            >
                                <span class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" />
                                    {{ formatDate(rel.created_at) }}
                                </span>
                                <span
                                    v-if="rel.creator"
                                    class="flex items-center gap-1"
                                >
                                    <User class="h-3 w-3" />
                                    {{ rel.creator.name }}
                                </span>
                            </div>
                        </div>

                        <!-- Action: Rollback -->
                        <div>
                            <Button
                                v-if="rel.status !== 'active' && canEdit"
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 text-xs text-foreground"
                                :disabled="rollingBackId !== null"
                                data-testid="rollback-release-btn"
                                @click="handleRollback(rel)"
                            >
                                <Loader2
                                    v-if="rollingBackId === rel.id"
                                    class="h-3.5 w-3.5 animate-spin"
                                />
                                <RotateCcw v-else class="h-3.5 w-3.5" />
                                <span>Rollback to this</span>
                            </Button>
                            <span
                                v-else-if="rel.status === 'active'"
                                class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                            >
                                Currently Live
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between border-t pt-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1 text-xs text-muted-foreground"
                        :disabled="isLoading"
                        @click="fetchReleases"
                    >
                        <RefreshCw
                            class="h-3 w-3"
                            :class="{ 'animate-spin': isLoading }"
                        />
                        Refresh
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        class="h-8 text-xs"
                        @click="emit('update:open', false)"
                    >
                        Close
                    </Button>
                </div>
            </div>
        </div>
    </teleport>
</template>
