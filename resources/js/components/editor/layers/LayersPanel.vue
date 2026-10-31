<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import {
    ChevronsDown,
    ChevronsUp,
    HelpCircle,
    Layers,
    PanelLeftClose,
    Search,
    X,
} from '@lucide/vue';
import LayerTreeNode from './LayerTreeNode.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { resolvePointerDropTarget, type DropPosition } from '@/lib/dropIndex';
import {
    CONTAINER_COMPONENT_TYPES,
    DOCUMENT_LIMITS,
    type ComponentMetadata,
    type DocumentNode,
    type DocumentSchema,
    type ViewportMode,
} from '@/types/document';

const props = withDefaults(
    defineProps<{
        document: DocumentSchema;
        selectedId?: string | null;
        hoveredId?: string | null;
        components?: Record<string, ComponentMetadata>;
        readOnly?: boolean;
        viewport?: ViewportMode;
        activeTab?: 'components' | 'layers';
    }>(),
    {
        selectedId: null,
        hoveredId: null,
        components: () => ({}),
        readOnly: false,
        viewport: 'desktop',
        activeTab: 'layers',
    },
);

const emit = defineEmits<{
    (e: 'select', id: string): void;
    (e: 'hover', id: string | null): void;
    (e: 'close'): void;
    (e: 'switchTab', tab: 'components' | 'layers'): void;
    (
        e: 'moveNode',
        payload: {
            nodeId: string;
            targetParentId: string;
            targetIndex: number;
        },
    ): void;
    (
        e: 'setNodeLocked',
        payload: {
            nodeId: string;
            locked: boolean;
        },
    ): void;
    (
        e: 'setNodeHidden',
        payload: {
            nodeId: string;
            hidden: boolean;
        },
    ): void;
    (
        e: 'setNodeLabel',
        payload: {
            nodeId: string;
            label: string | null;
        },
    ): void;
}>();

// UI-only expansion state (Set of node IDs)
const expandedIds = ref<Set<string>>(new Set());

// Initialize: Root node is expanded by default
if (props.document?.root?.id) {
    expandedIds.value.add(props.document.root.id);
}

// Search State
const searchQuery = ref('');
const preSearchExpandedIds = ref<Set<string> | null>(null);

function getNodeTextPreview(node: DocumentNode): string | null {
    const p = node.props;
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
    if (typeof p.subtitle === 'string' && p.subtitle.trim()) {
        return p.subtitle.trim();
    }
    if (Array.isArray(p.items)) {
        return `${p.items.length} item${p.items.length === 1 ? '' : 's'}`;
    }
    return null;
}

function getNodeSearchableStrings(node: DocumentNode): string[] {
    const strings: string[] = [node.type, node.id];
    if (node.label) strings.push(node.label);
    const label = props.components?.[node.type]?.label;
    if (label) strings.push(label);

    const preview = getNodeTextPreview(node);
    if (preview) strings.push(preview);

    const p = node.props;
    if (p && typeof p === 'object') {
        if (typeof p.subtitle === 'string' && p.subtitle.trim()) {
            strings.push(p.subtitle.trim());
        }
        if (Array.isArray(p.items)) {
            for (const item of p.items) {
                if (item && typeof item === 'object') {
                    if (
                        typeof item.question === 'string' &&
                        item.question.trim()
                    ) {
                        strings.push(item.question.trim());
                    }
                    if (typeof item.title === 'string' && item.title.trim()) {
                        strings.push(item.title.trim());
                    }
                }
            }
        }
    }
    return strings;
}

function nodeMatchesQuery(node: DocumentNode, query: string): boolean {
    const q = query.toLowerCase();
    const strings = getNodeSearchableStrings(node);
    return strings.some((s) => s.toLowerCase().includes(q));
}

