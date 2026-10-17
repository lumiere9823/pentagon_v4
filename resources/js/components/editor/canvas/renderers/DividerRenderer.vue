<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        style?: 'solid' | 'dashed' | 'dotted';
        color?: 'muted' | 'primary' | 'subtle';
    }>(),
    {
        style: 'solid',
        color: 'muted',
    },
);

const borderStyleClass = computed(() => {
    switch (props.style) {
        case 'dashed':
            return 'border-dashed';
        case 'dotted':
            return 'border-dotted';
        default:
            return 'border-solid';
    }
});

const colorClass = computed(() => {
    switch (props.color) {
        case 'primary':
            return 'border-primary/40';
        case 'subtle':
            return 'border-border/40';
        default:
            return 'border-border';
    }
});
</script>

<template>
    <div class="my-6 w-full py-2">
        <hr :class="['w-full border-t', borderStyleClass, colorClass]" />
    </div>
</template>
