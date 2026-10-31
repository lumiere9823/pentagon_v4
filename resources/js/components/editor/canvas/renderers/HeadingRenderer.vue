<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        text?: string;
        level?: number;
        align?: 'left' | 'center' | 'right';
        blockSpacing?: 'none' | 'small' | 'medium' | 'large';
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
        fontSize?: 'semantic' | 'small' | 'medium' | 'large' | 'xlarge';
        textColor?: 'foreground' | 'soft' | 'muted' | 'primary';
    }>(),
    {
        text: 'Heading',
        level: 2,
        align: 'left',
        blockSpacing: 'small',
        maxWidth: 'full',
        fontSize: 'semantic',
        textColor: 'foreground',
    },
);

const tag = computed(() => {
    const lvl = Math.max(1, Math.min(6, props.level || 2));
    return `h${lvl}`;
});

const sizeClass = computed(() => {
    switch (props.level) {
        case 1:
            return 'text-3xl sm:text-4xl font-extrabold tracking-tight';
        case 2:
            return 'text-2xl sm:text-3xl font-bold tracking-tight';
        case 3:
            return 'text-xl sm:text-2xl font-bold';
        case 4:
            return 'text-lg sm:text-xl font-semibold';
        case 5:
            return 'text-base sm:text-lg font-medium';
        default:
            return 'text-sm sm:text-base font-medium';
    }
});

const alignClass = computed(() => {
    switch (props.align) {
        case 'center':
            return 'text-center';
        case 'right':
            return 'text-right';
        default:
            return 'text-left';
    }
});
</script>

<template>
    <component
        :is="tag"
        :class="['my-2 leading-tight text-foreground', sizeClass, alignClass]"
    >
        {{ text || 'Heading' }}
    </component>
</template>
