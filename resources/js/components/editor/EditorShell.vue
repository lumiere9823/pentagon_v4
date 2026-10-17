<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { PanelLeftOpen, SlidersHorizontal } from '@lucide/vue';
import ComponentLibrary from './ComponentLibrary.vue';
import EditorCanvas from './EditorCanvas.vue';
import EditorToolbar from './EditorToolbar.vue';
import InspectorPanel from './InspectorPanel.vue';
import { useDocumentState } from '@/composables/useDocumentState';
import { useEditorHistory } from '@/composables/useEditorHistory';
import { useEditorState } from '@/composables/useEditorState';
import {
    CONTAINER_COMPONENT_TYPES,
    type ComponentMetadata,
    type ComponentRegistryResponse,
    type CoreComponentType,
    type DocumentSchema,
} from '@/types/document';

interface WebsiteSummary {
    id: number;
    name: string;
    slug: string;
}

interface PageSummary {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published' | 'archived' | string;
    updated_at?: string | null;
}

const props = defineProps<{
    website: WebsiteSummary;
    page: PageSummary;
    userRole: string;
    can: {
        edit: boolean;
        delete: boolean;
    };
    initialDocument?: DocumentSchema;
}>();

// Shell layout state (panels, viewport)
const {
    viewport,
    leftSidebarOpen,
    rightPanelOpen,
    setViewport,
    toggleLeftSidebar,
    toggleRightPanel,
} = useEditorState();

// Document state (tree, selection, mutations)
const {
    document,
    selectedNodeId,
    hoveredNodeId,
    selectedNode,
    lastError,
    selectNode,
    clearSelection,
    setHoveredNode,
    insertNode,
    updateNodeProps,
    updateNodeStyle,
    resetNodeStyle,
    deleteNode,
    duplicateNode,
    moveNodeRelative,
    setDocument,
} = useDocumentState(props.initialDocument);

// History management (undo/redo)
const { canUndo, canRedo, pushSnapshot, recordDebouncedEdit, undo, redo } =
    useEditorHistory(
        () => document.value,
        (restoredDoc) => setDocument(restoredDoc),
        { maxSnapshots: 30, debounceMs: 300 },
    );

// Component Registry metadata for Inspector
const registryMetadata = ref<Record<string, ComponentMetadata>>({});

const fetchRegistryMeta = async () => {
    try {
        const response = await fetch('/api/components/registry');
        if (response.ok) {
            const data: ComponentRegistryResponse = await response.json();
            registryMetadata.value = data.components || {};
        }
    } catch {
        // Handled silently, ComponentLibrary also fetches and provides error state
    }
};

onMounted(() => {
    fetchRegistryMeta();
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleGlobalKeyDown);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleGlobalKeyDown);
    }
});

function handleGlobalKeyDown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        clearSelection();
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
        // Prevent default browser bookmark shortcut and toggle sidebar
        const target = e.target as HTMLElement | null;
        const isInputField =
            target &&
            (target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.isContentEditable);
        if (!isInputField) {
            e.preventDefault();
            toggleLeftSidebar();
        }
    }
}

const handleSelectNode = (nodeId: string) => {
    selectNode(nodeId);
    // Auto-open Inspector if closed so user can edit immediately
    if (!rightPanelOpen.value) {
        toggleRightPanel();
    }
};

// Current metadata for selected node
const selectedNodeMetadata = computed(() => {
    if (!selectedNode.value) return null;
    return registryMetadata.value[selectedNode.value.type] || null;
});

// Handlers for Component Insertion
const handleInsertComponent = (type: CoreComponentType) => {
    if (!props.can.edit) return;

    // Snapshot before mutation
    const docBefore = JSON.parse(JSON.stringify(document.value));

    let res;
    if (selectedNode.value) {
        if (CONTAINER_COMPONENT_TYPES.has(selectedNode.value.type)) {
            res = insertNode(type, selectedNode.value.id, 'inside');
        } else {
            res = insertNode(type, selectedNode.value.id, 'after');
        }
    } else {
        res = insertNode(type, null, 'inside');
    }

    if (res.success) {
        pushSnapshot(docBefore);
    }
};

const handleDropComponent = (payload: {
    type: CoreComponentType;
    parentId: string;
    index: number;
}) => {
    if (!props.can.edit) return;

    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = insertNode(
        payload.type,
        payload.parentId,
        'inside',
        undefined,
        payload.index,
    );

    if (res.success) {
        pushSnapshot(docBefore);
    }
};

// Handlers for Node Actions
const handleMoveUp = (nodeId: string) => {
    if (!props.can.edit) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = moveNodeRelative(nodeId, 'up');
    if (res.success) {
        pushSnapshot(docBefore);
    }
};

const handleMoveDown = (nodeId: string) => {
    if (!props.can.edit) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = moveNodeRelative(nodeId, 'down');
    if (res.success) {
        pushSnapshot(docBefore);
    }
};

const handleDuplicate = (nodeId: string) => {
    if (!props.can.edit || nodeId === document.value.root.id) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = duplicateNode(nodeId);
    if (res.success) {
        pushSnapshot(docBefore);
    }
};

const handleDelete = (nodeId: string) => {
    if (!props.can.edit || nodeId === document.value.root.id) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = deleteNode(nodeId);
    if (res.success) {
        pushSnapshot(docBefore);
    }
};

