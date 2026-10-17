<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Filter, FolderOpen, Search, UploadCloud, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import MediaDetailsPanel from './MediaDetailsPanel.vue';
import MediaGrid from './MediaGrid.vue';
import MediaUploadDropzone from './MediaUploadDropzone.vue';
import type {
    MediaAssetItem,
    MediaListResponse,
    MediaPaginationMeta,
} from '@/types/media';

const props = withDefaults(
    defineProps<{
        open: boolean;
        websiteId: number;
        canManage?: boolean;
    }>(),
    {
        canManage: true,
    },
);

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'select', asset: MediaAssetItem): void;
    (e: 'close'): void;
}>();

const activeTab = ref<'browse' | 'upload'>('browse');
const assets = ref<MediaAssetItem[]>([]);
const selectedAsset = ref<MediaAssetItem | null>(null);
const paginationMeta = ref<MediaPaginationMeta | null>(null);
const isLoading = ref(false);
const searchQuery = ref('');
const mimeFilter = ref<string>('');
const currentPage = ref(1);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const MIME_OPTIONS = [
    { label: 'All formats', value: '' },
    { label: 'JPEG', value: 'image/jpeg' },
    { label: 'PNG', value: 'image/png' },
    { label: 'WebP', value: 'image/webp' },
    { label: 'GIF', value: 'image/gif' },
];

async function fetchAssets(page = 1) {
    isLoading.value = true;
    try {
        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('per_page', '24');
        if (searchQuery.value.trim()) {
            params.set('search', searchQuery.value.trim());
        }
        if (mimeFilter.value) {
            params.set('type', mimeFilter.value);
        }

        const response = await fetch(
            `/websites/${props.websiteId}/media?${params.toString()}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (response.ok) {
            const data: MediaListResponse = await response.json();
            assets.value = data.data;
            paginationMeta.value = data.meta;
            currentPage.value = data.meta.current_page;

            // If selected asset is in new list, update reference; otherwise keep or clear
            if (selectedAsset.value) {
                const found = assets.value.find(
                    (a) => a.id === selectedAsset.value?.id,
                );
                if (found) {
                    selectedAsset.value = found;
                }
            }
        }
    } catch {
        // Network error handling
    } finally {
        isLoading.value = false;
    }
}

function handleSearchInput() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchAssets(1);
    }, 300);
}

function handleMimeFilterChange(val: string) {
    mimeFilter.value = val;
    currentPage.value = 1;
    fetchAssets(1);
}

function handlePageChange(newPage: number) {
    currentPage.value = newPage;
    fetchAssets(newPage);
}

function handleSelectAsset(asset: MediaAssetItem) {
    selectedAsset.value = asset;
}

function handleConfirmSelect(asset: MediaAssetItem) {
    emit('select', asset);
    handleClose();
}

function handleAssetUpdated(updated: MediaAssetItem) {
    selectedAsset.value = updated;
    const index = assets.value.findIndex((a) => a.id === updated.id);
    if (index !== -1) {
        assets.value[index] = updated;
    }
}

function handleAssetDeleted(deletedId: number) {
    assets.value = assets.value.filter((a) => a.id !== deletedId);
    if (selectedAsset.value?.id === deletedId) {
        selectedAsset.value = null;
    }
    if (assets.value.length === 0 && currentPage.value > 1) {
        fetchAssets(currentPage.value - 1);
    } else {
        fetchAssets(currentPage.value);
    }
}

function handleUploaded(newAsset: MediaAssetItem) {
    // Switch to browse tab, select new asset, refresh list
    activeTab.value = 'browse';
    selectedAsset.value = newAsset;
    fetchAssets(1);
}

function handleClose() {
    emit('update:open', false);
    emit('close');
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            selectedAsset.value = null;
            fetchAssets(1);
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex h-[85vh] w-[92vw] max-w-4xl! flex-col gap-0 overflow-hidden p-0 sm:rounded-lg"
        >
            <!-- Modal Header -->
            <DialogHeader
                class="flex shrink-0 flex-row items-center justify-between border-b px-5 py-3"
            >
                <div class="flex items-center gap-2">
                    <FolderOpen class="h-5 w-5 text-primary" />
                    <DialogTitle class="text-base font-semibold"
                        >Media Library</DialogTitle
                    >
                </div>
            </DialogHeader>

            <!-- Navigation Tabs & Toolbar -->
            <div
                class="flex shrink-0 flex-col items-stretch justify-between gap-2 border-b bg-muted/20 px-5 py-2.5 sm:flex-row sm:items-center"
            >
                <!-- Tabs -->
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        :variant="activeTab === 'browse' ? 'default' : 'ghost'"
                        size="sm"
                        class="h-7 text-xs font-medium"
                        @click="activeTab = 'browse'"
                    >
                        Browse Library
                    </Button>
                    <Button
                        v-if="canManage"
                        type="button"
                        :variant="activeTab === 'upload' ? 'default' : 'ghost'"
                        size="sm"
                        class="h-7 text-xs font-medium"
                        @click="activeTab = 'upload'"
                    >
                        <UploadCloud class="mr-1 h-3.5 w-3.5" />
                        Upload New
                    </Button>
                </div>

                <!-- Search & Filters (Shown when browsing) -->
                <div
                    v-if="activeTab === 'browse'"
                    class="flex items-center gap-2"
                >
                    <!-- Search Input -->
                    <div class="relative w-44 sm:w-56">
                        <Search
                            class="absolute top-2 left-2.5 h-3.5 w-3.5 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search images..."
                            class="h-7 pl-8 text-xs"
                            @input="handleSearchInput"
                        />
                    </div>

                    <!-- MIME Select Filter -->
                    <select
                        :value="mimeFilter"
                        class="h-7 rounded-md border border-input bg-transparent px-2 text-xs shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        @change="
                            handleMimeFilterChange(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option
                            v-for="opt in MIME_OPTIONS"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Tab Content Body -->
            <div class="min-h-0 flex-1 overflow-hidden">
                <!-- Tab: Browse -->
                <div
                    v-if="activeTab === 'browse'"
                    class="flex h-full flex-col md:flex-row"
                >
                    <!-- Main Grid -->
                    <div class="h-full min-h-0 flex-1 overflow-hidden">
                        <MediaGrid
                            :assets="assets"
                            :selected-asset-id="selectedAsset?.id"
                            :is-loading="isLoading"
                            :meta="paginationMeta"
                            @select="handleSelectAsset"
                            @change-page="handlePageChange"
                        />
                    </div>

                    <!-- Right Details Panel -->
                    <div
                        class="h-auto w-full shrink-0 border-t md:h-full md:w-80 md:border-t-0 md:border-l"
                    >
                        <MediaDetailsPanel
                            :website-id="websiteId"
                            :asset="selectedAsset"
                            :can-manage="canManage"
                            @select="handleConfirmSelect"
                            @updated="handleAssetUpdated"
                            @deleted="handleAssetDeleted"
                        />
                    </div>
                </div>

                <!-- Tab: Upload -->
                <div
                    v-else
                    class="mx-auto flex h-full max-w-xl flex-col justify-center overflow-y-auto p-6"
                >
                    <MediaUploadDropzone
                        :website-id="websiteId"
                        :disabled="!canManage"
                        @uploaded="handleUploaded"
                    />
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
