<script setup lang="ts">
import { computed, onErrorCaptured, ref } from 'vue';
import { AlertCircle } from '@lucide/vue';
import DropZone from './DropZone.vue';
import NodeToolbar from './NodeToolbar.vue';
import ButtonRenderer from './renderers/ButtonRenderer.vue';
import CardGridRenderer from './renderers/CardGridRenderer.vue';
import ColumnsRenderer from './renderers/ColumnsRenderer.vue';
import ContainerRenderer from './renderers/ContainerRenderer.vue';
import DividerRenderer from './renderers/DividerRenderer.vue';
import FAQRenderer from './renderers/FAQRenderer.vue';
import HeadingRenderer from './renderers/HeadingRenderer.vue';
import HeroRenderer from './renderers/HeroRenderer.vue';
import ImageRenderer from './renderers/ImageRenderer.vue';
import RichTextRenderer from './renderers/RichTextRenderer.vue';
import SectionRenderer from './renderers/SectionRenderer.vue';
import SpacerRenderer from './renderers/SpacerRenderer.vue';
import { resolveAllResponsiveProps } from '@/lib/responsive';
import {
    CONTAINER_COMPONENT_TYPES,
    type CoreComponentType,
    type DocumentNode,
    type ViewportMode,
} from '@/types/document';

const props = withDefaults(
    defineProps<{
        node: DocumentNode;
        parent?: DocumentNode | null;
        index?: number;
        siblingCount?: number;
        selectedId?: string | null;
        hoveredId?: string | null;
        readOnly?: boolean;
        depth?: number;
        viewport?: ViewportMode;
    }>(),
    {
        parent: null,
        index: 0,
        siblingCount: 1,
        selectedId: null,
        hoveredId: null,
        readOnly: false,
        depth: 1,
        viewport: 'desktop',
    },
);

const emit = defineEmits<{
    (e: 'select', id: string): void;
    (e: 'hover', id: string | null): void;
    (e: 'moveUp', id: string): void;
    (e: 'moveDown', id: string): void;
    (e: 'duplicate', id: string): void;
    (e: 'delete', id: string): void;
    (
        e: 'dropComponent',
        payload: { type: CoreComponentType; parentId: string; index: number },
    ): void;
}>();

const error = ref<string | null>(null);

onErrorCaptured((err) => {
    error.value = err instanceof Error ? err.message : 'Render error';
    return false; // prevent error bubbling
});

const isSelected = computed(() => props.selectedId === props.node.id);
const isHovered = computed(
    () => props.hoveredId === props.node.id && !isSelected.value,
);
const isRoot = computed(() => props.parent === null);
const isContainer = computed(() =>
    CONTAINER_COMPONENT_TYPES.has(props.node.type),
);

const canMoveUp = computed(() => props.index > 0);
const canMoveDown = computed(() => props.index < props.siblingCount - 1);

// Map type to concrete Vue component
const rendererMap: Record<CoreComponentType, any> = {
    Section: SectionRenderer,
    Container: ContainerRenderer,
    Heading: HeadingRenderer,
    RichText: RichTextRenderer,
    Image: ImageRenderer,
    Button: ButtonRenderer,
    Hero: HeroRenderer,
    Columns: ColumnsRenderer,
    CardGrid: CardGridRenderer,
    FAQ: FAQRenderer,
    Spacer: SpacerRenderer,
    Divider: DividerRenderer,
};

const concreteRenderer = computed(() => rendererMap[props.node.type] || null);

const effectiveProps = computed(() => {
    return resolveAllResponsiveProps(props.node, props.viewport);
});

const isHiddenOnActiveViewport = computed(() => {
    return effectiveProps.value.display === 'hidden';
});

const componentProps = computed(() => {
    const { display: _, ...rest } = effectiveProps.value;
    return rest;
});

const handleNodeClick = (e: MouseEvent) => {
    e.stopPropagation();
    emit('select', props.node.id);
};

const handleMouseEnter = (e: MouseEvent) => {
    e.stopPropagation();
    emit('hover', props.node.id);
};

const handleMouseLeave = (e: MouseEvent) => {
    e.stopPropagation();
    emit('hover', null);
};
</script>

