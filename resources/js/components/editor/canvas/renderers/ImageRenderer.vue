<script setup lang="ts">
import { computed, ref } from 'vue';
import { Image as ImageIcon } from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        src?: string;
        alt?: string;
        caption?: string;
        aspectRatio?: 'auto' | '16:9' | '4:3' | '1:1';
    }>(),
    {
        src: '',
        alt: '',
        caption: '',
        aspectRatio: 'auto',
    },
);

const hasError = ref(false);

const aspectClass = computed(() => {
    switch (props.aspectRatio) {
        case '16:9':
            return 'aspect-16/9 object-cover';
        case '4:3':
            return 'aspect-4/3 object-cover';
        case '1:1':
            return 'aspect-square object-cover';
        default:
            return 'h-auto max-h-[500px] object-cover';
    }
});
</script>

<template>
    <figure class="my-4 w-full overflow-hidden rounded-lg">
        <div
            v-if="!src || hasError"
            class="flex aspect-16/9 w-full flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/40 p-6 text-center text-muted-foreground"
        >
            <ImageIcon class="h-10 w-10 stroke-1 opacity-60" />
            <p class="mt-2 text-xs font-medium">No Image Source Configured</p>
            <p class="text-[11px] text-muted-foreground/80">
                Set a valid image URL in the Inspector panel.
            </p>
        </div>
        <img
            v-else
            :src="src"
            :alt="alt || 'Image'"
            :class="[
                'w-full rounded-lg shadow-xs transition-opacity',
                aspectClass,
            ]"
            loading="lazy"
            @error="hasError = true"
            @load="hasError = false"
        />
        <figcaption
            v-if="caption"
            class="mt-2 text-center text-xs text-muted-foreground"
        >
            {{ caption }}
        </figcaption>
    </figure>
</template>
