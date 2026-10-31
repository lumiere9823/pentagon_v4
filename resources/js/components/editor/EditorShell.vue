<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { AlertCircle, PanelLeftOpen, SlidersHorizontal } from '@lucide/vue';
import ComponentLibrary from './ComponentLibrary.vue';
import ConflictModal from './ConflictModal.vue';
import EditorCanvas from './EditorCanvas.vue';
import EditorToolbar from './EditorToolbar.vue';
import InspectorPanel from './InspectorPanel.vue';
import LayersPanel from './layers/LayersPanel.vue';
import PublishSuccessModal from './PublishSuccessModal.vue';
import ReleasesModal from './ReleasesModal.vue';
import RevisionsModal from './RevisionsModal.vue';
import SeoSettingsModal from './SeoSettingsModal.vue';
import SharePreviewModal from './SharePreviewModal.vue';
import SaveAsTemplateModal from '@/components/templates/SaveAsTemplateModal.vue';
import { useAutosave } from '@/composables/useAutosave';
import { createComponentRegistrySession } from '@/composables/useComponentRegistry';
import { useDocumentState } from '@/composables/useDocumentState';
import { useEditorHistory } from '@/composables/useEditorHistory';
import { useEditorState } from '@/composables/useEditorState';
import { requestJson } from '@/lib/httpClient';
import { createPublishProtocol } from '@/lib/publishProtocol';
import {
    CONTAINER_COMPONENT_TYPES,
    type ComponentMetadata,
    type CoreComponentType,
    type DocumentSchema,
    type TreeMutationResult,
} from '@/types/document';
import type { PageSeoMetadata } from '@/types/seo';

interface WebsiteSummary {
    id: number;
    name: string;
    slug: string;
}

interface PageSummary {
    id: number;
    title: string;
    slug: string;
    is_homepage?: boolean;
    status: 'draft' | 'published' | 'archived' | string;
    lock_version?: number;
    updated_at?: string | null;
    document_json?: DocumentSchema | null;
    seo_metadata?: PageSeoMetadata;
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
    activeLeftTab,
    setViewport,
    toggleLeftSidebar,
    toggleRightPanel,
    setActiveLeftTab,
} = useEditorState();

const handleSelectLeftTab = (tab: 'components' | 'layers') => {
    if (leftSidebarOpen.value && activeLeftTab.value === tab) {
        toggleLeftSidebar();
    } else {
        setActiveLeftTab(tab);
        if (!leftSidebarOpen.value) {
            toggleLeftSidebar();
        }
    }
};

const saveTemplateModalOpen = ref(false);
const revisionsModalOpen = ref(false);
const releasesModalOpen = ref(false);
const sharePreviewModalOpen = ref(false);
const seoModalOpen = ref(false);
const conflictModalOpen = ref(false);
const publishModalOpen = ref(false);
const publishedPublicUrl = ref('');
const seoMetadata = ref<PageSeoMetadata>(props.page.seo_metadata ?? {});

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
    moveNode,
    isNodeLocked,
    isNodeOrAncestorLocked,
    getProtectingAncestor,
    setNodeLocked,
    isNodeHidden,
    getHidingAncestor,
    isNodeOrAncestorHidden,
    setNodeHidden,
    setNodeLabel,
    setDocument,
} = useDocumentState(props.initialDocument);

// Live status region announcement state (R-16)
const liveAnnouncement = ref('');

const announce = (message: string) => {
    liveAnnouncement.value = message;
};

// History management (undo/redo)
const {
    canUndo,
    canRedo,
    pushSnapshot,
    recordDebouncedEdit,
    undo,
    redo,
    flushPending,
    clearHistory,
} = useEditorHistory(
    () => document.value,
    (restoredDoc) => setDocument(restoredDoc),
    {
        maxSnapshots: 30,
        debounceMs: 300,
        onUndo: () => announce('Undo action executed.'),
        onRedo: () => announce('Redo action executed.'),
    },
);

