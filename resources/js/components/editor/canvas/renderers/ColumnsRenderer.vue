<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        count?: number;
        gap?: 'small' | 'medium' | 'large';
    }>(),
    {
        count: 2,
        gap: 'medium',
    },
);

const gridColsClass = computed(() => {
    switch (props.count) {
        case 3:
            return 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3';
        case 4:
            return 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4';
        default:
            return 'grid-cols-1 md:grid-cols-2';
    }
});

const gapClass = computed(() => {
    switch (props.gap) {
        case 'small':
            return 'gap-3 sm:gap-4';
        case 'large':
            return 'gap-8 sm:gap-12';
        default:
            return 'gap-6 sm:gap-8';
    }
});
</script>

<template>
    <div :class="['my-4 grid w-full', gridColsClass, gapClass]">
        <slot />
    </div>
</template>
