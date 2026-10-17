<script setup lang="ts">
import { ref } from 'vue';
import { ImageIcon, ImageOff } from '@lucide/vue';
import type { MediaAssetItem } from '@/types/media';

const props = withDefaults(
    defineProps<{
        asset: MediaAssetItem;
        isSelected?: boolean;
    }>(),
    {
        isSelected: false,
    },
);

const emit = defineEmits<{
    (e: 'select', asset: MediaAssetItem): void;
}>();

const hasError = ref(false);

function handleImageError() {
    hasError.value = true;
}
</script>

<template>
    <div
        class="group relative flex cursor-pointer flex-col overflow-hidden rounded-md border bg-card text-left transition-all select-none hover:border-primary/50 focus:ring-2 focus:ring-primary focus:ring-offset-1 focus:outline-none"
        :class="[
            isSelected
                ? 'border-primary bg-primary/5 shadow-sm ring-2 ring-primary'
                : 'border-border',
        ]"
        tabindex="0"
        role="button"
        :aria-pressed="isSelected"
        @click="emit('select', asset)"
        @keydown.enter.prevent="emit('select', asset)"
        @keydown.space.prevent="emit('select', asset)"
    >
        <!-- Thumbnail Container -->
        <div
            class="relative flex aspect-square w-full items-center justify-center overflow-hidden bg-muted/40"
        >
            <template v-if="!hasError">
                <img
                    :src="asset.url"
                    :alt="asset.alt_text || asset.original_filename"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                    @error="handleImageError"
                />
            </template>
            <template v-else>
                <div
                    class="flex flex-col items-center justify-center gap-1 p-2 text-center text-muted-foreground"
                >
                    <ImageOff class="h-6 w-6" />
                    <span class="text-[10px]">Failed to load</span>
                </div>
            </template>

            <!-- Dimensions Badge -->
            <div
                v-if="asset.width && asset.height"
                class="absolute right-1 bottom-1 rounded bg-black/60 px-1 py-0.5 font-mono text-[9px] text-white backdrop-blur-xs"
            >
                {{ asset.width }}×{{ asset.height }}
            </div>
        </div>

        <!-- Meta info -->
        <div class="flex flex-col gap-0.5 p-1.5">
            <p
                class="truncate text-xs leading-tight font-medium text-foreground"
                :title="asset.original_filename"
            >
                {{ asset.original_filename }}
            </p>
            <p class="text-[10px] text-muted-foreground">
                {{ asset.formatted_size }}
            </p>
        </div>
    </div>
</template>
