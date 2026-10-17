<script setup lang="ts">
import { Monitor, Smartphone, Tablet } from '@lucide/vue';
import { VIEWPORT_PRESETS } from '@/config/editor';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ViewportMode } from '@/types/document';

const props = defineProps<{
    modelValue: ViewportMode;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: ViewportMode): void;
}>();

const presets = [
    { id: 'desktop' as const, label: 'Desktop', icon: Monitor },
    { id: 'tablet' as const, label: 'Tablet', icon: Tablet },
    { id: 'mobile' as const, label: 'Mobile', icon: Smartphone },
];

const selectViewport = (mode: ViewportMode) => {
    emit('update:modelValue', mode);
};
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <div
            role="radiogroup"
            aria-label="Select canvas viewport"
            class="inline-flex items-center rounded-lg border bg-muted/60 p-0.5 shadow-xs"
        >
            <Tooltip v-for="preset in presets" :key="preset.id">
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        role="radio"
                        :aria-checked="modelValue === preset.id"
                        :aria-label="`${preset.label} viewport`"
                        class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            modelValue === preset.id
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="selectViewport(preset.id)"
                    >
                        <component :is="preset.icon" class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">{{ preset.label }}</span>
                    </button>
                </TooltipTrigger>
                <TooltipContent side="bottom" :side-offset="6">
                    <p class="text-xs">
                        {{ VIEWPORT_PRESETS[preset.id].description }}
                    </p>
                </TooltipContent>
            </Tooltip>
        </div>
    </TooltipProvider>
</template>