// Compute matching node IDs and ancestor paths
const searchMatchState = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query || !props.document?.root) {
        return {
            isFiltering: false,
            directMatches: new Set<string>(),
            visibleInSearch: null as Set<string> | null,
            ancestorIdsToOpen: new Set<string>(),
        };
    }

    const directMatches = new Set<string>();
    const visibleInSearch = new Set<string>();
    const ancestorIdsToOpen = new Set<string>();

    function traverse(node: DocumentNode, ancestors: string[]): boolean {
        const isMatch = nodeMatchesQuery(node, query);
        let hasMatchingDescendant = false;

        const currentPath = [...ancestors, node.id];

        for (const child of node.children ?? []) {
            const childMatched = traverse(child, currentPath);
            if (childMatched) {
                hasMatchingDescendant = true;
            }
        }

        if (isMatch) {
            directMatches.add(node.id);
            visibleInSearch.add(node.id);
            for (const ancId of ancestors) {
                visibleInSearch.add(ancId);
                ancestorIdsToOpen.add(ancId);
            }
        } else if (hasMatchingDescendant) {
            visibleInSearch.add(node.id);
            for (const ancId of ancestors) {
                visibleInSearch.add(ancId);
                ancestorIdsToOpen.add(ancId);
            }
        }

        return isMatch || hasMatchingDescendant;
    }

    traverse(props.document.root, []);

    return {
        isFiltering: true,
        directMatches,
        visibleInSearch,
        ancestorIdsToOpen,
    };
});

// Watch search match state to preserve/restore manual expansion state and auto-expand matches
watch(
    searchMatchState,
    (state) => {
        if (state.isFiltering) {
            // First time filtering: store pre-search expanded IDs
            if (preSearchExpandedIds.value === null) {
                preSearchExpandedIds.value = new Set(expandedIds.value);
            }
            // Expand all ancestors of matches
            for (const ancId of state.ancestorIdsToOpen) {
                expandedIds.value.add(ancId);
            }
        } else {
            // Search cleared: restore user's previous expansion state
            if (preSearchExpandedIds.value !== null) {
                expandedIds.value = new Set(preSearchExpandedIds.value);
                preSearchExpandedIds.value = null;
            }
        }
    },
    { immediate: true },
);

const clearSearch = () => {
    searchQuery.value = '';
};

// Find path of ancestor IDs from root to targetId
function findAncestorIds(
    current: DocumentNode,
    targetId: string,
    path: string[] = [],
): string[] | null {
    if (current.id === targetId) {
        return path;
    }
    if (current.children && current.children.length > 0) {
        for (const child of current.children) {
            const found = findAncestorIds(child, targetId, [
                ...path,
                current.id,
            ]);
            if (found) return found;
        }
    }
    return null;
}

function expandAncestorsOf(targetId: string) {
    if (!props.document?.root) return;
    const ancestors = findAncestorIds(props.document.root, targetId);
    if (ancestors && ancestors.length > 0) {
        for (const id of ancestors) {
            expandedIds.value.add(id);
        }
    }
}

// Watch selectedId: when selection changes, expand all ancestor containers
watch(
    () => props.selectedId,
    (newId) => {
        if (newId) {
            expandAncestorsOf(newId);
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.selectedId) {
        expandAncestorsOf(props.selectedId);
    }
});

const handleToggleExpand = (id: string) => {
    if (expandedIds.value.has(id)) {
        expandedIds.value.delete(id);
    } else {
        expandedIds.value.add(id);
    }
};

const expandAll = () => {
    function collectContainerIds(node: DocumentNode) {
        if (CONTAINER_COMPONENT_TYPES.has(node.type)) {
            expandedIds.value.add(node.id);
        }
        for (const child of node.children ?? []) {
            collectContainerIds(child);
        }
    }
    if (props.document?.root) {
        collectContainerIds(props.document.root);
    }
};

const collapseAll = () => {
    expandedIds.value.clear();
    // Keep root container open
    if (props.document?.root?.id) {
        expandedIds.value.add(props.document.root.id);
    }
};

// Node & Parent Lookups
function findNodeInDoc(
    id: string,
    current: DocumentNode | undefined = props.document?.root,
): DocumentNode | null {
    if (!current) return null;
    if (current.id === id) return current;
    for (const child of current.children ?? []) {
        const found = findNodeInDoc(id, child);
        if (found) return found;
    }
    return null;
}

function findParentNodeInDoc(
    id: string,
    current: DocumentNode | undefined = props.document?.root,
    parent: DocumentNode | null = null,
): DocumentNode | null {
    if (!current) return null;
    if (current.id === id) return parent;
    for (const child of current.children ?? []) {
        const found = findParentNodeInDoc(id, child, current);
        if (found) return found;
    }
    return null;
}