<template>
    <div
        :data-node-id="node.id"
        :data-node-type="node.type"
        :class="[
            'relative transition-all duration-150',
            isSelected
                ? isHiddenOnActiveViewport
                    ? 'ring-dashed opacity-75 ring-2 ring-amber-500'
                    : 'ring-2 ring-primary ring-offset-2 ring-offset-background'
                : isHovered
                  ? 'ring-1 ring-primary/40'
                  : '',
            isHiddenOnActiveViewport && !isSelected
                ? 'border border-dashed border-muted-foreground/40 opacity-40'
                : '',
            !readOnly ? 'cursor-pointer' : '',
        ]"
        @click="handleNodeClick"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <!-- Hidden on Viewport Badge -->
        <div
            v-if="isHiddenOnActiveViewport"
            class="pointer-events-none absolute top-1 right-1 z-20 flex items-center gap-1 rounded bg-amber-500/90 px-1.5 py-0.5 text-[9px] font-medium tracking-wide text-white uppercase shadow-xs"
        >
            Hidden on {{ viewport }}
        </div>

        <!-- Floating Action Overlay Toolbar for Selected Node -->
        <NodeToolbar
            v-if="isSelected && !readOnly"
            :component-type="node.type"
            :is-root="isRoot"
            :can-move-up="canMoveUp"
            :can-move-down="canMoveDown"
            @move-up="emit('moveUp', node.id)"
            @move-down="emit('moveDown', node.id)"
            @duplicate="emit('duplicate', node.id)"
            @delete="emit('delete', node.id)"
        />

        <!-- Error Fallback Box if renderer throws -->
        <div
            v-if="error"
            class="my-2 flex items-center gap-2 rounded border border-destructive/50 bg-destructive/10 p-3 text-xs text-destructive"
        >
            <AlertCircle class="h-4 w-4" />
            <span>Error rendering {{ node.type }}: {{ error }}</span>
        </div>

        <!-- Render Target Component -->
        <component
            :is="concreteRenderer"
            v-else-if="concreteRenderer"
            v-bind="componentProps"
        >
            <!-- Children Slot for Container Components -->
            <template v-if="isContainer">
                <!-- DropZone before first child -->
                <DropZone
                    v-if="
                        !readOnly && node.children && node.children.length > 0
                    "
                    :parent-id="node.id"
                    :insert-index="0"
                    :read-only="readOnly"
                    @drop-component="emit('dropComponent', $event)"
                />

                <!-- Empty Container Placeholder / Drop Zone -->
                <DropZone
                    v-if="!node.children || node.children.length === 0"
                    :parent-id="node.id"
                    :insert-index="0"
                    :read-only="readOnly"
                    :empty-container="true"
                    @drop-component="emit('dropComponent', $event)"
                />

                <!-- Recursive Children Rendering -->
                <template
                    v-for="(child, childIdx) in node.children || []"
                    :key="child.id"
                >
                    <NodeRenderer
                        :node="child"
                        :parent="node"
                        :index="childIdx"
                        :sibling-count="node.children?.length || 0"
                        :selected-id="selectedId"
                        :hovered-id="hoveredId"
                        :read-only="readOnly"
                        :depth="depth + 1"
                        :viewport="viewport"
                        @select="emit('select', $event)"
                        @hover="emit('hover', $event)"
                        @move-up="emit('moveUp', $event)"
                        @move-down="emit('moveDown', $event)"
                        @duplicate="emit('duplicate', $event)"
                        @delete="emit('delete', $event)"
                        @drop-component="emit('dropComponent', $event)"
                    />

                    <!-- DropZone after each child -->
                    <DropZone
                        v-if="!readOnly"
                        :parent-id="node.id"
                        :insert-index="childIdx + 1"
                        :read-only="readOnly"
                        @drop-component="emit('dropComponent', $event)"
                    />
                </template>
            </template>
        </component>

        <!-- Fallback if component type is unmapped -->
        <div
            v-else
            class="rounded border border-amber-500/50 bg-amber-500/10 p-3 text-xs text-amber-700"
        >
            Unknown component: {{ node.type }}
        </div>
    </div>
</template>