const handleUpdateProp = (payload: { key: string; value: unknown }) => {
    if (!props.can.edit || !selectedNodeId.value) return;

    // Record debounced snapshot of document before edit
    recordDebouncedEdit(document.value);

    updateNodeProps(selectedNodeId.value, {
        [payload.key]: payload.value,
    });
};

const handleUpdateStyle = (payload: {
    viewport: 'tablet' | 'mobile';
    key: string;
    value: unknown;
}) => {
    if (!props.can.edit || !selectedNodeId.value) return;

    recordDebouncedEdit(document.value);

    updateNodeStyle(
        selectedNodeId.value,
        payload.viewport,
        payload.key,
        payload.value,
    );
};

const handleResetStyle = (payload: {
    viewport: 'tablet' | 'mobile';
    key: string;
}) => {
    if (!props.can.edit || !selectedNodeId.value) return;

    recordDebouncedEdit(document.value);

    resetNodeStyle(selectedNodeId.value, payload.viewport, payload.key);
};
</script>

<template>
    <div
        class="flex h-screen w-full flex-col overflow-hidden bg-background text-foreground antialiased"
    >
        <!-- Top Toolbar -->
        <EditorToolbar
            :website="website"
            :page="page"
            :viewport="viewport"
            :left-sidebar-open="leftSidebarOpen"
            :right-panel-open="rightPanelOpen"
            :user-role="userRole"
            :can-edit="can.edit"
            :can-undo="canUndo"
            :can-redo="canRedo"
            @update:viewport="setViewport"
            @toggle-left-sidebar="toggleLeftSidebar"
            @toggle-right-panel="toggleRightPanel"
            @undo="undo"
            @redo="redo"
        />

        <!-- Error Notification Toast/Banner if mutation rejects -->
        <div
            v-if="lastError"
            class="flex items-center justify-between border-b border-destructive/30 bg-destructive/10 px-4 py-2 text-xs text-destructive"
        >
            <span>{{ lastError }}</span>
            <button
                type="button"
                class="ml-2 font-semibold underline"
                @click="lastError = null"
            >
                Dismiss
            </button>
        </div>

        <!-- Main Body: 3-column Layout (Component Library, Canvas Frame, Inspector Panel) -->
        <div class="relative flex flex-1 overflow-hidden">
            <!-- Floating toggle button for Left Sidebar when collapsed -->
            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-x-2"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 -translate-x-2"
            >
                <button
                    v-if="!leftSidebarOpen"
                    type="button"
                    class="absolute top-3 left-3 z-30 flex items-center gap-1.5 rounded-lg border bg-background/95 px-2.5 py-1.5 text-xs font-medium text-foreground shadow-sm backdrop-blur transition hover:bg-accent hover:text-accent-foreground"
                    title="Expand Components Library (Ctrl+B)"
                    @click="toggleLeftSidebar"
                >
                    <PanelLeftOpen class="h-3.5 w-3.5 text-primary" />
                    <span class="hidden sm:inline">Components</span>
                </button>
            </transition>

            <!-- Floating toggle button for Right Inspector when collapsed -->
            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 translate-x-2"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-2"
            >
                <button
                    v-if="!rightPanelOpen"
                    type="button"
                    class="absolute top-3 right-3 z-30 flex items-center gap-1.5 rounded-lg border bg-background/95 px-2.5 py-1.5 text-xs font-medium text-foreground shadow-sm backdrop-blur transition hover:bg-accent hover:text-accent-foreground"
                    title="Expand Inspector Panel"
                    @click="toggleRightPanel"
                >
                    <SlidersHorizontal class="h-3.5 w-3.5 text-primary" />
                    <span class="hidden sm:inline">Inspector</span>
                </button>
            </transition>

            <!-- Left Sidebar: Component Library -->
            <transition
                enter-active-class="transition-transform duration-200 ease-out"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-150 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <ComponentLibrary
                    v-show="leftSidebarOpen"
                    :read-only="!can.edit"
                    @insert-component="handleInsertComponent"
                    @close="toggleLeftSidebar"
                />
            </transition>

            <!-- Center: Editor Canvas Frame -->
            <EditorCanvas
                :document="document"
                :viewport="viewport"
                :page-title="page.title"
                :selected-id="selectedNodeId"
                :hovered-id="hoveredNodeId"
                :read-only="!can.edit"
                @select="handleSelectNode"
                @hover="setHoveredNode"
                @clear-selection="clearSelection"
                @move-up="handleMoveUp"
                @move-down="handleMoveDown"
                @duplicate="handleDuplicate"
                @delete="handleDelete"
                @drop-component="handleDropComponent"
            />

            <!-- Right Panel: Inspector -->
            <transition
                enter-active-class="transition-transform duration-200 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-150 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <InspectorPanel
                    v-show="rightPanelOpen"
                    :selected-node="selectedNode"
                    :component-metadata="selectedNodeMetadata"
                    :read-only="!can.edit"
                    :is-root="selectedNode?.id === document.root.id"
                    :current-viewport="viewport"
                    :website-id="website.id"
                    @update-prop="handleUpdateProp"
                    @update-style="handleUpdateStyle"
                    @reset-style="handleResetStyle"
                    @delete-node="handleDelete"
                    @duplicate-node="handleDuplicate"
                    @close="toggleRightPanel"
                />
            </transition>
        </div>
    </div>
</template>
