<script setup lang="ts">
import { ChevronLeft, ChevronRight, ImageIcon, Loader2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import MediaCard from './MediaCard.vue';
import type { MediaAssetItem, MediaPaginationMeta } from '@/types/media';

const props = withDefaults(
    defineProps<{
        assets: MediaAssetItem[];
        selectedAssetId?: number | null;
        isLoading?: boolean;
        meta?: MediaPaginationMeta | null;
    }>(),
    {
        selectedAssetId: null,
        isLoading: false,
        meta: null,
    },
);

const emit = defineEmits<{
    (e: 'select', asset: MediaAssetItem): void;
    (e: 'change-page', page: number): void;
}>();
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Scrollable Grid Content -->
        <div class="flex-1 overflow-y-auto p-4">
            <!-- Loading Skeletons -->
            <div
                v-if="isLoading"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
            >
                <div
                    v-for="i in 8"
                    :key="i"
                    class="flex flex-col gap-2 rounded-md border p-2"
                >
                    <Skeleton class="aspect-square w-full rounded" />
                    <Skeleton class="h-3 w-3/4" />
                    <Skeleton class="h-2 w-1/2" />
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="assets.length === 0"
                class="flex h-64 flex-col items-center justify-center gap-2 text-center text-muted-foreground"
            >
                <ImageIcon
                    class="h-12 w-12 stroke-[1.5] text-muted-foreground/50"
                />
                <p class="text-sm font-medium text-foreground">
                    No media assets found
                </p>
                <p class="text-xs text-muted-foreground">
                    Upload an image or adjust your search filter to find assets.
                </p>
            </div>

            <!-- Assets Grid -->
            <div
                v-else
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
            >
                <MediaCard
                    v-for="asset in assets"
                    :key="asset.id"
                    :asset="asset"
                    :is-selected="asset.id === selectedAssetId"
                    @select="emit('select', asset)"
                />
            </div>
        </div>

        <!-- Pagination Bar -->
        <div
            v-if="meta && meta.last_page > 1"
            class="flex shrink-0 items-center justify-between border-t bg-card px-4 py-2 text-xs text-muted-foreground"
        >
            <span>
                Showing page {{ meta.current_page }} of {{ meta.last_page }} ({{
                    meta.total
                }}
                total)
            </span>
            <div class="flex items-center gap-1.5">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    :disabled="meta.current_page <= 1 || isLoading"
                    @click="emit('change-page', meta.current_page - 1)"
                >
                    <ChevronLeft class="mr-1 h-3.5 w-3.5" />
                    Previous
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    :disabled="meta.current_page >= meta.last_page || isLoading"
                    @click="emit('change-page', meta.current_page + 1)"
                >
                    Next
                    <ChevronRight class="ml-1 h-3.5 w-3.5" />
                </Button>
            </div>
        </div>
    </div>
</template>