// Autosave & Persistence (M14)
const {
    lockVersion,
    saveStatus,
    isDirty,
    isSaving,
    lastSavedAt,
    errorMessage: autosaveError,
    conflictData,
    notifyChange,
    saveNow,
    forceUpdateLockVersion,
} = useAutosave({
    websiteId: props.website.id,
    pageId: props.page.id,
    initialLockVersion: props.page.lock_version ?? 1,
    getCurrentDocument: () => document.value,
    getSeoMetadata: () => seoMetadata.value,
    onConflict: () => {
        conflictModalOpen.value = true;
    },
});

// Announce autosave state changes (R-16)
watch(
    () => isSaving.value,
    (saving) => {
        if (saving) {
            announce('Saving document...');
        }
    },
);

watch(
    () => saveStatus.value,
    (status, oldStatus) => {
        if (
            status === 'saved' &&
            oldStatus === 'saving' &&
            !isDirty.value &&
            !isSaving.value
        ) {
            announce('All changes saved successfully.');
        } else if (status === 'error') {
            announce(
                'Save failed. ' + (autosaveError.value || 'An error occurred.'),
            );
        } else if (status === 'conflict') {
            announce(
                'Save conflict detected. The page was updated in another session.',
            );
        }
    },
);

let internalDocumentUpdate: string | null = null;

watch(
    () => JSON.stringify(document.value),
    (currentDocument) => {
        if (internalDocumentUpdate !== null) {
            const shouldIgnore = currentDocument === internalDocumentUpdate;
            internalDocumentUpdate = null;
            if (shouldIgnore) return;
        }
        if (props.can.edit) {
            notifyChange();
        }
    },
);

const handleConflictReload = () => {
    if (typeof window !== 'undefined') {
        window.location.reload();
    }
};

const handleConflictOverwrite = async () => {
    if (conflictData.value) {
        forceUpdateLockVersion(conflictData.value.current_lock_version);
        conflictModalOpen.value = false;
        await saveNow('Overwrite after conflict resolution', false);
    }
};

const handleRevisionRestored = (payload: {
    document_json: DocumentSchema;
    lock_version: number;
}) => {
    internalDocumentUpdate = JSON.stringify(payload.document_json);
    const result = setDocument(payload.document_json);
    if (!result.success) return;

    forceUpdateLockVersion(payload.lock_version);
    saveStatus.value = 'saved';
    flushPending();
    clearHistory();
    revisionsModalOpen.value = false;
    announce(
        `Revision restored (Version #${payload.lock_version}). Document history reset.`,
    );
};

const handlePreview = () => {
    if (typeof window !== 'undefined') {
        const url = `/websites/${props.website.id}/pages/${props.page.id}/preview`;
        window.open(url, '_blank');
    }
};

const handleReleaseRolledBack = (release: any) => {
    announce(
        `Public release rolled back to Release #${release.release_number}.`,
    );
};

const publishProtocol = createPublishProtocol({
    saveDraft: () => saveNow('Pre-publish autosave', false),
    publish: async () => {
        const response = await requestJson<any>(
            `/websites/${props.website.id}/pages/${props.page.id}/publish`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            },
        );
        const data = response.data;
        if (!response.ok || !data.success) {
            return { success: false, message: data.message };
        }

        publishedPublicUrl.value = data.public_url;
        publishModalOpen.value = true;
        props.page.status = 'published';
        return { success: true };
    },
});
const isPublishing = publishProtocol.isPublishing;

const handlePublish = async () => {
    if (!props.can.edit || isPublishing.value) return;
    lastError.value = null;
    announce('Publishing page...');

    const result = await publishProtocol.execute(saveStatus.value !== 'saved');
    if (result.status === 'save_failed') {
        lastError.value = conflictData.value
            ? 'Resolve the save conflict before publishing.'
            : autosaveError.value ||
              'The latest draft could not be saved. Publishing was stopped.';
        announce('Publishing stopped: ' + lastError.value);
    } else if (result.status === 'publish_failed') {
        lastError.value = result.message || 'Publishing failed.';
        announce('Publishing failed: ' + lastError.value);
    } else if (result.status === 'exception') {
        lastError.value = result.message;
        announce('Publishing failed: ' + lastError.value);
    } else if (result.status === 'published') {
        announce('Page published successfully.');
    }
};

