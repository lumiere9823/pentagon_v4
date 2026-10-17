<script setup lang="ts">
import { computed } from 'vue';
import {
    Code2,
    Layers,
    LayoutTemplate,
    Monitor,
    Smartphone,
    Tablet,
} from '@lucide/vue';
import { VIEWPORT_PRESETS } from '@/config/editor';
import { Badge } from '@/components/ui/badge';
import type { ViewportMode } from '@/types/document';

const props = defineProps<{
    viewport: ViewportMode;
    pageTitle: string;
}>();

const currentPreset = computed(() => {
    return VIEWPORT_PRESETS[props.viewport];
});

const viewportIcon = computed(() => {
    switch (props.viewport) {
        case 'tablet':
            return Tablet;
        case 'mobile':
            return Smartphone;
        default:
            return Monitor;
    }
});
</script>

<template>
    <main
        class="relative flex flex-1 flex-col overflow-auto bg-muted/40 p-4 sm:p-6"
        aria-label="Editor Canvas Surface"
    >
        <!-- Canvas Dimension & Viewport Indicator -->
        <div
            class="mb-3 flex items-center justify-between text-xs text-muted-foreground"
        >
            <div class="flex items-center gap-2">
                <component :is="viewportIcon" class="h-3.5 w-3.5" />
                <span class="font-medium text-foreground">
                    {{ currentPreset.label }} Viewport
                </span>
                <span class="text-[11px]">
                    ({{ currentPreset.width }}px simulated width)
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Badge variant="outline" class="bg-background text-[10px]">
                    Document Schema v1
                </Badge>
            </div>
        </div>

        <!-- Center Canvas Frame Surface -->
        <div class="flex flex-1 items-start justify-center pb-12">
            <div
                class="flex min-h-[640px] flex-col rounded-xl border border-border/80 bg-background shadow-sm transition-all duration-300"
                :class="currentPreset.widthCss"
            >
                <!-- Canvas Top Bar (Simulated Browser or Page Title Bar) -->
                <div
                    class="flex h-10 items-center justify-between border-b bg-muted/20 px-4 text-xs text-muted-foreground"
                >
                    <div class="flex items-center gap-1.5">
                        <div class="h-2.5 w-2.5 rounded-full bg-border" />
                        <div class="h-2.5 w-2.5 rounded-full bg-border" />
                        <div class="h-2.5 w-2.5 rounded-full bg-border" />
                    </div>
                    <div
                        class="truncate font-mono text-[11px] text-muted-foreground"
                    >
                        {{ pageTitle }}
                    </div>
                    <div
                        class="text-[10px] tracking-wider text-muted-foreground/60 uppercase"
                    >
                        Canvas Shell
                    </div>
                </div>

                <!-- Canvas Body: Phase 04 Structured Placeholder -->
                <div
                    class="flex flex-1 flex-col items-center justify-center p-8 text-center"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary shadow-xs"
                    >
                        <LayoutTemplate class="h-7 w-7" />
                    </div>

                    <h3 class="mt-4 text-base font-semibold text-foreground">
                        Editor Canvas Frame Ready
                    </h3>

                    <p
                        class="mt-1.5 max-w-md text-xs leading-relaxed text-muted-foreground"
                    >
                        This center surface provides the responsive viewport
                        container for
                        <span class="font-medium text-foreground">{{
                            currentPreset.label
                        }}</span>
                        layout mode. Visual drag-and-drop, interactive node
                        rendering, and selection will be integrated in Phase 05.
                    </p>

                    <!-- Technical Specs Card -->
                    <div
                        class="mt-6 grid w-full max-w-md grid-cols-2 gap-3 text-left"
                    >
                        <div class="rounded-lg border bg-muted/20 p-3">
                            <div
                                class="flex items-center gap-1.5 text-xs font-medium text-foreground"
                            >
                                <Layers class="h-3.5 w-3.5 text-primary" />
                                <span>Component Registry</span>
                            </div>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                12 Core components active and verified by server
                                validator.
                            </p>
                        </div>

                        <div class="rounded-lg border bg-muted/20 p-3">
                            <div
                                class="flex items-center gap-1.5 text-xs font-medium text-foreground"
                            >
                                <Code2 class="h-3.5 w-3.5 text-primary" />
                                <span>Tree Architecture</span>
                            </div>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Max 10 depth, 250 nodes budget protected against
                                overflow.
                            </p>
                        </div>
                    </div>

                    <!-- Next Step Indicator -->
                    <div
                        class="mt-6 flex items-center gap-2 rounded-full border border-dashed border-primary/40 bg-primary/5 px-3 py-1 text-[11px] text-primary"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary"
                        />
                        <span
                            >Phase 05: Visual Editing Core will mount the live
                            renderer here</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