function isNodeOrAncestorLockedInDoc(nodeId: string): boolean {
    const node = findNodeInDoc(nodeId);
    if (!node) return false;
    if (node.locked) return true;
    let parent = findParentNodeInDoc(nodeId);
    while (parent) {
        if (parent.locked) return true;
        parent = findParentNodeInDoc(parent.id);
    }
    return false;
}

// Visible Node List for Keyboard Traversal
function collectVisibleNodeIds(
    node: DocumentNode,
    expanded: Set<string>,
    searchFilter: Set<string> | null,
    out: string[] = [],
): string[] {
    if (searchFilter && !searchFilter.has(node.id)) {
        return out;
    }

    out.push(node.id);

    if (
        CONTAINER_COMPONENT_TYPES.has(node.type) &&
        expanded.has(node.id) &&
        node.children &&
        node.children.length > 0
    ) {
        for (const child of node.children) {
            collectVisibleNodeIds(child, expanded, searchFilter, out);
        }
    }

    return out;
}

const visibleNodeIds = computed<string[]>(() => {
    if (!props.document?.root) return [];
    return collectVisibleNodeIds(
        props.document.root,
        expandedIds.value,
        searchMatchState.value.visibleInSearch,
    );
});

// Focus & Roving Tabindex
const treeContainerRef = ref<HTMLElement | null>(null);
const focusedNodeId = ref<string | null>(null);

const effectiveFocusedId = computed<string | null>(() => {
    const list = visibleNodeIds.value;
    if (list.length === 0) return null;
    if (focusedNodeId.value && list.includes(focusedNodeId.value)) {
        return focusedNodeId.value;
    }
    if (props.selectedId && list.includes(props.selectedId)) {
        return props.selectedId;
    }
    return list[0] ?? null;
});

function focusNode(id: string) {
    focusedNodeId.value = id;
    nextTick(() => {
        const el = treeContainerRef.value?.querySelector(
            `[data-node-id="${id}"]`,
        ) as HTMLElement | null;
        if (el) {
            el.focus();
        }
    });
}

const handleFocusNode = (id: string) => {
    focusedNodeId.value = id;
};

const focusFirstVisibleNode = () => {
    if (effectiveFocusedId.value) {
        focusNode(effectiveFocusedId.value);
    }
};