const isUnpublishing = ref(false);

const handleUnpublish = async () => {
    if (!props.can.edit || isUnpublishing.value) return;
    if (
        typeof window !== 'undefined' &&
        !window.confirm(
            'Are you sure you want to unpublish this page? It will revert to draft status and the public URL will return 404 until published again.',
        )
    ) {
        return;
    }

    isUnpublishing.value = true;
    lastError.value = null;
    announce('Unpublishing page...');

    try {
        const response = await requestJson<any>(
            `/websites/${props.website.id}/pages/${props.page.id}/unpublish`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            },
        );

        if (response.ok && response.data?.success) {
            props.page.status = 'draft';
            announce(
                'Page unpublished successfully. Public URL is now offline.',
            );
        } else {
            lastError.value =
                response.data?.message || 'Failed to unpublish page.';
            announce('Unpublishing failed: ' + lastError.value);
        }
    } catch (e: any) {
        lastError.value =
            e?.message || 'A network error occurred while unpublishing.';
        announce('Unpublishing error: ' + lastError.value);
    } finally {
        isUnpublishing.value = false;
    }
};

// Component Registry metadata for Inspector
const registrySession = createComponentRegistrySession();
const registryMetadata = registrySession.components;
const registryComponents = computed(() =>
    Object.values(registryMetadata.value),
);

onMounted(() => {
    void registrySession.load();
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleGlobalKeyDown);
    }
});

onUnmounted(() => {
    registrySession.dispose();
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleGlobalKeyDown);
    }
});

