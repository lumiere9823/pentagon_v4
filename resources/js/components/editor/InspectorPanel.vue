<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Copy,
    Info,
    Lock,
    MousePointer,
    Paintbrush,
    PanelRightClose,
    Settings,
    SlidersHorizontal,
    Trash2,
} from '@lucide/vue';
import MediaLibraryModal from '@/components/media/MediaLibraryModal.vue';
import InspectorContentGroup from './inspector/InspectorContentGroup.vue';
import InspectorQuickActions from './inspector/InspectorQuickActions.vue';
import InspectorSettingsGroup from './inspector/InspectorSettingsGroup.vue';
import InspectorStyleGroup from './inspector/InspectorStyleGroup.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    getInspectorFields,
    validateInspectorValue,
} from '@/lib/inspectorHelpers';
import { isResponsiveProp } from '@/lib/responsive';
import type {
    ComponentMetadata,
    DocumentNode,
    ViewportMode,
} from '@/types/document';
import type { MediaAssetItem } from '@/types/media';

const props = withDefaults(
    defineProps<{
        selectedNode: DocumentNode | null;
        componentMetadata?: ComponentMetadata | null;
        readOnly?: boolean;
        isLocked?: boolean;
        isAncestorLocked?: boolean;
        lockedAncestorLabel?: string | null;
        isRoot?: boolean;
        currentViewport?: ViewportMode;
        websiteId?: number;
    }>(),
    {
        componentMetadata: null,
        readOnly: false,
        isLocked: false,
        isAncestorLocked: false,
        lockedAncestorLabel: null,
        isRoot: false,
        currentViewport: 'desktop',
        websiteId: undefined,
    },
);

const emit = defineEmits<{
    (e: 'updateProp', payload: { key: string; value: unknown }): void;
    (
        e: 'updateStyle',
        payload: {
            viewport: 'tablet' | 'mobile';
            key: string;
            value: unknown;
        },
    ): void;
    (
        e: 'resetStyle',
        payload: { viewport: 'tablet' | 'mobile'; key: string },
    ): void;
    (e: 'deleteNode', nodeId: string): void;
    (e: 'duplicateNode', nodeId: string): void;
    (e: 'close'): void;
}>();

const activeTab = ref<'content' | 'style' | 'settings'>('content');
const isMediaModalOpen = ref(false);
const mediaTargetNodeId = ref<string | null>(null);

const isNodeDirectLocked = computed(
    () => props.isLocked || Boolean(props.selectedNode?.locked),
);
const isProtectedByAncestor = computed(() => Boolean(props.isAncestorLocked));
const isNodeProtected = computed(
    () => isNodeDirectLocked.value || isProtectedByAncestor.value,
);
const isEffectiveReadOnly = computed(
    () => props.readOnly || isNodeProtected.value,
);

const handleResetStyle = (key: string) => {
    if (isEffectiveReadOnly.value || props.currentViewport === 'desktop')
        return;
    emit('resetStyle', { viewport: props.currentViewport, key });
};

const fieldsForGroup = (group: 'content' | 'style' | 'settings') =>
    getInspectorFields(props.componentMetadata, group).filter((field) => {
        if (!props.selectedNode) return false;
        if (field.key === 'display') return field.responsive;
        return (
            field.responsive ===
            isResponsiveProp(props.selectedNode.type, field.key)
        );
    });

const contentProps = computed(() => fieldsForGroup('content'));
const styleProps = computed(() => fieldsForGroup('style'));
const settingsProps = computed(() => fieldsForGroup('settings'));
const inspectorProps = computed(() => [
    ...contentProps.value,
    ...styleProps.value,
    ...settingsProps.value,
]);

// Auto-switch tab if current tab has no properties
watch(
    () => props.selectedNode?.id,
    () => {
        isMediaModalOpen.value = false;
        mediaTargetNodeId.value = null;
        if (contentProps.value.length > 0) {
            activeTab.value = 'content';
        } else if (styleProps.value.length > 0) {
            activeTab.value = 'style';
        } else {
            activeTab.value = 'settings';
        }
    },
);

