<script setup lang="ts">
import { computed } from 'vue';
import { sanitizeHtml } from '@/lib/domSanitizer';

const props = withDefaults(
    defineProps<{
        content?: string;
        align?: 'left' | 'center' | 'right';
        blockSpacing?: 'none' | 'small' | 'medium' | 'large';
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
        fontSize?: 'semantic' | 'small' | 'medium' | 'large' | 'xlarge';
        textColor?: 'foreground' | 'soft' | 'muted' | 'primary';
    }>(),
    {
        content: '<p>Start typing rich text here...</p>',
        align: 'left',
        blockSpacing: 'small',
        maxWidth: 'full',
        fontSize: 'semantic',
        textColor: 'soft',
    },
);

const sanitizedContent = computed(() => {
    return sanitizeHtml(props.content || '<p></p>');
});
</script>

<template>
    <div
        class="prose dark:prose-invert my-2 max-w-none leading-relaxed text-foreground/90"
        v-html="sanitizedContent"
    />
</template>
