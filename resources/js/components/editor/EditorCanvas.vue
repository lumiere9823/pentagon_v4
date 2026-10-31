<script setup lang="ts">
import { computed, ref } from 'vue';
import { Monitor, Smartphone, Tablet } from '@lucide/vue';
import NodeRenderer from './canvas/NodeRenderer.vue';
import { VIEWPORT_PRESETS } from '@/config/editor';
import { Badge } from '@/components/ui/badge';
import type {
    CoreComponentType,
    DocumentSchema,
    ViewportMode,
} from '@/types/document';

const props = withDefaults(
    defineProps<{
        document: DocumentSchema;
        viewport: ViewportMode;
        pageTitle: string;
        selectedId?: string | null;
        hoveredId?: string | null;
        readOnly?: boolean;
    }>(),
    {
        selectedId: null,
        hoveredId: null,
        readOnly: false,
    },
);

const emit = defineEmits<{
    (e: 'select', id: string): void;
    (e: 'hover', id: string | null): void;
    (e: 'clearSelection'): void;
    (e: 'moveUp', id: string): void;
    (e: 'moveDown', id: string): void;
    (e: 'duplicate', id: string): void;
    (e: 'delete', id: string): void;
    (
        e: 'dropComponent',
        payload: { type: CoreComponentType; parentId: string; index: number },
    ): void;
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

const handleCanvasBackgroundClick = (e: MouseEvent) => {
    // If user clicks on background (not a child node), deselect
    if (e.target === e.currentTarget) {
        emit('clearSelection');
    }
};

const canvasContainerRef = ref<HTMLElement | null>(null);

function scrollToNode(nodeId: string) {
    if (!canvasContainerRef.value) return;
    const el = canvasContainerRef.value.querySelector(
        `[data-node-id="${nodeId}"]`,
    ) as HTMLElement | null;
    if (!el) return;

    const container = canvasContainerRef.value;
    const containerRect = container.getBoundingClientRect();
    const elRect = el.getBoundingClientRect();

    const isAbove = elRect.top < containerRect.top;
    const isBelow = elRect.bottom > containerRect.bottom;

    if (isAbove || isBelow) {
        const prefersReducedMotion =
            typeof window !== 'undefined' &&
            window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

        el.scrollIntoView({
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
            block: 'nearest',
            inline: 'nearest',
        });
    }
}

defineExpose({
    scrollToNode,
});
</script>

<template>
    <main
        ref="canvasContainerRef"
        tabindex="0"
        class="relative flex flex-1 flex-col overflow-auto bg-muted/40 p-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden sm:p-6"
        aria-label="Editor Canvas Surface"
        @click="handleCanvasBackgroundClick"
    >
        <!-- Canvas Dimension & Viewport Indicator -->
        <div
            class="mb-3 flex items-center justify-between text-xs text-muted-foreground select-none"
        >
            <div class="flex items-center gap-2">
                <component :is="viewportIcon" class="h-3.5 w-3.5" />
                <span class="font-medium text-foreground">
                    {{ currentPreset.label }} Viewport
                </span>
                <span class="text-[11px]">
                    ({{ currentPreset.width }}px simulated canvas)
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Badge variant="outline" class="bg-background text-[10px]">
                    Schema v{{ document.schemaVersion }}
                </Badge>
            </div>
        </div>

        <!-- Center Canvas Frame Surface -->
        <div
            class="flex flex-1 items-start justify-center pb-24"
            @click="handleCanvasBackgroundClick"
        >
            <div
                class="flex min-h-[640px] flex-col rounded-xl border border-border/80 bg-background shadow-sm transition-all duration-300"
                :class="currentPreset.widthCss"
            >
                <!-- Canvas Top Bar (Simulated Browser or Page Title Bar) -->
                <div
                    class="flex h-10 items-center justify-between border-b bg-muted/20 px-4 text-xs text-muted-foreground select-none"
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
                        class="text-[10px] tracking-wider text-muted-foreground uppercase"
                    >
                        Live Preview
                    </div>
                </div>

                <!-- Canvas Active Document Body -->
                <div class="flex-1 p-2 sm:p-4">
                    <NodeRenderer
                        :node="document.root"
                        :selected-id="selectedId"
                        :hovered-id="hoveredId"
                        :read-only="readOnly"
                        :viewport="viewport"
                        @select="emit('select', $event)"
                        @hover="emit('hover', $event)"
                        @move-up="emit('moveUp', $event)"
                        @move-down="emit('moveDown', $event)"
                        @duplicate="emit('duplicate', $event)"
                        @delete="emit('delete', $event)"
                        @drop-component="emit('dropComponent', $event)"
                    />
                </div>
            </div>
        </div>
    </main>
</template>
