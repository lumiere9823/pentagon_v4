<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import {
    ChevronDown,
    ChevronRight,
    Eye,
    EyeOff,
    GripVertical,
    Lock,
    Unlock,
} from '@lucide/vue';
import { getComponentIcon } from '@/lib/componentIcons';
import type { DropPosition } from '@/lib/dropIndex';
import {
    CONTAINER_COMPONENT_TYPES,
    type ComponentMetadata,
    type DocumentNode,
    type ViewportMode,
} from '@/types/document';

defineOptions({
    name: 'LayerTreeNode',
});

const props = withDefaults(
    defineProps<{
        node: DocumentNode;
        depth?: number;
        selectedId?: string | null;
        hoveredId?: string | null;
        focusedId?: string | null;
        expandedIds: Set<string>;
        components?: Record<string, ComponentMetadata>;
        readOnly?: boolean;
        viewport?: ViewportMode;
        searchFilter?: Set<string> | null;
        directMatchIds?: Set<string>;
        draggedId?: string | null;
        dropTarget?: {
            nodeId: string;
            position: DropPosition;
            isValid: boolean;
        } | null;
        parentLocked?: boolean;
        lockedAncestorLabel?: string | null;
        parentHidden?: boolean;
        hiddenAncestorLabel?: string | null;
    }>(),
    {
        depth: 0,
        selectedId: null,
        hoveredId: null,
        focusedId: null,
        components: () => ({}),
        readOnly: false,
        viewport: 'desktop',
        searchFilter: null,
        directMatchIds: () => new Set(),
        draggedId: null,
        dropTarget: null,
        parentLocked: false,
        lockedAncestorLabel: null,
        parentHidden: false,
        hiddenAncestorLabel: null,
    },
);

const emit = defineEmits<{
    (e: 'select', id: string): void;
    (e: 'hover', id: string | null): void;
    (e: 'focus', id: string): void;
    (e: 'toggleExpand', id: string): void;
    (e: 'setNodeLocked', payload: { nodeId: string; locked: boolean }): void;
    (e: 'setNodeHidden', payload: { nodeId: string; hidden: boolean }): void;
    (
        e: 'setNodeLabel',
        payload: { nodeId: string; label: string | null },
    ): void;
    (e: 'dragStart', nodeId: string, event: DragEvent): void;
    (e: 'dragEnd', nodeId: string, event: DragEvent): void;
    (
        e: 'dragOver',
        nodeId: string,
        position: DropPosition,
        event: DragEvent,
    ): void;
    (e: 'dragLeave', nodeId: string, event: DragEvent): void;
    (e: 'drop', nodeId: string, event: DragEvent): void;
}>();

const isContainer = computed(() =>
    CONTAINER_COMPONENT_TYPES.has(props.node.type),
);

const childrenList = computed(() => {
    return Array.isArray(props.node.children) ? props.node.children : [];
});

const hasChildren = computed(() => childrenList.value.length > 0);

const isExpanded = computed(() => props.expandedIds.has(props.node.id));

const isSelected = computed(() => props.selectedId === props.node.id);

const isHovered = computed(() => props.hoveredId === props.node.id);

const isFocused = computed(() => props.focusedId === props.node.id);

const isDirectMatch = computed(
    () => props.directMatchIds?.has(props.node.id) ?? false,
);

const isHiddenOnActiveViewport = computed(() => {
    if (!props.viewport || props.viewport === 'desktop') return false;
    return props.node.styles?.[props.viewport]?.display === 'hidden';
});

const componentIcon = computed(() => getComponentIcon(props.node.type));

const componentLabel = computed(() => {
    return props.components?.[props.node.type]?.label || props.node.type;
});

const hasCustomLabel = computed(() =>
    Boolean(props.node.label && props.node.label.trim().length > 0),
);

const displayLabel = computed(() => {
    if (hasCustomLabel.value) {
        return props.node.label!.trim();
    }
    return componentLabel.value;
});