const handleTreeKeydown = (e: KeyboardEvent) => {
    // Skip if active element is an interactive input or textarea
    const target = e.target as HTMLElement | null;
    const activeEl = (
        typeof document !== 'undefined' ? document.activeElement : null
    ) as HTMLElement | null;
    const el = target || activeEl;
    if (el) {
        const tagName = el.tagName?.toLowerCase();
        const isContentEditable = el.isContentEditable;
        if (
            tagName === 'input' ||
            tagName === 'textarea' ||
            isContentEditable
        ) {
            return;
        }
    }

    const list = visibleNodeIds.value;
    if (list.length === 0) return;

    const currentId = effectiveFocusedId.value;
    const currentIndex = currentId ? list.indexOf(currentId) : -1;

    // Handle structural mutations with Alt modifier
    if (e.altKey) {
        if (props.readOnly) {
            // Viewer mode: ignore keyboard movement shortcuts without mutation or error
            return;
        }

        const key = e.key;
        if (
            key !== 'ArrowUp' &&
            key !== 'ArrowDown' &&
            key !== 'ArrowLeft' &&
            key !== 'ArrowRight'
        ) {
            return;
        }

        if (!currentId) return;

        // Root node protection: root node cannot be moved
        if (currentId === props.document?.root?.id) {
            return;
        }

        // Node locking protection: locked node cannot be moved
        if (isNodeOrAncestorLockedInDoc(currentId)) {
            return;
        }

        const parentNode = findParentNodeInDoc(currentId);
        if (!parentNode || !parentNode.children) return;

        const siblings = parentNode.children;
        const siblingIndex = siblings.findIndex((c) => c.id === currentId);
        if (siblingIndex === -1) return;

        switch (key) {
            case 'ArrowUp': {
                e.preventDefault();
                if (siblingIndex === 0) {
                    // Legitimate boundary no-op: already top sibling
                    return;
                }
                emit('moveNode', {
                    nodeId: currentId,
                    targetParentId: parentNode.id,
                    targetIndex: siblingIndex - 1,
                });
                focusNode(currentId);
                break;
            }
            case 'ArrowDown': {
                e.preventDefault();
                if (siblingIndex === siblings.length - 1) {
                    // Legitimate boundary no-op: already bottom sibling
                    return;
                }
                emit('moveNode', {
                    nodeId: currentId,
                    targetParentId: parentNode.id,
                    targetIndex: siblingIndex + 1,
                });
                focusNode(currentId);
                break;
            }
            case 'ArrowLeft': {
                e.preventDefault();
                if (parentNode.id === props.document?.root?.id) {
                    // Legitimate boundary no-op: already at outermost level under root
                    return;
                }
                const grandparentNode = findParentNodeInDoc(parentNode.id);
                if (!grandparentNode || !grandparentNode.children) {
                    return;
                }
                if (isNodeOrAncestorLockedInDoc(grandparentNode.id)) {
                    return;
                }
                const parentIndex = grandparentNode.children.findIndex(
                    (c) => c.id === parentNode.id,
                );
                if (parentIndex === -1) return;

                emit('moveNode', {
                    nodeId: currentId,
                    targetParentId: grandparentNode.id,
                    targetIndex: parentIndex + 1,
                });
                focusNode(currentId);
                break;
            }
            case 'ArrowRight': {
                e.preventDefault();
                if (siblingIndex === 0) {
                    // Legitimate boundary no-op: no preceding sibling to move into
                    return;
                }
                const precedingSibling = siblings[siblingIndex - 1];
                if (!precedingSibling) return;
                if (isNodeOrAncestorLockedInDoc(precedingSibling.id)) {
                    return;
                }

                const targetIndex = precedingSibling.children?.length ?? 0;
                emit('moveNode', {
                    nodeId: currentId,
                    targetParentId: precedingSibling.id,
                    targetIndex,
                });
                if (CONTAINER_COMPONENT_TYPES.has(precedingSibling.type)) {
                    expandedIds.value.add(precedingSibling.id);
                }
                focusNode(currentId);
                break;
            }
        }
        return;
    }

    switch (e.key) {
        case 'ArrowDown': {
            e.preventDefault();
            if (currentIndex < list.length - 1) {
                focusNode(list[currentIndex + 1]);
            }
            break;
        }
        case 'ArrowUp': {
            e.preventDefault();
            if (currentIndex > 0) {
                focusNode(list[currentIndex - 1]);
            }
            break;
        }
        case 'Home': {
            e.preventDefault();
            if (list.length > 0) {
                focusNode(list[0]);
            }
            break;
        }
        case 'End': {
            e.preventDefault();
            if (list.length > 0) {
                focusNode(list[list.length - 1]);
            }
            break;
        }
        case 'Enter':
        case ' ': {
            e.preventDefault();
            if (currentId) {
                emit('select', currentId);
            }
            break;
        }
        case 'ArrowRight': {
            e.preventDefault();
            if (!currentId) return;
            const nodeSearch = findNodeInDoc(currentId);
            if (!nodeSearch) return;
            const isContainer = CONTAINER_COMPONENT_TYPES.has(nodeSearch.type);
            if (isContainer) {
                const isExpanded = expandedIds.value.has(currentId);
                const hasChildren = (nodeSearch.children?.length ?? 0) > 0;
                if (!isExpanded) {
                    expandedIds.value.add(currentId);
                } else if (hasChildren && currentIndex < list.length - 1) {
                    focusNode(list[currentIndex + 1]);
                }
            }
            break;
        }
        case 'ArrowLeft': {
            e.preventDefault();
            if (!currentId) return;
            const nodeSearch = findNodeInDoc(currentId);
            if (!nodeSearch) return;
            const isContainer = CONTAINER_COMPONENT_TYPES.has(nodeSearch.type);
            const isExpanded = expandedIds.value.has(currentId);
            if (isContainer && isExpanded) {
                expandedIds.value.delete(currentId);
                focusNode(currentId);
            } else {
                const parent = findParentNodeInDoc(currentId);
                if (parent && list.includes(parent.id)) {
                    focusNode(parent.id);
                }
            }
            break;
        }
    }
};

function countNodes(node?: DocumentNode): number {
    if (!node) return 0;
    let count = 1;
    for (const child of node.children ?? []) {
        count += countNodes(child);
    }
    return count;
}

