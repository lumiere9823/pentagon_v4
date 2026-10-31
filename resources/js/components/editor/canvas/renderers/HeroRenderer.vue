<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        align?: 'left' | 'center' | 'right';
        background?: 'transparent' | 'muted' | 'primary' | 'dark';
        ctaText?: string;
        ctaUrl?: string;
    }>(),
    {
        title: 'Hero Headline',
        subtitle: '',
        align: 'center',
        background: 'transparent',
        ctaText: '',
        ctaUrl: '',
    },
);

const alignClass = computed(() => {
    return props.align === 'left'
        ? 'text-left items-start'
        : props.align === 'right'
          ? 'text-right items-end'
          : 'text-center items-center';
});

const titleForegroundClass = computed(() =>
    props.background === 'dark' ? 'text-zinc-100' : 'text-foreground',
);

const subtitleForegroundClass = computed(() => {
    if (props.background === 'dark') return 'text-zinc-200';
    return props.background === 'transparent'
        ? 'text-muted-foreground'
        : 'text-foreground';
});
</script>

<template>
    <div
        :class="[
            'flex w-full flex-col justify-center px-4 py-12 sm:px-6 sm:py-20',
            alignClass,
        ]"
    >
        <h1
            :class="[
                'max-w-3xl text-3xl leading-tight font-extrabold tracking-tight sm:text-5xl',
                titleForegroundClass,
            ]"
            style="font-size: var(--pb-hero-title-size)"
        >
            {{ title || 'Hero Headline' }}
        </h1>
        <p
            v-if="subtitle"
            :class="[
                'mt-4 max-w-2xl text-base leading-relaxed sm:text-lg',
                subtitleForegroundClass,
            ]"
            style="font-size: var(--pb-hero-subtitle-size)"
        >
            {{ subtitle }}
        </p>

        <div v-if="ctaText" class="mt-6">
            <a
                :href="ctaUrl || '#'"
                class="inline-flex h-10 cursor-pointer items-center justify-center rounded-md bg-primary px-6 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
                @click.prevent
            >
                {{ ctaText }}
            </a>
        </div>

        <!-- Slot for nested child components -->
        <div class="mt-6 w-full">
            <slot />
        </div>
    </div>
</template>