const textPreview = computed(() => {
    const p = props.node.props;
    if (!p || typeof p !== 'object') return null;

    if (typeof p.title === 'string' && p.title.trim()) {
        return p.title.trim();
    }
    if (typeof p.text === 'string' && p.text.trim()) {
        return p.text.trim();
    }
    if (typeof p.label === 'string' && p.label.trim()) {
        return p.label.trim();
    }
    if (typeof p.alt === 'string' && p.alt.trim()) {
        return p.alt.trim();
    }
    if (typeof p.caption === 'string' && p.caption.trim()) {
        return p.caption.trim();
    }
    if (Array.isArray(p.items)) {
        return `${p.items.length} item${p.items.length === 1 ? '' : 's'}`;
    }
    return null;
});

const accessibleLabel = computed(() => {
    const base = hasCustomLabel.value
        ? `${displayLabel.value} (${componentLabel.value})`
        : componentLabel.value;
    if (textPreview.value) {
        return `${base}: ${textPreview.value}`;
    }
    return base;
});

const isRoot = computed(() => props.depth === 0);
const isDirectLocked = computed(() => Boolean(props.node.locked));
const isInheritedLocked = computed(() => Boolean(props.parentLocked));
const isEffectiveLocked = computed(
    () => isDirectLocked.value || isInheritedLocked.value,
);
const effectiveProtectingLabel = computed(() => {
    if (isDirectLocked.value) {
        return displayLabel.value;
    }
    return props.lockedAncestorLabel ?? displayLabel.value;
});

const isDirectHidden = computed(() => Boolean(props.node.hidden));
const isInheritedHidden = computed(() => Boolean(props.parentHidden));
const isGloballyHidden = computed(
    () => isDirectHidden.value || isInheritedHidden.value,
);
const effectiveHidingLabel = computed(() => {
    if (isDirectHidden.value) {
        return displayLabel.value;
    }
    return props.hiddenAncestorLabel ?? displayLabel.value;
});

const isRenaming = ref(false);
const renamingText = ref('');
const renameInputRef = ref<HTMLInputElement | null>(null);

const startRenaming = () => {
    if (props.readOnly || isEffectiveLocked.value) return;
    renamingText.value = props.node.label ?? '';
    isRenaming.value = true;
    nextTick(() => {
        renameInputRef.value?.focus();
        renameInputRef.value?.select();
    });
};

const commitRenaming = () => {
    if (!isRenaming.value) return;
    isRenaming.value = false;
    const trimmed = renamingText.value.trim();
    const finalLabel = trimmed.length > 0 ? trimmed.slice(0, 64) : null;
    const currentLabel = props.node.label?.trim() || null;
    if (finalLabel !== currentLabel) {
        emit('setNodeLabel', {
            nodeId: props.node.id,
            label: finalLabel,
        });
    }
};

const cancelRenaming = () => {
    if (!isRenaming.value) return;
    isRenaming.value = false;
    renamingText.value = '';
};

const isBeingDragged = computed(() => props.draggedId === props.node.id);
const isDropTarget = computed(() => props.dropTarget?.nodeId === props.node.id);
const dropTargetPosition = computed(() =>
    isDropTarget.value ? props.dropTarget!.position : null,
);
const isDropTargetValid = computed(() =>
    isDropTarget.value ? props.dropTarget!.isValid : false,
);

const handleRowClick = () => {
    emit('focus', props.node.id);
    emit('select', props.node.id);
};

const handleChevronClick = (e: MouseEvent) => {
    e.stopPropagation();
    emit('toggleExpand', props.node.id);
};

const handleToggleLock = () => {
    if (props.readOnly || isInheritedLocked.value) return;
    emit('setNodeLocked', {
        nodeId: props.node.id,
        locked: !isDirectLocked.value,
    });
};

const handleToggleHidden = () => {
    if (
        props.readOnly ||
        isRoot.value ||
        isInheritedHidden.value ||
        isEffectiveLocked.value
    ) {
        return;
    }
    emit('setNodeHidden', {
        nodeId: props.node.id,
        hidden: !isDirectHidden.value,
    });
};

const handleDragStart = (e: DragEvent) => {
    if (props.readOnly || isRoot.value || isEffectiveLocked.value) {
        e.preventDefault();
        return;
    }
    if (e.dataTransfer) {
        e.dataTransfer.setData(
            'application/x-pentagon-layer-id',
            props.node.id,
        );
        e.dataTransfer.setData('text/plain', props.node.id);
        e.dataTransfer.effectAllowed = 'move';
    }
    emit('dragStart', props.node.id, e);
};