function getMaxDepth(node?: DocumentNode, currentDepth: number = 1): number {
    if (!node || !node.children || node.children.length === 0) {
        return currentDepth;
    }
    let max = currentDepth;
    for (const child of node.children) {
        max = Math.max(max, getMaxDepth(child, currentDepth + 1));
    }
    return max;
}

const totalNodes = computed(() => countNodes(props.document?.root));
const maxDepth = computed(() => getMaxDepth(props.document?.root));

// Pointer Drag-and-Drop State & Handlers
const draggedNodeId = ref<string | null>(null);
const dropTargetState = ref<{
    nodeId: string;
    position: DropPosition;
    isValid: boolean;
} | null>(null);

function isDescendantInDoc(candidateId: string, ancestorId: string): boolean {
    if (candidateId === ancestorId) return true;
    const ancestor = findNodeInDoc(ancestorId);
    if (!ancestor) return false;
    function check(node: DocumentNode): boolean {
        for (const child of node.children ?? []) {
            if (child.id === candidateId) return true;
            if (check(child)) return true;
        }
        return false;
    }
    return check(ancestor);
}

const handleNodeDragStart = (nodeId: string, e: DragEvent) => {
    if (
        props.readOnly ||
        nodeId === props.document?.root?.id ||
        isNodeOrAncestorLockedInDoc(nodeId)
    ) {
        e.preventDefault();
        return;
    }
    draggedNodeId.value = nodeId;
    if (e.dataTransfer) {
        e.dataTransfer.setData('application/x-pentagon-layer-id', nodeId);
        e.dataTransfer.setData('text/plain', nodeId);
        e.dataTransfer.effectAllowed = 'move';
    }
};

const handleNodeDragEnd = () => {
    draggedNodeId.value = null;
    dropTargetState.value = null;
};

const handleNodeDragOver = (
    targetId: string,
    position: DropPosition,
    e: DragEvent,
) => {
    if (props.readOnly || !draggedNodeId.value) {
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'none';
        return;
    }

    const sourceId = draggedNodeId.value;
    const sourceNode = findNodeInDoc(sourceId);
    if (!sourceNode) return;
    const sourceParent = findParentNodeInDoc(sourceId);

    const targetNode = findNodeInDoc(targetId);
    if (!targetNode) return;
    const targetParent = findParentNodeInDoc(targetId);

    const isDescendant = isDescendantInDoc(targetId, sourceId);
    const sourceIsLocked = isNodeOrAncestorLockedInDoc(sourceId);
    const targetIsLocked = isNodeOrAncestorLockedInDoc(targetId);
    const targetParentIsLocked = targetParent
        ? isNodeOrAncestorLockedInDoc(targetParent.id)
        : false;

    const resolved = resolvePointerDropTarget({
        sourceNodeId: sourceId,
        sourceParentId: sourceParent?.id ?? null,
        sourceIndex:
            sourceParent?.children?.findIndex((c) => c.id === sourceId) ?? -1,
        targetNodeId: targetId,
        targetParentId: targetParent?.id ?? null,
        targetIndexInParent:
            targetParent?.children?.findIndex((c) => c.id === targetId) ?? -1,
        targetIsContainer: CONTAINER_COMPONENT_TYPES.has(targetNode.type),
        targetChildCount: targetNode.children?.length ?? 0,
        position,
        targetIsDescendantOfSource: isDescendant,
        sourceIsLocked,
        targetIsLocked,
        targetParentIsLocked,
    });

    if (!resolved.isValid) {
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'none';
        dropTargetState.value = {
            nodeId: targetId,
            position,
            isValid: false,
        };
        return;
    }

    e.preventDefault();
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
    dropTargetState.value = {
        nodeId: targetId,
        position,
        isValid: true,
    };
};

const handleNodeDragLeave = (targetId: string) => {
    if (dropTargetState.value?.nodeId === targetId) {
        dropTargetState.value = null;
    }
};

