<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
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
        window.addEventListener('keydown', handleGlobalEscape);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleGlobalEscape);
    }
});

function handleGlobalEscape(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        clearSelection();
    }
}

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
    const res = insertNode(payload.type, payload.parentId, 'inside');

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
    if (!props.can.edit) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = duplicateNode(nodeId);
    if (res.success) {
        pushSnapshot(docBefore);
    }
};

const handleDelete = (nodeId: string) => {
    if (!props.can.edit) return;
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
</script>

<template>
    <div
        class="flex h-screen w-screen flex-col overflow-hidden bg-background text-foreground antialiased"
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
                @select="selectNode"
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
                    @update-prop="handleUpdateProp"
                    @delete-node="handleDelete"
                />
            </transition>
        </div>
    </div>
</template>
