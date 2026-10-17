<script setup lang="ts">
import { computed } from 'vue';
import { sanitizeHtml } from '@/lib/domSanitizer';

const props = withDefaults(
    defineProps<{
        content?: string;
    }>(),
    {
        content: '<p>Start typing rich text here...</p>',
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
