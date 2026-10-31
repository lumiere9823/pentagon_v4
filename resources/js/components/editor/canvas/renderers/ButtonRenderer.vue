<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label?: string;
        url?: string;
        variant?: 'primary' | 'secondary' | 'outline' | 'ghost';
        size?: 'sm' | 'md' | 'lg';
        target?: '_self' | '_blank';
        presentationStyle?: Record<string, string | number>;
    }>(),
    {
        label: 'Button',
        url: '#',
        variant: 'primary',
        size: 'md',
        target: '_self',
        presentationStyle: () => ({}),
    },
);

const variantClass = computed(() => {
    switch (props.variant) {
        case 'secondary':
            return 'bg-secondary text-secondary-foreground hover:bg-secondary/80';
        case 'outline':
            return 'border border-input bg-background hover:bg-accent hover:text-accent-foreground';
        case 'ghost':
            return 'hover:bg-accent hover:text-accent-foreground';
        default:
            return 'bg-primary text-primary-foreground hover:bg-primary/90';
    }
});

const sizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-8 px-3 text-xs';
        case 'lg':
            return 'h-11 px-6 text-base font-semibold';
        default:
            return 'h-9 px-4 text-sm font-medium';
    }
});
</script>

<template>
    <div class="my-2 inline-block">
        <a
            :href="url || '#'"
            :target="target"
            :class="[
                'inline-flex cursor-pointer items-center justify-center rounded-md shadow-xs transition-colors select-none',
                variantClass,
                sizeClass,
            ]"
            :style="presentationStyle"
            @click.prevent
        >
            {{ label || 'Button' }}
        </a>
    </div>
</template>
