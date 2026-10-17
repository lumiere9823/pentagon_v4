<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
        centered?: boolean;
    }>(),
    {
        maxWidth: 'lg',
        centered: true,
    },
);

const maxWidthClass = computed(() => {
    switch (props.maxWidth) {
        case 'sm':
            return 'max-w-screen-sm';
        case 'md':
            return 'max-w-screen-md';
        case 'xl':
            return 'max-w-screen-xl';
        case 'full':
            return 'max-w-full';
        default:
            return 'max-w-screen-lg';
    }
});
</script>

<template>
    <div
        :class="[
            'w-full px-4 transition-all sm:px-6',
            maxWidthClass,
            centered ? 'mx-auto' : '',
        ]"
    >
        <slot />
    </div>
</template>
