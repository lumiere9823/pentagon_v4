<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDown } from '@lucide/vue';
import { sanitizeHtml } from '@/lib/domSanitizer';

interface FaqItem {
    question: string;
    answer: string;
}

const props = withDefaults(
    defineProps<{
        items?: FaqItem[];
    }>(),
    {
        items: () => [],
    },
);

const openIndex = ref<number | null>(0);

const toggleItem = (idx: number) => {
    openIndex.value = openIndex.value === idx ? null : idx;
};
</script>

<template>
    <div class="my-6 w-full divide-y divide-border rounded-lg border bg-card">
        <div
            v-if="!items || items.length === 0"
            class="p-6 text-center text-xs text-muted-foreground"
        >
            No FAQ items configured. Add questions and answers in the Inspector.
        </div>
        <div v-for="(item, idx) in items" :key="idx" class="transition-colors">
            <button
                type="button"
                class="flex w-full items-center justify-between p-4 text-left text-sm font-medium text-foreground hover:bg-muted/30 focus-visible:outline-none"
                @click.stop="toggleItem(idx)"
            >
                <span>{{ item.question || 'Untitled Question' }}</span>
                <ChevronDown
                    class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200"
                    :class="
                        openIndex === idx ? 'rotate-180 text-foreground' : ''
                    "
                />
            </button>
            <div
                v-show="openIndex === idx"
                class="prose dark:prose-invert max-w-none px-4 pt-1 pb-4 text-xs leading-relaxed text-muted-foreground"
                v-html="sanitizeHtml(item.answer || '')"
            />
        </div>
    </div>
</template>