const handleDragEnd = (e: DragEvent) => {
    emit('dragEnd', props.node.id, e);
};

const handleDragOver = (e: DragEvent) => {
    if (props.readOnly) return;
    const el = e.currentTarget as HTMLElement | null;
    if (!el) return;

    const rect = el.getBoundingClientRect();
    const offsetY = e.clientY - rect.top;
    const height = rect.height;

    let position: DropPosition;
    if (isContainer.value) {
        if (isRoot.value) {
            position = 'inside';
        } else if (offsetY < height * 0.25) {
            position = 'before';
        } else if (offsetY > height * 0.75) {
            position = 'after';
        } else {
            position = 'inside';
        }
    } else {
        if (offsetY < height * 0.5) {
            position = 'before';
        } else {
            position = 'after';
        }
    }

    emit('dragOver', props.node.id, position, e);
};

const handleDragLeave = (e: DragEvent) => {
    const related = e.relatedTarget as Node | null;
    const current = e.currentTarget as Node | null;
    if (current && related && current.contains(related)) {
        return;
    }
    emit('dragLeave', props.node.id, e);
};

const handleDrop = (e: DragEvent) => {
    emit('drop', props.node.id, e);
};

const handleEmptyContainerDragOver = (e: DragEvent) => {
    if (props.readOnly) return;
    emit('dragOver', props.node.id, 'inside', e);
};

const handleEmptyContainerDrop = (e: DragEvent) => {
    emit('drop', props.node.id, e);
};
</script>