function handleGlobalKeyDown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        const target = e.target as HTMLElement | null;
        const isInputField =
            target &&
            (target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.isContentEditable);
        if (isInputField) {
            target.blur();
            return;
        }
        if (selectedNodeId.value !== null) {
            clearSelection();
            announce('Selection cleared.');
        }
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
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        if (props.can.edit) {
            saveNow();
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

const editorCanvasRef = ref<{ scrollToNode: (id: string) => void } | null>(
    null,
);

const handleSelectNodeFromLayers = (nodeId: string) => {
    handleSelectNode(nodeId);
    nextTick(() => {
        editorCanvasRef.value?.scrollToNode(nodeId);
    });
};

// Current metadata for selected node
const selectedNodeMetadata = computed(() => {
    if (!selectedNode.value) return null;
    if (typeof registrySession.resolveMetadata === 'function') {
        return registrySession.resolveMetadata(selectedNode.value.type);
    }

    return registryMetadata.value[selectedNode.value.type] ?? null;
});

const selectedNodeIsLocked = computed(() => {
    if (!selectedNodeId.value) return false;
    return isNodeLocked(selectedNodeId.value);
});

const selectedNodeProtectingAncestor = computed(() => {
    if (!selectedNodeId.value) return null;
    return getProtectingAncestor(selectedNodeId.value);
});

const selectedNodeIsAncestorLocked = computed(() => {
    return selectedNodeProtectingAncestor.value !== null;
});

const selectedNodeLockedAncestorLabel = computed(() => {
    const ancestor = selectedNodeProtectingAncestor.value;
    if (!ancestor) return null;
    return registryMetadata.value[ancestor.type]?.label || ancestor.type;
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

let errorDismissTimer: ReturnType<typeof setTimeout> | null = null;

const showMutationError = (message: string) => {
    lastError.value = message;
    announce('Error: ' + message);
    if (errorDismissTimer) {
        clearTimeout(errorDismissTimer);
    }
    errorDismissTimer = setTimeout(() => {
        if (lastError.value === message) {
            lastError.value = null;
        }
    }, 6000);
};

const clearMutationError = () => {
    if (errorDismissTimer) {
        clearTimeout(errorDismissTimer);
        errorDismissTimer = null;
    }
    lastError.value = null;
};

// Handlers for Node Actions
const handleMoveUp = (nodeId: string) => {
    if (!props.can.edit) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = moveNodeRelative(nodeId, 'up');
    if (res.success && res.changed) {
        pushSnapshot(docBefore);
        clearMutationError();
    } else if (!res.success && res.error) {
        showMutationError(res.error);
    }
};

const handleMoveDown = (nodeId: string) => {
    if (!props.can.edit) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = moveNodeRelative(nodeId, 'down');
    if (res.success && res.changed) {
        pushSnapshot(docBefore);
        clearMutationError();
    } else if (!res.success && res.error) {
        showMutationError(res.error);
    }
};

const handleMoveNode = (payload: {
    nodeId: string;
    targetParentId: string;
    targetIndex: number;
}): TreeMutationResult => {
    if (!props.can.edit) {
        const error = 'Permission denied: modifications disabled.';
        showMutationError(error);
        return { success: false, changed: false, error };
    }
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = moveNode(
        payload.nodeId,
        payload.targetParentId,
        payload.targetIndex,
    );
    if (res.success && res.changed) {
        pushSnapshot(docBefore);
        selectNode(payload.nodeId);
        clearMutationError();
    } else if (!res.success && res.error) {
        showMutationError(res.error);
    }
    return res;
};

const handleSetNodeLocked = (payload: {
    nodeId: string;
    locked: boolean;
}): TreeMutationResult => {
    if (!props.can.edit) {
        const error = 'Permission denied: modifications disabled.';
        showMutationError(error);
        return { success: false, changed: false, error };
    }
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = setNodeLocked(payload.nodeId, payload.locked);
    if (res.success && res.changed) {
        pushSnapshot(docBefore);
        clearMutationError();
    } else if (!res.success && res.error) {
        showMutationError(res.error);
    }
    return res;
};

const handleSetNodeHidden = (payload: {
    nodeId: string;
    hidden: boolean;
}): TreeMutationResult => {
    if (!props.can.edit) {
        const error = 'Permission denied: modifications disabled.';
        showMutationError(error);
        return { success: false, changed: false, error };
    }
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = setNodeHidden(payload.nodeId, payload.hidden);
    if (res.success && res.changed) {
        pushSnapshot(docBefore);
        clearMutationError();
    } else if (!res.success && res.error) {
        showMutationError(res.error);
    }
    return res;
};

const handleSetNodeLabel = (payload: {
    nodeId: string;
    label: string | null;
}): TreeMutationResult => {
    if (!props.can.edit) {
        const error = 'Permission denied: modifications disabled.';
        showMutationError(error);
        return { success: false, changed: false, error };
    }
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = setNodeLabel(payload.nodeId, payload.label);
    if (res.success && res.changed) {
        pushSnapshot(docBefore);
        clearMutationError();
    } else if (!res.success && res.error) {
        showMutationError(res.error);
    }
    return res;
};

const handleDuplicate = (nodeId: string) => {
    if (!props.can.edit || nodeId === document.value.root.id) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = duplicateNode(nodeId);
    if (res.success) {
        pushSnapshot(docBefore);
        clearMutationError();
    } else if (res.error) {
        showMutationError(res.error);
    }
};

const handleDelete = (nodeId: string) => {
    if (!props.can.edit || nodeId === document.value.root.id) return;
    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = deleteNode(nodeId);
    if (res.success) {
        pushSnapshot(docBefore);
        clearMutationError();
    } else if (res.error) {
        showMutationError(res.error);
    }
};

const handleUpdateProp = (payload: { key: string; value: unknown }) => {
    if (!props.can.edit || !selectedNodeId.value) return;

    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = updateNodeProps(selectedNodeId.value, {
        [payload.key]: payload.value,
    });
    if (res.success) {
        recordDebouncedEdit(docBefore);
        clearMutationError();
    } else if (res.error) {
        showMutationError(res.error);
    }
};

const handleUpdateStyle = (payload: {
    viewport: 'tablet' | 'mobile';
    key: string;
    value: unknown;
}) => {
    if (!props.can.edit || !selectedNodeId.value) return;

    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = updateNodeStyle(
        selectedNodeId.value,
        payload.viewport,
        payload.key,
        payload.value,
    );
    if (res.success) {
        recordDebouncedEdit(docBefore);
        clearMutationError();
    } else if (res.error) {
        showMutationError(res.error);
    }
};

const handleResetStyle = (payload: {
    viewport: 'tablet' | 'mobile';
    key: string;
}) => {
    if (!props.can.edit || !selectedNodeId.value) return;

    const docBefore = JSON.parse(JSON.stringify(document.value));
    const res = resetNodeStyle(
        selectedNodeId.value,
        payload.viewport,
        payload.key,
    );
    if (res.success) {
        recordDebouncedEdit(docBefore);
        clearMutationError();
    } else if (res.error) {
        showMutationError(res.error);
    }
};

defineExpose({
    document,
    selectedNodeId,
    selectedNode,
    isNodeLocked,
    isNodeOrAncestorLocked,
    getProtectingAncestor,
    selectedNodeIsLocked,
    selectedNodeIsAncestorLocked,
    selectedNodeLockedAncestorLabel,
    setNodeLocked,
    handleSetNodeLocked,
    isNodeHidden,
    getHidingAncestor,
    isNodeOrAncestorHidden,
    setNodeHidden,
    handleSetNodeHidden,
    setNodeLabel,
    handleSetNodeLabel,
    handleMoveNode,
    handleDelete,
    handleDuplicate,
    handleUpdateProp,
    handleUpdateStyle,
    handleResetStyle,
    liveAnnouncement,
    announce,
    clearHistory,
    flushPending,
    canUndo,
    canRedo,
    undo,
    redo,
    revisionsModalOpen,
    releasesModalOpen,
    sharePreviewModalOpen,
    saveTemplateModalOpen,
    publishModalOpen,
    conflictModalOpen,
    handleRevisionRestored,
});
</script>

<template>
    <div
        class="flex h-screen w-full flex-col overflow-hidden bg-background text-foreground antialiased"
    >
        <!-- Accessibility Live Region for Status & Action Announcements (R-16) -->
        <div
            data-testid="editor-live-region"
            role="status"
            aria-live="polite"
            aria-atomic="true"
            class="sr-only"
        >
            {{ liveAnnouncement }}
        </div>

        <!-- Top Toolbar -->
        <EditorToolbar
            :website="website"
            :page="page"
            :viewport="viewport"
            :left-sidebar-open="leftSidebarOpen"
            :right-panel-open="rightPanelOpen"
            :active-left-tab="activeLeftTab"
            :user-role="userRole"
            :can-edit="can.edit"
            :can-undo="canUndo"
            :can-redo="canRedo"
            :save-status="saveStatus"
            :is-saving="isSaving"
            :is-dirty="isDirty"
            :is-publishing="isPublishing"
            @update:viewport="setViewport"
            @toggle-left-sidebar="toggleLeftSidebar"
            @select-left-tab="handleSelectLeftTab"
            @toggle-right-panel="toggleRightPanel"
            @undo="undo"
            @redo="redo"
            @save="saveNow"
            @open-revisions="revisionsModalOpen = true"
            @open-releases="releasesModalOpen = true"
            @open-seo="seoModalOpen = true"
            @save-as-template="saveTemplateModalOpen = true"
            @preview="handlePreview"
            @share-preview="sharePreviewModalOpen = true"
            @publish="handlePublish"
            @unpublish="handleUnpublish"
        />

        <!-- Error Notification Toast/Banner if mutation or autosave rejects -->
        <div
            v-if="lastError || autosaveError"
            role="alert"
            aria-live="assertive"
            class="flex items-center justify-between border-b border-destructive/30 bg-destructive/10 px-4 py-2 text-xs text-destructive"
        >
            <div class="flex items-center gap-2">
                <AlertCircle class="h-4 w-4 shrink-0" />
                <span>{{ lastError || autosaveError }}</span>
            </div>
            <button
                type="button"
                class="ml-2 font-semibold underline hover:opacity-80"
                aria-label="Dismiss error"
                @click="
                    clearMutationError();
                    autosaveError = null;
                "
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
                    title="Expand Sidebar (Ctrl+B)"
                    @click="toggleLeftSidebar"
                >
                    <PanelLeftOpen class="h-3.5 w-3.5 text-primary" />
                    <span class="hidden sm:inline">{{
                        activeLeftTab === 'layers' ? 'Layers' : 'Components'
                    }}</span>
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

            <!-- Left Sidebar: Component Library or Layers Panel -->
            <transition
                enter-active-class="transition-transform duration-200 ease-out"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-150 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <div v-show="leftSidebarOpen" class="flex h-full shrink-0">
                    <ComponentLibrary
                        v-show="activeLeftTab === 'components'"
                        :read-only="!can.edit"
                        :components="registryComponents"
                        :is-loading="registrySession.isLoading.value"
                        :error-message="registrySession.errorMessage.value"
                        :active-tab="activeLeftTab"
                        @insert-component="handleInsertComponent"
                        @retry="registrySession.retry"
                        @close="toggleLeftSidebar"
                        @switch-tab="setActiveLeftTab"
                    />

                    <LayersPanel
                        v-show="activeLeftTab === 'layers'"
                        :document="document"
                        :selected-id="selectedNodeId"
                        :hovered-id="hoveredNodeId"
                        :components="registryMetadata"
                        :read-only="!can.edit"
                        :viewport="viewport"
                        :active-tab="activeLeftTab"
                        @select="handleSelectNodeFromLayers"
                        @hover="setHoveredNode"
                        @close="toggleLeftSidebar"
                        @switch-tab="setActiveLeftTab"
                        @move-node="handleMoveNode"
                        @set-node-locked="handleSetNodeLocked"
                        @set-node-hidden="handleSetNodeHidden"
                        @set-node-label="handleSetNodeLabel"
                    />
                </div>
            </transition>

            <!-- Center: Editor Canvas Frame -->
            <EditorCanvas
                ref="editorCanvasRef"
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
                    :is-locked="selectedNodeIsLocked"
                    :is-ancestor-locked="selectedNodeIsAncestorLocked"
                    :locked-ancestor-label="selectedNodeLockedAncestorLabel"
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

        <SaveAsTemplateModal
            v-model:open="saveTemplateModalOpen"
            :website-id="website.id"
            :document="document"
            @saved="announce('Custom template saved successfully.')"
        />

        <ConflictModal
            :open="conflictModalOpen"
            :server-lock-version="conflictData?.current_lock_version ?? 0"
            :server-updated-at="conflictData?.updated_at"
            @reload="handleConflictReload"
            @overwrite="handleConflictOverwrite"
        />

        <RevisionsModal
            v-model:open="revisionsModalOpen"
            :website-id="website.id"
            :page-id="page.id"
            :can-edit="can.edit"
            @restored="handleRevisionRestored"
        />

        <PublishSuccessModal
            v-model:open="publishModalOpen"
            :public-url="publishedPublicUrl"
            :page-title="page.title"
        />

        <SharePreviewModal
            v-model:open="sharePreviewModalOpen"
            :website-id="website.id"
            :page-id="page.id"
            :page-title="page.title"
        />

        <ReleasesModal
            v-model:open="releasesModalOpen"
            :website-id="website.id"
            :page-id="page.id"
            :can-edit="can.edit"
            @rolled-back="handleReleaseRolledBack"
        />

        <SeoSettingsModal
            v-model:open="seoModalOpen"
            v-model="seoMetadata"
            :website-id="website.id"
            :website-name="website.name"
            :website-slug="website.slug"
            :page-title="page.title"
            :page-slug="page.slug"
            @save="saveNow('Updated SEO metadata')"
        />
    </div>
</template>
