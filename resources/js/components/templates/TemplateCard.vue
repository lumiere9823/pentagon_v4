<script setup lang="ts">
import { computed } from 'vue';
import { Check, Globe, Layout, Sparkles } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

export interface TemplateSummary {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    category: string;
    thumbnail_path?: string | null;
    website_id: number | null;
}

const props = defineProps<{
    template: TemplateSummary;
    selected: boolean;
}>();

const emit = defineEmits<{
    (e: 'select', template: TemplateSummary): void;
}>();

const categoryColor = computed(() => {
    switch (props.template.category) {
        case 'landing':
            return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800';
        case 'portfolio':
            return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-200 dark:border-purple-800';
        case 'lead_gen':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800';
        case 'blank':
            return 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800';
        default:
            return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800';
    }
});

const isGlobal = computed(() => props.template.website_id === null);
</script>

<template>
    <div
        role="radio"
        :aria-checked="selected"
        tabindex="0"
        class="group relative flex cursor-pointer flex-col justify-between rounded-xl border p-4 text-left transition-all duration-150 focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
        :class="[
            selected
                ? 'border-primary bg-primary/5 shadow-sm ring-1 ring-primary'
                : 'border-border bg-card hover:border-muted-foreground/40 hover:shadow-xs',
        ]"
        @click="emit('select', template)"
        @keydown.enter.prevent="emit('select', template)"
        @keydown.space.prevent="emit('select', template)"
    >
        <!-- Top Status & Badges -->
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-wrap items-center gap-1.5">
                <Badge
                    variant="outline"
                    class="px-1.5 py-0.5 text-[10px] font-medium tracking-wider uppercase"
                    :class="categoryColor"
                >
                    {{ template.category.replace('_', ' ') }}
                </Badge>

                <Badge
                    v-if="isGlobal"
                    variant="secondary"
                    class="gap-1 px-1.5 py-0.5 text-[10px] font-normal text-muted-foreground"
                >
                    <Globe class="h-2.5 w-2.5" />
                    Starter
                </Badge>
                <Badge
                    v-else
                    variant="outline"
                    class="gap-1 border-primary/30 px-1.5 py-0.5 text-[10px] font-normal text-primary"
                >
                    <Sparkles class="h-2.5 w-2.5" />
                    Custom
                </Badge>
            </div>

            <!-- Radio Selection Indicator -->
            <div
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors"
                :class="[
                    selected
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-muted-foreground/30 group-hover:border-muted-foreground',
                ]"
            >
                <Check v-if="selected" class="h-3 w-3 stroke-[3]" />
            </div>
        </div>

        <!-- Preview Icon / Mock Canvas -->
        <div
            class="my-3 flex h-20 items-center justify-center rounded-lg border border-dashed bg-muted/40 transition-colors group-hover:bg-muted/70"
        >
            <Layout
                class="h-8 w-8 text-muted-foreground/60 transition-transform group-hover:scale-105"
            />
        </div>

        <!-- Title & Description -->
        <div>
            <h3
                class="text-sm font-semibold tracking-tight text-foreground transition-colors group-hover:text-primary"
            >
                {{ template.name }}
            </h3>
            <p
                class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted-foreground"
            >
                {{ template.description || 'No description provided.' }}
            </p>
        </div>
    </div>
</template>