<template>
    <div class="flex flex-col select-none">
        <!-- Layer Row -->
        <div
            :data-node-id="node.id"
            :data-component-type="node.type"
            :class="[
                'group relative flex h-8 cursor-pointer items-center gap-1.5 rounded-md px-1.5 text-xs transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 focus-visible:ring-offset-background',
                isSelected
                    ? 'bg-primary/10 font-medium text-primary ring-1 ring-primary/40'
                    : isHovered
                      ? 'bg-accent/70 text-foreground'
                      : 'text-foreground/80 hover:bg-accent/40 hover:text-foreground',
                isHiddenOnActiveViewport || isGloballyHidden
                    ? 'opacity-50'
                    : '',
                isDropTarget && dropTargetPosition === 'inside'
                    ? isDropTargetValid
                        ? 'bg-primary/20 font-medium ring-2 ring-primary ring-inset'
                        : 'cursor-not-allowed bg-destructive/15 ring-2 ring-destructive ring-inset'
                    : '',
                isBeingDragged
                    ? 'border border-dashed border-primary/50 opacity-40'
                    : '',
            ]"
            :style="{ paddingLeft: `${depth * 14 + 6}px` }"
            role="treeitem"
            :tabindex="isFocused ? 0 : -1"
            :aria-selected="isSelected"
            :aria-expanded="isContainer ? isExpanded : undefined"
            :aria-level="depth + 1"
            :aria-label="accessibleLabel"
            @click="handleRowClick"
            @focus="emit('focus', node.id)"
            @keydown.f2.prevent.stop="startRenaming"
            @mouseenter="emit('hover', node.id)"
            @mouseleave="emit('hover', null)"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
        >
            <!-- Drop Indicator Line Before -->
            <div
                v-if="isDropTarget && dropTargetPosition === 'before'"
                data-testid="drop-indicator-before"
                class="pointer-events-none absolute -top-0.5 right-0 left-0 z-20 h-0.5 rounded-full"
                :class="
                    isDropTargetValid
                        ? 'bg-primary shadow-xs'
                        : 'bg-destructive'
                "
            />

            <!-- Drop Indicator Line After -->
            <div
                v-if="isDropTarget && dropTargetPosition === 'after'"
                data-testid="drop-indicator-after"
                class="pointer-events-none absolute right-0 -bottom-0.5 left-0 z-20 h-0.5 rounded-full"
                :class="
                    isDropTargetValid
                        ? 'bg-primary shadow-xs'
                        : 'bg-destructive'
                "
            />

            <!-- Drag Handle for non-root editable nodes -->
            <div
                v-if="!readOnly && !isRoot && !isEffectiveLocked"
                role="button"
                tabindex="-1"
                class="flex h-4 w-3.5 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground/40 transition-colors hover:bg-accent hover:text-foreground active:cursor-grabbing"
                draggable="true"
                aria-label="Drag to reorder"
                data-testid="drag-handle"
                @dragstart.stop="handleDragStart"
                @dragend.stop="handleDragEnd"
            >
                <GripVertical class="h-3 w-3" />
            </div>
            <div
                v-else-if="!isRoot"
                class="h-4 w-3.5 shrink-0"
                aria-hidden="true"
            />

            <!-- Expand/Collapse Chevron for Container Components -->
            <button
                v-if="isContainer"
                type="button"
                tabindex="-1"
                class="flex h-4 w-4 shrink-0 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                :title="
                    isExpanded
                        ? `Collapse ${componentLabel}`
                        : `Expand ${componentLabel}`
                "
                :aria-label="
                    isExpanded
                        ? `Collapse ${componentLabel}`
                        : `Expand ${componentLabel}`
                "
                @click="handleChevronClick"
            >
                <ChevronDown v-if="isExpanded" class="h-3.5 w-3.5" />
                <ChevronRight v-else class="h-3.5 w-3.5" />
            </button>
            <div v-else class="h-4 w-4 shrink-0" aria-hidden="true" />

            <!-- Component Icon -->
            <component
                :is="componentIcon"
                :class="[
                    'h-3.5 w-3.5 shrink-0',
                    isSelected
                        ? 'text-primary'
                        : 'text-muted-foreground group-hover:text-foreground',
                ]"
            />

            <!-- Inline Rename Input or Label Display -->
            <input
                v-if="isRenaming"
                ref="renameInputRef"
                v-model="renamingText"
                type="text"
                maxlength="64"
                data-testid="inline-rename-input"
                class="h-6 w-full max-w-[160px] min-w-0 rounded border border-primary bg-background px-1.5 py-0.5 text-xs text-foreground shadow-xs ring-1 ring-primary outline-hidden focus:ring-2 focus:ring-primary"
                @click.stop
                @mousedown.stop
                @dblclick.stop
                @keydown.enter.prevent.stop="commitRenaming"
                @keydown.esc.prevent.stop="cancelRenaming"
                @keydown.stop
                @blur="commitRenaming"
            />
            <template v-else>
                <!-- Component / Custom Label -->
                <span
                    class="cursor-text truncate font-medium"
                    :title="
                        hasCustomLabel
                            ? `${displayLabel} (${componentLabel})`
                            : displayLabel
                    "
                    data-testid="layer-label"
                    @dblclick.stop="startRenaming"
                >
                    {{ displayLabel }}
                </span>

                <!-- Text Preview or Child Count -->
                <span
                    v-if="textPreview"
                    class="max-w-[120px] truncate text-[11px] font-normal text-muted-foreground group-hover:text-foreground/80"
                    :title="textPreview"
                >
                    "{{ textPreview }}"
                </span>

                <span
                    v-else-if="isContainer && hasChildren"
                    class="font-mono text-[10px] text-muted-foreground/70"
                >
                    ({{ childrenList.length }})
                </span>
            </template>

            <!-- Trailing Badges and Lock Controls -->
            <div class="ml-auto flex shrink-0 items-center gap-1">
                <!-- Direct Search Match Badge -->
                <span
                    v-if="searchFilter && isDirectMatch"
                    class="rounded bg-primary/10 px-1 py-0.5 text-[9px] font-medium text-primary"
                >
                    match
                </span>

                <!-- Hidden on Active Viewport Indicator -->
                <span
                    v-if="isHiddenOnActiveViewport"
                    class="flex items-center gap-0.5 rounded bg-amber-500/10 px-1 py-0.5 text-[9px] font-medium text-amber-600 uppercase"
                    title="Hidden on active viewport"
                >
                    <EyeOff class="h-2.5 w-2.5" />
                    <span class="hidden sm:inline">hidden</span>
                </span>

                <!-- Visibility / Inherited Visibility Control (non-root only) -->
                <!-- Case 1: Directly hidden node (editable mode, not locked) -->
                <button
                    v-if="
                        !readOnly &&
                        !isRoot &&
                        isDirectHidden &&
                        !isInheritedHidden &&
                        !isEffectiveLocked
                    "
                    type="button"
                    tabindex="-1"
                    class="flex h-5 w-5 items-center justify-center rounded text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                    :title="`Show ${componentLabel}`"
                    :aria-label="`Show ${componentLabel}`"
                    data-testid="visibility-toggle"
                    @click.stop="handleToggleHidden"
                >
                    <EyeOff class="h-3 w-3" />
                </button>

                <!-- Case 2: Directly hidden BUT locked (cannot unhide directly) -->
                <button
                    v-else-if="
                        !readOnly &&
                        !isRoot &&
                        isDirectHidden &&
                        isEffectiveLocked
                    "
                    type="button"
                    tabindex="-1"
                    disabled
                    class="flex h-5 w-5 cursor-not-allowed items-center justify-center rounded text-muted-foreground/40"
                    :title="`Locked by parent container (${effectiveProtectingLabel})`"
                    :aria-label="`Locked by parent container (${effectiveProtectingLabel})`"
                    data-testid="visibility-toggle-disabled"
                    @click.stop
                >
                    <EyeOff class="h-3 w-3 text-muted-foreground/40" />
                </button>

                <!-- Case 3: Inherited hidden on a descendant (editable mode) -->
                <span
                    v-else-if="!readOnly && !isRoot && isInheritedHidden"
                    class="flex h-5 w-5 items-center justify-center text-muted-foreground/50"
                    :title="`Hidden by ancestor (${effectiveHidingLabel})`"
                    :aria-label="`Hidden by ancestor (${effectiveHidingLabel})`"
                    data-testid="inherited-hidden-indicator"
                >
                    <EyeOff class="h-3 w-3 opacity-60" />
                </span>

                <!-- Case 4: Visible node in editable mode (not locked): show Eye on hover -->
                <button
                    v-else-if="!readOnly && !isRoot && !isEffectiveLocked"
                    type="button"
                    tabindex="-1"
                    class="flex h-5 w-5 items-center justify-center rounded text-muted-foreground/60 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-accent hover:text-foreground"
                    :title="`Hide ${componentLabel}`"
                    :aria-label="`Hide ${componentLabel}`"
                    data-testid="visibility-toggle"
                    @click.stop="handleToggleHidden"
                >
                    <Eye class="h-3 w-3" />
                </button>

                <!-- Case 5: Viewer mode (readOnly) with hidden state: non-interactive indicator -->
                <span
                    v-else-if="readOnly && !isRoot && isGloballyHidden"
                    class="flex h-5 w-5 items-center justify-center text-muted-foreground/50"
                    :title="
                        isDirectHidden
                            ? `Hidden: ${componentLabel}`
                            : `Hidden by ancestor (${effectiveHidingLabel})`
                    "
                    :aria-label="
                        isDirectHidden
                            ? `Hidden: ${componentLabel}`
                            : `Hidden by ancestor (${effectiveHidingLabel})`
                    "
                    data-testid="visibility-indicator"
                >
                    <EyeOff class="h-3 w-3 opacity-60" />
                </span>

                <!-- Lock / Inherited Lock Control -->
                <!-- Case 1: Directly locked node (editable mode) -->
                <button
                    v-if="!readOnly && isDirectLocked && !isInheritedLocked"
                    type="button"
                    tabindex="-1"
                    class="flex h-5 w-5 items-center justify-center rounded text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                    :title="`Unlock ${componentLabel}`"
                    :aria-label="`Unlock ${componentLabel}`"
                    data-testid="lock-toggle"
                    @click.stop="handleToggleLock"
                >
                    <Lock class="h-3 w-3" />
                </button>

                <!-- Case 2: Directly locked BUT protected by locked ancestor (cannot unlock directly) -->
                <button
                    v-else-if="!readOnly && isDirectLocked && isInheritedLocked"
                    type="button"
                    tabindex="-1"
                    disabled
                    class="flex h-5 w-5 cursor-not-allowed items-center justify-center rounded text-muted-foreground/40"
                    :title="`Locked by parent container (${effectiveProtectingLabel})`"
                    :aria-label="`Locked by parent container (${effectiveProtectingLabel})`"
                    data-testid="lock-toggle-disabled"
                    @click.stop
                >
                    <Lock class="h-3 w-3 text-muted-foreground/40" />
                </button>

                <!-- Case 3: Inherited lock on an unlocked descendant (editable mode) -->
                <span
                    v-else-if="!readOnly && isInheritedLocked"
                    class="flex h-5 w-5 items-center justify-center text-muted-foreground/50"
                    :title="`Locked by parent container (${effectiveProtectingLabel})`"
                    :aria-label="`Locked by parent container (${effectiveProtectingLabel})`"
                    data-testid="inherited-lock-indicator"
                >
                    <Lock class="h-3 w-3 opacity-60" />
                </span>

                <!-- Case 4: Unlocked node in editable mode (lock button shows on group hover) -->
                <button
                    v-else-if="!readOnly"
                    type="button"
                    tabindex="-1"
                    class="flex h-5 w-5 items-center justify-center rounded text-muted-foreground/60 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-accent hover:text-foreground"
                    :title="`Lock ${componentLabel}`"
                    :aria-label="`Lock ${componentLabel}`"
                    data-testid="lock-toggle"
                    @click.stop="handleToggleLock"
                >
                    <Unlock class="h-3 w-3" />
                </button>

                <!-- Case 5: Viewer mode (readOnly) with locked state: non-interactive indicator -->
                <span
                    v-else-if="
                        readOnly && (isDirectLocked || isInheritedLocked)
                    "
                    class="flex h-5 w-5 items-center justify-center text-muted-foreground/50"
                    :title="
                        isDirectLocked
                            ? `Locked: ${componentLabel}`
                            : `Locked by parent container (${effectiveProtectingLabel})`
                    "
                    :aria-label="
                        isDirectLocked
                            ? `Locked: ${componentLabel}`
                            : `Locked by parent container (${effectiveProtectingLabel})`
                    "
                    data-testid="lock-indicator"
                >
                    <Lock class="h-3 w-3 opacity-60" />
                </span>
            </div>
        </div>

        <!-- Render Children Recursively if Container is Expanded -->
        <template v-if="isContainer && isExpanded">
            <!-- Nested children -->
            <template v-if="hasChildren">
                <template v-for="child in childrenList" :key="child.id">
                    <LayerTreeNode
                        v-if="!searchFilter || searchFilter.has(child.id)"
                        :node="child"
                        :depth="depth + 1"
                        :selected-id="selectedId"
                        :hovered-id="hoveredId"
                        :focused-id="focusedId"
                        :expanded-ids="expandedIds"
                        :components="components"
                        :read-only="readOnly"
                        :viewport="viewport"
                        :search-filter="searchFilter"
                        :direct-match-ids="directMatchIds"
                        :dragged-id="draggedId"
                        :drop-target="dropTarget"
                        :parent-locked="isEffectiveLocked"
                        :locked-ancestor-label="effectiveProtectingLabel"
                        :parent-hidden="isGloballyHidden"
                        :hidden-ancestor-label="effectiveHidingLabel"
                        @select="emit('select', $event)"
                        @hover="emit('hover', $event)"
                        @focus="emit('focus', $event)"
                        @toggle-expand="emit('toggleExpand', $event)"
                        @set-node-locked="emit('setNodeLocked', $event)"
                        @set-node-hidden="emit('setNodeHidden', $event)"
                        @set-node-label="emit('setNodeLabel', $event)"
                        @drag-start="(id, e) => emit('dragStart', id, e)"
                        @drag-end="(id, e) => emit('dragEnd', id, e)"
                        @drag-over="
                            (id, pos, e) => emit('dragOver', id, pos, e)
                        "
                        @drag-leave="(id, e) => emit('dragLeave', id, e)"
                        @drop="(id, e) => emit('drop', id, e)"
                    />
                </template>
            </template>

            <!-- Empty Container Placeholder -->
            <div
                v-else
                :style="{ paddingLeft: `${(depth + 1) * 14 + 18}px` }"
                class="flex h-6 items-center text-[11px] text-muted-foreground/60 italic select-none"
                :class="[
                    isDropTarget && dropTargetPosition === 'inside'
                        ? isDropTargetValid
                            ? 'bg-primary/20 font-medium ring-1 ring-primary'
                            : 'cursor-not-allowed bg-destructive/15 ring-1 ring-destructive'
                        : '',
                ]"
                @dragover="handleEmptyContainerDragOver"
                @dragleave="handleDragLeave"
                @drop="handleEmptyContainerDrop"
            >
                Empty container
            </div>
        </template>
    </div>
</template>
