<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        size?: 'small' | 'medium' | 'large' | 'xlarge';
    }>(),
    {
        size: 'medium',
    },
);

const heightClass = computed(() => {
    switch (props.size) {
        case 'small':
            return 'h-4 sm:h-6';
        case 'large':
            return 'h-16 sm:h-20';
        case 'xlarge':
            return 'h-24 sm:h-32';
        default:
            return 'h-8 sm:h-12';
    }
});
</script>

<template>
    <div
        :class="[
            'flex w-full items-center justify-center border border-dashed border-transparent transition-all hover:border-border/40',
            heightClass,
        ]"
        aria-hidden="true"
    />
</template>