const handleNodeDrop = (targetId: string, e: DragEvent) => {
    e.preventDefault();
    if (props.readOnly) return;

    const sourceId =
        e.dataTransfer?.getData('application/x-pentagon-layer-id') ||
        draggedNodeId.value;

    const targetState = dropTargetState.value;
    draggedNodeId.value = null;
    dropTargetState.value = null;

    if (!sourceId || sourceId === props.document?.root?.id) return;
    if (isNodeOrAncestorLockedInDoc(sourceId)) return;

    const sourceNode = findNodeInDoc(sourceId);
    if (!sourceNode) return;
    const sourceParent = findParentNodeInDoc(sourceId);

    const targetNode = findNodeInDoc(targetId);
    if (!targetNode) return;
    const targetParent = findParentNodeInDoc(targetId);

    const position =
        targetState?.nodeId === targetId ? targetState.position : 'inside';
    const isDescendant = isDescendantInDoc(targetId, sourceId);
    const sourceIsLocked = isNodeOrAncestorLockedInDoc(sourceId);
    const targetIsLocked = isNodeOrAncestorLockedInDoc(targetId);
    const targetParentIsLocked = targetParent
        ? isNodeOrAncestorLockedInDoc(targetParent.id)
        : false;

    const resolved = resolvePointerDropTarget({
        sourceNodeId: sourceId,
        sourceParentId: sourceParent?.id ?? null,
        sourceIndex:
            sourceParent?.children?.findIndex((c) => c.id === sourceId) ?? -1,
        targetNodeId: targetId,
        targetParentId: targetParent?.id ?? null,
        targetIndexInParent:
            targetParent?.children?.findIndex((c) => c.id === targetId) ?? -1,
        targetIsContainer: CONTAINER_COMPONENT_TYPES.has(targetNode.type),
        targetChildCount: targetNode.children?.length ?? 0,
        position,
        targetIsDescendantOfSource: isDescendant,
        sourceIsLocked,
        targetIsLocked,
        targetParentIsLocked,
    });

    if (!resolved.isValid || resolved.isNoop) {
        return;
    }

    emit('moveNode', {
        nodeId: sourceId,
        targetParentId: resolved.targetParentId,
        targetIndex: resolved.targetIndex,
    });

    // Auto-expand destination container if dropped inside or into collapsed container
    expandedIds.value.add(resolved.targetParentId);

    nextTick(() => {
        focusNode(sourceId);
    });
};
</script>