const handlePropChange = (key: string, value: unknown) => {
    if (isEffectiveReadOnly.value || !props.selectedNode) return false;
    const field = inspectorProps.value.find(
        (candidate) => candidate.key === key,
    );
    if (!field) return false;

    const validation = validateInspectorValue(field, value);
    if (!validation.valid) return false;

    if (field.path === 'styles.*.display') {
        if (props.currentViewport === 'desktop' || !field.responsive) {
            return false;
        }
        emit('updateStyle', {
            viewport: props.currentViewport,
            key,
            value: validation.value,
        });
        return true;
    }

    if (props.currentViewport !== 'desktop' && field.responsive) {
        emit('updateStyle', {
            viewport: props.currentViewport,
            key,
            value: validation.value,
        });
    } else {
        emit('updateProp', { key, value: validation.value });
    }

    return true;
};

// Media Library Picker state and handlers
const openMediaLibrary = () => {
    if (isEffectiveReadOnly.value || !props.selectedNode) return;
    mediaTargetNodeId.value = props.selectedNode.id;
    isMediaModalOpen.value = true;
};

const handleMediaSelect = (asset: MediaAssetItem) => {
    if (
        !props.selectedNode ||
        mediaTargetNodeId.value !== props.selectedNode.id
    ) {
        return;
    }

    // 1. Write canonical public URL to src
    if (!handlePropChange('src', asset.url)) {
        mediaTargetNodeId.value = null;
        return;
    }

    // 2. D-020: Auto-fill alt only when existing alt value is empty
    const currentAlt = props.selectedNode.props.alt;
    const isAltEmpty =
        currentAlt === undefined ||
        currentAlt === null ||
        String(currentAlt).trim() === '';

    if (isAltEmpty && asset.alt_text) {
        handlePropChange('alt', asset.alt_text);
    }

    mediaTargetNodeId.value = null;
};
</script>

