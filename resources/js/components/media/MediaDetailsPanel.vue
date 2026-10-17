<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    AlertTriangle,
    Check,
    Copy,
    ImageOff,
    Loader2,
    Trash2,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MediaAssetItem } from '@/types/media';

const props = withDefaults(
    defineProps<{
        websiteId: number;
        asset: MediaAssetItem | null;
        canManage?: boolean;
    }>(),
    {
        canManage: true,
    },
);

const emit = defineEmits<{
    (e: 'select', asset: MediaAssetItem): void;
    (e: 'updated', asset: MediaAssetItem): void;
    (e: 'deleted', assetId: number): void;
}>();

const altText = ref('');
const isSavingAlt = ref(false);
const isDeleting = ref(false);
const showDeleteConfirm = ref(false);
const deleteError = ref<string | null>(null);
const referencingPages = ref<
    Array<{ id: number; title: string; slug: string }>
>([]);
const copied = ref(false);
const hasPreviewError = ref(false);

watch(
    () => props.asset,
    (newAsset) => {
        altText.value = newAsset?.alt_text || '';
        deleteError.value = null;
        referencingPages.value = [];
        showDeleteConfirm.value = false;
        hasPreviewError.value = false;
        copied.value = false;
    },
    { immediate: true },
);

function getCsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        return meta.getAttribute('content');
    }
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

async function copyUrlToClipboard() {
    if (!props.asset) return;
    try {
        await navigator.clipboard.writeText(props.asset.url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Fallback if clipboard API blocked
    }
}

async function saveAltText() {
    if (!props.asset || isSavingAlt.value) return;

    isSavingAlt.value = true;
    try {
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        const csrf = getCsrfToken();
        if (csrf) {
            headers['X-XSRF-TOKEN'] = csrf;
            headers['X-CSRF-TOKEN'] = csrf;
        }

        const response = await fetch(
            `/websites/${props.websiteId}/media/${props.asset.id}`,
            {
                method: 'PUT',
                headers,
                body: JSON.stringify({
                    alt_text: altText.value.trim(),
                }),
            },
        );

        if (response.ok) {
            const data = await response.json();
            emit('updated', data.data);
        }
    } finally {
        isSavingAlt.value = false;
    }
}

async function executeDelete() {
    if (!props.asset || isDeleting.value) return;

    isDeleting.value = true;
    deleteError.value = null;
    referencingPages.value = [];

    try {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        const csrf = getCsrfToken();
        if (csrf) {
            headers['X-XSRF-TOKEN'] = csrf;
            headers['X-CSRF-TOKEN'] = csrf;
        }

        const response = await fetch(
            `/websites/${props.websiteId}/media/${props.asset.id}`,
            {
                method: 'DELETE',
                headers,
            },
        );

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422 && data.error_code === 'media_in_use') {
                deleteError.value =
                    data.message ||
                    'Cannot delete media asset because it is in use.';
                referencingPages.value = data.referencing_pages || [];
            } else {
                deleteError.value =
                    data.message ||
                    `Failed to delete media asset (${response.status}).`;
            }
            return;
        }

        emit('deleted', props.asset.id);
    } catch (err) {
        deleteError.value =
            err instanceof Error
                ? err.message
                : 'Network error during deletion.';
    } finally {
        isDeleting.value = false;
    }
}
</script>

