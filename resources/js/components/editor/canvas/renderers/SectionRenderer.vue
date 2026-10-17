<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        padding?: 'none' | 'small' | 'medium' | 'large';
        background?: 'transparent' | 'muted' | 'primary' | 'dark';
    }>(),
    {
        padding: 'medium',
        background: 'transparent',
    },
);

const paddingClass = computed(() => {
    switch (props.padding) {
        case 'none':
            return 'py-0';
        case 'small':
            return 'py-4 sm:py-6';
        case 'large':
            return 'py-16 sm:py-24';
        default:
            return 'py-8 sm:py-12';
    }
});

const backgroundClass = computed(() => {
    switch (props.background) {
        case 'muted':
            return 'bg-muted/50 text-foreground';
        case 'primary':
            return 'bg-primary/10 text-foreground';
        case 'dark':
            return 'bg-zinc-900 text-zinc-100 dark:bg-zinc-950';
        default:
            return 'bg-transparent text-foreground';
    }
});
</script>

<template>
    <section
        :class="['w-full transition-colors', paddingClass, backgroundClass]"
    >
        <slot />
    </section>
</template>