<template>
    <aside
        class="relative z-20 flex h-full w-80 shrink-0 flex-col border-r bg-background select-none"
        aria-label="Layers Tree Panel"
    >
        <!-- Header with Segmented Tab Switcher and Controls -->
        <div class="flex flex-col border-b">
            <!-- Top Bar: Switcher + Close Button -->
            <div class="flex items-center justify-between px-3 py-2.5">
                <!-- Segmented Control: Components | Layers -->
                <div class="flex rounded-lg bg-muted p-0.5">
                    <button
                        type="button"
                        :class="[
                            'rounded-md px-2.5 py-1 text-xs font-medium transition-all',
                            activeTab === 'components'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="emit('switchTab', 'components')"
                    >
                        Components
                    </button>
                    <button
                        type="button"
                        :class="[
                            'flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-all',
                            activeTab === 'layers'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="emit('switchTab', 'layers')"
                    >
                        <Layers class="h-3 w-3 text-primary" />
                        <span>Layers</span>
                    </button>
                </div>

                <!-- Action Tools -->
                <div class="flex items-center gap-0.5">
                    <TooltipProvider :delay-duration="300">
                        <!-- Expand All -->
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                    aria-label="Expand All Containers"
                                    @click="expandAll"
                                >
                                    <ChevronsDown class="h-3.5 w-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p class="text-xs">Expand All</p>
                            </TooltipContent>
                        </Tooltip>

                        <!-- Collapse All -->
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                    aria-label="Collapse All Containers"
                                    @click="collapseAll"
                                >
                                    <ChevronsUp class="h-3.5 w-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p class="text-xs">Collapse All</p>
                            </TooltipContent>
                        </Tooltip>

                        <!-- Keyboard Shortcuts Info -->
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                    aria-label="Keyboard Shortcuts"
                                >
                                    <HelpCircle class="h-3.5 w-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent
                                side="bottom"
                                class="max-w-xs space-y-1 text-xs"
                            >
                                <p class="font-medium">Keyboard Movement</p>
                                <p>Alt+↑ / Alt+↓: Reorder siblings</p>
                                <p>Alt+→: Move into preceding container</p>
                                <p>Alt+←: Move out of parent</p>
                            </TooltipContent>
                        </Tooltip>

                        <!-- Close Sidebar -->
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                    title="Collapse Sidebar (Ctrl+B)"
                                    aria-label="Collapse Sidebar"
                                    @click="emit('close')"
                                >
                                    <PanelLeftClose class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p class="text-xs">Collapse Sidebar (Ctrl+B)</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="border-t px-2 py-1.5">
                <div class="relative flex items-center">
                    <label for="layers-search-input" class="sr-only">
                        Search layers
                    </label>
                    <Search
                        class="pointer-events-none absolute left-2 h-3.5 w-3.5 text-muted-foreground"
                    />
                    <input
                        id="layers-search-input"
                        v-model="searchQuery"
                        type="search"
                        class="h-7 w-full rounded-md border border-input bg-muted/30 pr-7 pl-7 text-xs text-foreground placeholder:text-muted-foreground/60 focus:border-primary focus:bg-background focus:ring-1 focus:ring-primary focus:outline-hidden"
                        placeholder="Search layers..."
                        autocomplete="off"
                        spellcheck="false"
                        @keydown.down.prevent="focusFirstVisibleNode"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute right-1.5 flex h-4 w-4 items-center justify-center rounded-sm text-muted-foreground hover:text-foreground"
                        aria-label="Clear search"
                        @click="clearSearch"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden keyboard instructions for assistive technologies -->
        <div id="layers-keyboard-instructions" class="sr-only">
            Tree navigation: use Arrow keys to navigate, Enter or Space to
            select. Structural editing: Alt+Up and Alt+Down to reorder siblings,
            Alt+Right to move into preceding container, Alt+Left to move out of
            parent.
        </div>

        <!-- Tree View Container -->
        <div
            ref="treeContainerRef"
            class="flex-1 overflow-y-auto px-2 py-2 focus:outline-hidden"
            role="tree"
            aria-label="Document Structure"
            aria-describedby="layers-keyboard-instructions"
            tabindex="-1"
            @keydown="handleTreeKeydown"
        >
            <LayerTreeNode
                v-if="document?.root && visibleNodeIds.length > 0"
                :node="document.root"
                :depth="0"
                :selected-id="selectedId"
                :hovered-id="hoveredId"
                :focused-id="effectiveFocusedId"
                :expanded-ids="expandedIds"
                :components="components"
                :read-only="readOnly"
                :viewport="viewport"
                :search-filter="searchMatchState.visibleInSearch"
                :direct-match-ids="searchMatchState.directMatches"
                :dragged-id="draggedNodeId"
                :drop-target="dropTargetState"
                @select="emit('select', $event)"
                @hover="emit('hover', $event)"
                @focus="handleFocusNode"
                @toggle-expand="handleToggleExpand"
                @set-node-locked="emit('setNodeLocked', $event)"
                @set-node-hidden="emit('setNodeHidden', $event)"
                @set-node-label="emit('setNodeLabel', $event)"
                @drag-start="handleNodeDragStart"
                @drag-end="handleNodeDragEnd"
                @drag-over="handleNodeDragOver"
                @drag-leave="handleNodeDragLeave"
                @drop="handleNodeDrop"
            />

            <!-- Search empty state fallback -->
            <div
                v-else-if="
                    searchMatchState.isFiltering && visibleNodeIds.length === 0
                "
                role="status"
                class="flex flex-1 flex-col items-center justify-center p-6 text-center text-xs text-muted-foreground"
            >
                <p>No layers matching "{{ searchQuery }}"</p>
                <Button
                    variant="ghost"
                    size="sm"
                    class="mt-2 h-7 text-xs"
                    @click="clearSearch"
                >
                    Clear search
                </Button>
            </div>

            <!-- Empty document fallback -->
            <div
                v-else
                class="flex flex-1 items-center justify-center p-6 text-center text-xs text-muted-foreground"
            >
                No document available
            </div>
        </div>

        <!-- Footer Tree Metrics -->
        <div
            class="flex items-center justify-between border-t bg-muted/20 px-3 py-1.5 text-[11px] text-muted-foreground select-none"
        >
            <span
                >{{ totalNodes }} /
                {{ DOCUMENT_LIMITS.MAX_TOTAL_NODES }} nodes</span
            >
            <span
                >Depth {{ maxDepth }} /
                {{ DOCUMENT_LIMITS.MAX_TREE_DEPTH }}</span
            >
        </div>
    </aside>
</template>