<template>
    <div
        v-if="asset"
        class="flex h-full flex-col gap-4 overflow-y-auto border-l bg-muted/10 p-4 text-left"
    >
        <!-- Preview Image -->
        <div
            class="relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-md border bg-muted/40"
        >
            <template v-if="!hasPreviewError">
                <img
                    :src="asset.url"
                    :alt="asset.alt_text || asset.original_filename"
                    class="h-full w-full object-contain"
                    @error="hasPreviewError = true"
                />
            </template>
            <template v-else>
                <div
                    class="flex flex-col items-center justify-center p-4 text-muted-foreground"
                >
                    <ImageOff class="mb-1 h-8 w-8" />
                    <span class="text-xs">Preview unavailable</span>
                </div>
            </template>
        </div>

        <!-- Meta Details -->
        <div class="flex flex-col gap-1.5 border-b pb-3 text-xs">
            <h4
                class="truncate text-sm font-semibold text-foreground"
                :title="asset.original_filename"
            >
                {{ asset.original_filename }}
            </h4>
            <div
                class="flex items-center gap-2 text-[11px] text-muted-foreground"
            >
                <Badge
                    variant="secondary"
                    class="px-1.5 py-0 text-[10px] font-normal"
                >
                    {{ asset.mime_type }}
                </Badge>
                <span>{{ asset.formatted_size }}</span>
                <span v-if="asset.width && asset.height"
                    >• {{ asset.width }}×{{ asset.height }}px</span
                >
            </div>
        </div>

        <!-- URL Field with Copy Action -->
        <div class="space-y-1">
            <Label for="details-url" class="text-xs font-medium"
                >Public URL</Label
            >
            <div class="flex items-center gap-1.5">
                <Input
                    id="details-url"
                    :model-value="asset.url"
                    readonly
                    class="h-7 bg-muted/30 font-mono text-xs"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-7 shrink-0 px-2"
                    title="Copy URL"
                    @click="copyUrlToClipboard"
                >
                    <Check v-if="copied" class="h-3.5 w-3.5 text-emerald-500" />
                    <Copy v-else class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>

        <!-- Alt Text -->
        <div class="space-y-1">
            <Label for="details-alt-text" class="text-xs font-medium"
                >Alt Text</Label
            >
            <div class="flex flex-col gap-1.5">
                <Input
                    id="details-alt-text"
                    v-model="altText"
                    :disabled="!canManage || isSavingAlt"
                    placeholder="Descriptive text for accessibility..."
                    class="h-8 text-xs"
                    maxlength="255"
                />
                <Button
                    v-if="canManage"
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="h-7 self-start text-xs"
                    :disabled="
                        isSavingAlt || altText === (asset.alt_text || '')
                    "
                    @click="saveAltText"
                >
                    <Loader2
                        v-if="isSavingAlt"
                        class="mr-1 h-3 w-3 animate-spin"
                    />
                    Save Alt Text
                </Button>
            </div>
        </div>

        <!-- In-Use Warning or Delete Error -->
        <div
            v-if="deleteError"
            class="flex flex-col gap-1.5 rounded-md border border-amber-500/50 bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-400"
            role="alert"
        >
            <div class="flex items-center gap-1.5 font-medium">
                <AlertTriangle class="h-4 w-4 shrink-0 text-amber-600" />
                <span>{{ deleteError }}</span>
            </div>
            <div v-if="referencingPages.length > 0" class="mt-1">
                <p class="mb-1 text-[11px] font-medium">Referencing Pages:</p>
                <ul class="list-inside list-disc space-y-0.5 text-[11px]">
                    <li v-for="pg in referencingPages" :key="pg.id">
                        {{ pg.title }} (slug: /{{ pg.slug }})
                    </li>
                </ul>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-auto flex flex-col gap-2 border-t pt-4">
            <Button
                type="button"
                variant="default"
                size="sm"
                class="w-full text-xs"
                @click="emit('select', asset)"
            >
                Select Image
            </Button>

            <!-- Delete Controls (Hidden for Viewers) -->
            <template v-if="canManage">
                <div v-if="!showDeleteConfirm">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="w-full text-xs text-destructive hover:bg-destructive/10"
                        @click="showDeleteConfirm = true"
                    >
                        <Trash2 class="mr-1 h-3.5 w-3.5" />
                        Delete Asset
                    </Button>
                </div>
                <div
                    v-else
                    class="flex flex-col gap-1.5 rounded border border-destructive/30 bg-destructive/5 p-2"
                >
                    <p class="text-[11px] font-medium text-destructive">
                        Delete this asset permanently?
                    </p>
                    <div class="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            class="h-7 flex-1 text-xs"
                            :disabled="isDeleting"
                            @click="executeDelete"
                        >
                            <Loader2
                                v-if="isDeleting"
                                class="mr-1 h-3 w-3 animate-spin"
                            />
                            Confirm
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-7 flex-1 text-xs"
                            :disabled="isDeleting"
                            @click="showDeleteConfirm = false"
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            </template>
        </div>
    </div>
    <div
        v-else
        class="flex h-full flex-col items-center justify-center p-6 text-center text-muted-foreground"
    >
        <p class="text-xs">Select an image to view details and options</p>
    </div>
</template>