<template>
    <aside
        class="flex h-full w-72 shrink-0 flex-col border-l bg-background select-none sm:w-80"
        aria-label="Component Inspector"
    >
        <TooltipProvider :delay-duration="200">
            <!-- Header -->
            <div
                class="flex h-13 items-center justify-between border-b px-3 sm:px-4"
            >
                <div class="flex items-center gap-2 overflow-hidden">
                    <SlidersHorizontal
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                    <h2
                        class="truncate text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Inspector
                    </h2>
                    <Badge
                        v-if="selectedNode"
                        variant="secondary"
                        class="max-w-[110px] truncate font-mono text-[10px]"
                    >
                        {{ selectedNode.type }}
                    </Badge>
                    <Badge v-else variant="outline" class="text-[10px]">
                        No Selection
                    </Badge>
                </div>

                <div class="flex items-center gap-0.5">
                    <!-- Quick Duplicate -->
                    <Tooltip
                        v-if="selectedNode && !isRoot && !isEffectiveReadOnly"
                    >
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                aria-label="Duplicate component"
                                @click="emit('duplicateNode', selectedNode.id)"
                            >
                                <Copy class="h-3.5 w-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            <p class="text-xs">Duplicate component</p>
                        </TooltipContent>
                    </Tooltip>

                    <!-- Quick Delete -->
                    <Tooltip
                        v-if="selectedNode && !isRoot && !isEffectiveReadOnly"
                    >
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                aria-label="Delete component"
                                @click="emit('deleteNode', selectedNode.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            <p class="text-xs">Delete component</p>
                        </TooltipContent>
                    </Tooltip>

                    <!-- Collapse Inspector -->
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                aria-label="Collapse Inspector Panel"
                                @click="emit('close')"
                            >
                                <PanelRightClose class="h-3.5 w-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="left">
                            <p class="text-xs">Collapse Inspector</p>
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>

            <!-- Locked Component Banner -->
            <div
                v-if="isNodeProtected"
                role="status"
                aria-live="polite"
                class="flex items-start gap-2 border-b bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-300"
                data-testid="inspector-lock-banner"
            >
                <Lock
                    class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <div class="flex-1 leading-normal">
                    <span v-if="isProtectedByAncestor">
                        This component is locked by its ancestor{{
                            lockedAncestorLabel
                                ? ` (${lockedAncestorLabel})`
                                : ''
                        }}. Unlock it in the Layers panel to make changes.
                    </span>
                    <span v-else>
                        This component is locked. Unlock it in the Layers panel
                        to make changes.
                    </span>
                </div>
            </div>

            <!-- Read-Only Notice -->
            <div
                v-if="readOnly"
                class="flex items-center gap-2 border-b bg-amber-500/10 px-3 py-1.5 text-xs text-amber-700 dark:text-amber-400"
            >
                <Info class="h-3.5 w-3.5 shrink-0" />
                <span>Viewer mode: modifications disabled</span>
            </div>

            <!-- Active Responsive Viewport Notice -->
            <div
                v-if="currentViewport !== 'desktop'"
                class="flex items-center justify-between border-b bg-amber-500/10 px-3 py-1.5 text-xs text-amber-700 dark:text-amber-400"
            >
                <div class="flex items-center gap-1.5 font-medium capitalize">
                    <SlidersHorizontal class="h-3.5 w-3.5" />
                    <span>Editing {{ currentViewport }} styles</span>
                </div>
                <span class="text-[10px] text-muted-foreground"
                    >Overrides cascade</span
                >
            </div>

            <!-- Unselected State -->
            <div
                v-if="!selectedNode"
                class="flex flex-1 flex-col items-center justify-center p-6 text-center select-none"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl border bg-muted/40 text-muted-foreground shadow-xs"
                >
                    <MousePointer class="h-5 w-5" />
                </div>

                <h3 class="mt-3.5 text-sm font-semibold text-foreground">
                    No Component Selected
                </h3>

                <p
                    class="mt-1.5 max-w-[220px] text-xs leading-relaxed text-muted-foreground"
                >
                    Click any element on the canvas to inspect and configure its
                    content, styles, and properties.
                </p>

                <div
                    class="mt-4 rounded-md border border-dashed border-border/80 bg-muted/20 px-3 py-2 text-[11px] text-muted-foreground"
                >
                    💡 Tip: Drag components from the left sidebar or click to
                    append.
                </div>
            </div>

            <!-- Active Node Inspector -->
            <template v-else>
                <!-- Tabs (Content / Style / Settings) -->
                <div class="flex border-b bg-muted/20 text-xs">
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 border-b-2 py-2.5 font-medium transition-colors"
                        :class="
                            activeTab === 'content'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = 'content'"
                    >
                        <SlidersHorizontal class="h-3 w-3" />
                        <span>Content ({{ contentProps.length }})</span>
                    </button>

                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 border-b-2 py-2.5 font-medium transition-colors"
                        :class="
                            activeTab === 'style'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = 'style'"
                    >
                        <Paintbrush class="h-3 w-3" />
                        <span>Style ({{ styleProps.length }})</span>
                    </button>

                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 border-b-2 py-2.5 font-medium transition-colors"
                        :class="
                            activeTab === 'settings'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = 'settings'"
                    >
                        <Settings class="h-3 w-3" />
                        <span>Settings</span>
                    </button>
                </div>

                <!-- Panel Fields Form Area -->
                <div class="flex-1 space-y-4 overflow-y-auto p-4">
                    <InspectorContentGroup
                        v-if="activeTab === 'content'"
                        :node="selectedNode"
                        :fields="contentProps"
                        :read-only="isEffectiveReadOnly"
                        :website-id="websiteId"
                        @update-prop="
                            handlePropChange($event.key, $event.value)
                        "
                        @open-media-library="openMediaLibrary"
                    />

                    <InspectorStyleGroup
                        v-if="activeTab === 'style'"
                        :node="selectedNode"
                        :fields="styleProps"
                        :read-only="isEffectiveReadOnly"
                        :current-viewport="currentViewport"
                        @update-prop="
                            handlePropChange($event.key, $event.value)
                        "
                        @reset-style="handleResetStyle"
                    />

                    <InspectorSettingsGroup
                        v-if="activeTab === 'settings'"
                        :node="selectedNode"
                        :fields="settingsProps"
                        :read-only="isEffectiveReadOnly"
                        :current-viewport="currentViewport"
                        @update-prop="
                            handlePropChange($event.key, $event.value)
                        "
                        @reset-style="handleResetStyle"
                    />
                </div>

                <InspectorQuickActions
                    :node="selectedNode"
                    :read-only="isEffectiveReadOnly"
                    :is-root="isRoot"
                    @duplicate="emit('duplicateNode', $event)"
                    @delete="emit('deleteNode', $event)"
                />
            </template>
        </TooltipProvider>

        <!-- Media Library Picker Modal -->
        <MediaLibraryModal
            v-if="websiteId"
            v-model:open="isMediaModalOpen"
            :website-id="websiteId"
            :can-manage="!isEffectiveReadOnly"
            @select="handleMediaSelect"
        />
    </aside>
</template>
