<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    AlertCircle,
    Copy,
    Image as ImageIcon,
    Info,
    Link as LinkIcon,
    MousePointer,
    Paintbrush,
    PanelRightClose,
    Plus,
    Settings,
    SlidersHorizontal,
    Trash2,
} from '@lucide/vue';
import MediaLibraryModal from '@/components/media/MediaLibraryModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    coercePropValue,
    type PropSchemaDefinition,
} from '@/lib/inspectorHelpers';
import {
    getInheritedPropInfo,
    isPropOverridden,
    isResponsiveProp,
    resolveResponsiveProp,
} from '@/lib/responsive';
import type {
    ComponentMetadata,
    CoreComponentType,
    DocumentNode,
    ViewportMode,
} from '@/types/document';
import type { MediaAssetItem } from '@/types/media';

const props = withDefaults(
    defineProps<{
        selectedNode: DocumentNode | null;
        componentMetadata?: ComponentMetadata | null;
        readOnly?: boolean;
        isRoot?: boolean;
        currentViewport?: ViewportMode;
        websiteId?: number;
    }>(),
    {
        componentMetadata: null,
        readOnly: false,
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

function getPropValue(key: string): unknown {
    if (!props.selectedNode) return undefined;
    return resolveResponsiveProp(
        props.selectedNode,
        key,
        props.currentViewport,
    );
}

function isOverridden(key: string): boolean {
    if (!props.selectedNode) return false;
    return isPropOverridden(props.selectedNode, key, props.currentViewport);
}

function getInheritedInfo(key: string): {
    value: unknown;
    source: 'desktop' | 'tablet';
} {
    if (!props.selectedNode) return { value: undefined, source: 'desktop' };
    return getInheritedPropInfo(props.selectedNode, key, props.currentViewport);
}

const handleResetStyle = (key: string) => {
    if (props.readOnly || props.currentViewport === 'desktop') return;
    emit('resetStyle', { viewport: props.currentViewport, key });
};

// Helper to convert camelCase to Human Readable Label
function formatPropLabel(key: string): string {
    return key
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, (str) => str.toUpperCase())
        .trim();
}

// Categorize props into Content, Style, Settings
const CONTENT_PROP_KEYS = new Set([
    'text',
    'content',
    'src',
    'alt',
    'caption',
    'label',
    'url',
    'title',
    'subtitle',
    'ctaText',
    'ctaUrl',
    'items',
]);

const STYLE_PROP_KEYS = new Set([
    'level',
    'align',
    'padding',
    'background',
    'maxWidth',
    'aspectRatio',
    'variant',
    'size',
    'gap',
    'style',
    'color',
    'count',
    'columns',
    'centered',
]);

const propSchemas = computed(() => {
    return props.componentMetadata?.props || {};
});

const contentProps = computed(() => {
    const list: Array<{ key: string; schema: any }> = [];
    for (const [key, schema] of Object.entries(propSchemas.value)) {
        if (CONTENT_PROP_KEYS.has(key)) {
            list.push({ key, schema });
        }
    }
    return list;
});

const styleProps = computed(() => {
    const list: Array<{ key: string; schema: any }> = [];
    for (const [key, schema] of Object.entries(propSchemas.value)) {
        if (STYLE_PROP_KEYS.has(key)) {
            list.push({ key, schema });
        }
    }
    return list;
});

const settingsProps = computed(() => {
    const list: Array<{ key: string; schema: any }> = [];
    for (const [key, schema] of Object.entries(propSchemas.value)) {
        if (!CONTENT_PROP_KEYS.has(key) && !STYLE_PROP_KEYS.has(key)) {
            list.push({ key, schema });
        }
    }
    return list;
});

// Auto-switch tab if current tab has no properties
watch(
    () => props.selectedNode?.id,
    () => {
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
    if (props.readOnly) return;
    const schema = propSchemas.value[key] as PropSchemaDefinition | undefined;
    const resolvedValue = coercePropValue(schema, value);

    if (
        props.currentViewport !== 'desktop' &&
        props.selectedNode &&
        isResponsiveProp(props.selectedNode.type, key)
    ) {
        emit('updateStyle', {
            viewport: props.currentViewport,
            key,
            value: resolvedValue,
        });
    } else {
        emit('updateProp', { key, value: resolvedValue });
    }
};

// Media Library Picker state and handlers
const isMediaModalOpen = ref(false);

const openMediaLibrary = () => {
    isMediaModalOpen.value = true;
};

const handleMediaSelect = (asset: MediaAssetItem) => {
    if (!props.selectedNode) return;

    // 1. Write canonical public URL to src
    handlePropChange('src', asset.url);

    // 2. D-020: Auto-fill alt only when existing alt value is empty
    const currentAlt = props.selectedNode.props.alt;
    const isAltEmpty =
        currentAlt === undefined ||
        currentAlt === null ||
        String(currentAlt).trim() === '';

    if (isAltEmpty && asset.alt_text) {
        handlePropChange('alt', asset.alt_text);
    }
};

// FAQ Item Repeater handlers
const addFaqItem = () => {
    if (props.readOnly || !props.selectedNode) return;
    const currentItems = Array.isArray(props.selectedNode.props.items)
        ? [...props.selectedNode.props.items]
        : [];
    currentItems.push({
        question: 'New Question',
        answer: '<p>Answer details here.</p>',
    });
    emit('updateProp', { key: 'items', value: currentItems });
};

const updateFaqItem = (
    index: number,
    field: 'question' | 'answer',
    value: string,
) => {
    if (props.readOnly || !props.selectedNode) return;
    const currentItems = Array.isArray(props.selectedNode.props.items)
        ? [...props.selectedNode.props.items]
        : [];
    if (currentItems[index]) {
        currentItems[index] = {
            ...currentItems[index],
            [field]: value,
        };
        emit('updateProp', { key: 'items', value: currentItems });
    }
};

const removeFaqItem = (index: number) => {
    if (props.readOnly || !props.selectedNode) return;
    const currentItems = Array.isArray(props.selectedNode.props.items)
        ? [...props.selectedNode.props.items]
        : [];
    currentItems.splice(index, 1);
    emit('updateProp', { key: 'items', value: currentItems });
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
                    <Tooltip v-if="selectedNode && !isRoot && !readOnly">
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
                    <Tooltip v-if="selectedNode && !isRoot && !readOnly">
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
                    <!-- Tab 1: Content Fields -->
                    <div v-if="activeTab === 'content'" class="space-y-4">
                        <div
                            v-if="contentProps.length === 0"
                            class="py-6 text-center text-xs text-muted-foreground"
                        >
                            No content properties for this component.
                        </div>

                        <div
                            v-for="{ key, schema } in contentProps"
                            :key="key"
                            class="space-y-1.5"
                        >
                            <!-- FAQ Array Repeater -->
                            <div
                                v-if="
                                    schema.type === 'array' && key === 'items'
                                "
                                class="space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <Label class="text-xs font-semibold">{{
                                        formatPropLabel(key)
                                    }}</Label>
                                    <Button
                                        v-if="!readOnly"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-6 gap-1 px-2 text-[11px]"
                                        @click="addFaqItem"
                                    >
                                        <Plus class="h-3 w-3" />
                                        <span>Add FAQ</span>
                                    </Button>
                                </div>

                                <div
                                    v-for="(item, idx) in (selectedNode.props[
                                        key
                                    ] as any[]) || []"
                                    :key="idx"
                                    class="space-y-2 rounded-lg border bg-muted/20 p-2.5"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-[10px] font-semibold text-muted-foreground"
                                        >
                                            Item #{{ idx + 1 }}
                                        </span>
                                        <button
                                            v-if="!readOnly"
                                            type="button"
                                            aria-label="Remove item"
                                            class="text-destructive hover:opacity-80"
                                            @click="removeFaqItem(idx)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <Input
                                        :model-value="item.question"
                                        :disabled="readOnly"
                                        placeholder="Question..."
                                        class="h-7 text-xs"
                                        @update:model-value="
                                            updateFaqItem(
                                                idx,
                                                'question',
                                                String($event),
                                            )
                                        "
                                    />
                                    <Textarea
                                        :model-value="item.answer"
                                        :disabled="readOnly"
                                        placeholder="Answer (HTML allowed)..."
                                        class="min-h-12 text-xs"
                                        @update:model-value="
                                            updateFaqItem(
                                                idx,
                                                'answer',
                                                String($event),
                                            )
                                        "
                                    />
                                </div>
                            </div>

                            <!-- RichText / HTML Textarea -->
                            <div v-else-if="schema.isHtml" class="space-y-1">
                                <Label
                                    :for="`prop-${key}`"
                                    class="text-xs font-medium"
                                >
                                    {{ formatPropLabel(key) }}
                                </Label>
                                <Textarea
                                    :id="`prop-${key}`"
                                    :model-value="
                                        String(selectedNode.props[key] || '')
                                    "
                                    :disabled="readOnly"
                                    class="min-h-24 font-mono text-xs"
                                    @update:model-value="
                                        handlePropChange(key, $event)
                                    "
                                />
                                <p class="text-[10px] text-muted-foreground">
                                    Safe HTML is supported. Scripts and event
                                    handlers are automatically sanitized.
                                </p>
                            </div>

                            <!-- URL Input -->
                            <div v-else-if="schema.isUrl" class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <Label
                                        :for="`prop-${key}`"
                                        class="text-xs font-medium"
                                    >
                                        {{ formatPropLabel(key) }}
                                    </Label>
                                    <Button
                                        v-if="
                                            selectedNode.type === 'Image' &&
                                            key === 'src' &&
                                            websiteId
                                        "
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-6 gap-1 px-2 text-[11px]"
                                        :disabled="readOnly"
                                        @click="openMediaLibrary"
                                    >
                                        <ImageIcon class="h-3 w-3" />
                                        Choose from Library
                                    </Button>
                                </div>
                                <div class="relative">
                                    <LinkIcon
                                        class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-muted-foreground"
                                    />
                                    <Input
                                        :id="`prop-${key}`"
                                        :model-value="
                                            String(
                                                selectedNode.props[key] || '',
                                            )
                                        "
                                        :disabled="readOnly"
                                        placeholder="https://..."
                                        class="h-8 pl-8 font-mono text-xs"
                                        @update:model-value="
                                            handlePropChange(key, $event)
                                        "
                                    />
                                </div>
                            </div>

                            <!-- Regular String / Text Input -->
                            <div v-else class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <Label
                                        :for="`prop-${key}`"
                                        class="text-xs font-medium"
                                    >
                                        {{ formatPropLabel(key) }}
                                    </Label>
                                    <span
                                        v-if="schema.maxLength"
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{
                                            String(
                                                selectedNode.props[key] || '',
                                            ).length
                                        }}/{{ schema.maxLength }}
                                    </span>
                                </div>
                                <Input
                                    :id="`prop-${key}`"
                                    :model-value="
                                        String(selectedNode.props[key] || '')
                                    "
                                    :disabled="readOnly"
                                    :maxlength="schema.maxLength"
                                    class="h-8 text-xs"
                                    @update:model-value="
                                        handlePropChange(key, $event)
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Style Fields -->
                    <div v-if="activeTab === 'style'" class="space-y-4">
                        <!-- Component Visibility (Responsive display property) -->
                        <div
                            v-if="selectedNode"
                            class="space-y-1.5 rounded-lg border bg-muted/15 p-2.5"
                        >
                            <div class="flex items-center justify-between">
                                <Label
                                    for="style-visibility"
                                    class="text-xs font-semibold"
                                >
                                    Component Visibility
                                </Label>
                                <div
                                    v-if="
                                        currentViewport !== 'desktop' &&
                                        isOverridden('display')
                                    "
                                    class="flex items-center gap-1.5"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="border border-amber-500/20 bg-amber-500/15 px-1.5 py-0 text-[10px] text-amber-700 dark:text-amber-400"
                                    >
                                        Overridden
                                    </Badge>
                                    <Button
                                        v-if="!readOnly"
                                        variant="ghost"
                                        size="sm"
                                        class="h-5 px-1.5 text-[10px] text-muted-foreground hover:text-foreground"
                                        @click="handleResetStyle('display')"
                                    >
                                        Reset
                                    </Button>
                                </div>
                                <span
                                    v-else-if="currentViewport !== 'desktop'"
                                    class="text-[10px] text-muted-foreground italic"
                                >
                                    Inherited from
                                    {{ getInheritedInfo('display').source }} ({{
                                        getInheritedInfo('display').value ||
                                        'visible'
                                    }})
                                </span>
                            </div>

                            <div
                                v-if="currentViewport === 'desktop'"
                                class="text-[11px] text-muted-foreground"
                            >
                                Visible on desktop. Switch to Tablet or Mobile
                                viewport to hide on specific screen sizes.
                            </div>
                            <div v-else class="mt-1">
                                <select
                                    id="style-visibility"
                                    :value="
                                        getPropValue('display') || 'visible'
                                    "
                                    :disabled="readOnly"
                                    class="h-8 w-full rounded-md border border-input bg-transparent px-2.5 text-xs shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @change="
                                        handlePropChange(
                                            'display',
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option
                                        value="visible"
                                        class="bg-background text-foreground"
                                    >
                                        Visible
                                    </option>
                                    <option
                                        value="hidden"
                                        class="bg-background text-foreground"
                                    >
                                        Hidden
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div
                            v-if="styleProps.length === 0"
                            class="py-4 text-center text-xs text-muted-foreground"
                        >
                            No additional style properties for this component.
                        </div>

                        <div
                            v-for="{ key, schema } in styleProps"
                            :key="key"
                            class="space-y-1.5"
                        >
                            <div class="flex items-center justify-between">
                                <Label
                                    :for="`style-${key}`"
                                    class="text-xs font-medium"
                                >
                                    {{ formatPropLabel(key) }}
                                </Label>

                                <!-- Responsive override indicator & reset -->
                                <div
                                    v-if="
                                        currentViewport !== 'desktop' &&
                                        selectedNode &&
                                        isResponsiveProp(selectedNode.type, key)
                                    "
                                    class="flex items-center gap-1.5"
                                >
                                    <template v-if="isOverridden(key)">
                                        <Badge
                                            variant="secondary"
                                            class="border border-amber-500/20 bg-amber-500/15 px-1.5 py-0 text-[10px] text-amber-700 dark:text-amber-400"
                                        >
                                            Overridden
                                        </Badge>
                                        <Button
                                            v-if="!readOnly"
                                            variant="ghost"
                                            size="sm"
                                            class="h-5 px-1.5 text-[10px] text-muted-foreground hover:text-foreground"
                                            @click="handleResetStyle(key)"
                                        >
                                            Reset
                                        </Button>
                                    </template>
                                    <span
                                        v-else
                                        class="text-[10px] text-muted-foreground italic"
                                    >
                                        Inherited from
                                        {{ getInheritedInfo(key).source }}
                                    </span>
                                </div>
                            </div>

                            <!-- Enum Select / Options -->
                            <select
                                v-if="schema.enum"
                                :id="`style-${key}`"
                                :value="getPropValue(key)"
                                :disabled="readOnly"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2.5 text-xs shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                @change="
                                    handlePropChange(
                                        key,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option
                                    v-for="opt in schema.enum"
                                    :key="opt"
                                    :value="opt"
                                    class="bg-background text-foreground capitalize"
                                >
                                    {{ opt }}
                                </option>
                            </select>

                            <!-- Number / Integer Input -->
                            <Input
                                v-else-if="schema.type === 'integer'"
                                :id="`style-${key}`"
                                type="number"
                                :model-value="Number(getPropValue(key) ?? 0)"
                                :disabled="readOnly"
                                class="h-8 text-xs"
                                @update:model-value="
                                    handlePropChange(key, Number($event))
                                "
                            />

                            <!-- Boolean Input -->
                            <div
                                v-else-if="schema.type === 'boolean'"
                                class="flex items-center justify-between rounded-lg border bg-muted/10 p-2.5"
                            >
                                <Label
                                    :for="`style-${key}`"
                                    class="cursor-pointer text-xs font-medium"
                                >
                                    {{ formatPropLabel(key) }}
                                </Label>
                                <input
                                    :id="`style-${key}`"
                                    type="checkbox"
                                    :checked="Boolean(getPropValue(key))"
                                    :disabled="readOnly"
                                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    @change="
                                        handlePropChange(
                                            key,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                            </div>

                            <!-- Fallback String Input -->
                            <Input
                                v-else
                                :id="`style-${key}`"
                                :model-value="String(getPropValue(key) ?? '')"
                                :disabled="readOnly"
                                class="h-8 text-xs"
                                @update:model-value="
                                    handlePropChange(key, $event)
                                "
                            />
                        </div>
                    </div>

                    <!-- Tab 3: Settings Fields -->
                    <div v-if="activeTab === 'settings'" class="space-y-4">
                        <!-- Additional Props -->
                        <div
                            v-for="{ key, schema } in settingsProps"
                            :key="key"
                            class="space-y-1.5"
                        >
                            <!-- Header with override indicator if responsive -->
                            <div
                                v-if="
                                    currentViewport !== 'desktop' &&
                                    selectedNode &&
                                    isResponsiveProp(selectedNode.type, key)
                                "
                                class="flex items-center justify-between"
                            >
                                <Label
                                    :for="`setting-${key}`"
                                    class="text-xs font-medium"
                                >
                                    {{ formatPropLabel(key) }}
                                </Label>
                                <div class="flex items-center gap-1.5">
                                    <template v-if="isOverridden(key)">
                                        <Badge
                                            variant="secondary"
                                            class="border border-amber-500/20 bg-amber-500/15 px-1.5 py-0 text-[10px] text-amber-700 dark:text-amber-400"
                                        >
                                            Overridden
                                        </Badge>
                                        <Button
                                            v-if="!readOnly"
                                            variant="ghost"
                                            size="sm"
                                            class="h-5 px-1.5 text-[10px] text-muted-foreground hover:text-foreground"
                                            @click="handleResetStyle(key)"
                                        >
                                            Reset
                                        </Button>
                                    </template>
                                    <span
                                        v-else
                                        class="text-[10px] text-muted-foreground italic"
                                    >
                                        Inherited from
                                        {{ getInheritedInfo(key).source }}
                                    </span>
                                </div>
                            </div>

                            <!-- Boolean Checkbox / Switch -->
                            <div
                                v-if="schema.type === 'boolean'"
                                class="flex items-center justify-between rounded-lg border bg-muted/10 p-2.5"
                            >
                                <Label
                                    :for="`setting-${key}`"
                                    class="cursor-pointer text-xs font-medium"
                                >
                                    {{ formatPropLabel(key) }}
                                </Label>
                                <input
                                    :id="`setting-${key}`"
                                    type="checkbox"
                                    :checked="Boolean(getPropValue(key))"
                                    :disabled="readOnly"
                                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    @change="
                                        handlePropChange(
                                            key,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                            </div>

                            <!-- Enum Select for settings (e.g. target) -->
                            <div v-else-if="schema.enum" class="space-y-1">
                                <Label
                                    v-if="
                                        currentViewport === 'desktop' ||
                                        !selectedNode ||
                                        !isResponsiveProp(
                                            selectedNode.type,
                                            key,
                                        )
                                    "
                                    :for="`setting-${key}`"
                                    class="text-xs font-medium"
                                >
                                    {{ formatPropLabel(key) }}
                                </Label>
                                <select
                                    :id="`setting-${key}`"
                                    :value="getPropValue(key)"
                                    :disabled="readOnly"
                                    class="h-8 w-full rounded-md border border-input bg-transparent px-2.5 text-xs shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @change="
                                        handlePropChange(
                                            key,
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option
                                        v-for="opt in schema.enum"
                                        :key="opt"
                                        :value="opt"
                                        class="bg-background text-foreground capitalize"
                                    >
                                        {{ opt }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Node Diagnostic Information -->
                        <div
                            class="space-y-1.5 rounded-lg border bg-muted/20 p-3 text-xs"
                        >
                            <div
                                class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Node Identity
                            </div>
                            <div
                                class="flex items-center justify-between text-muted-foreground"
                            >
                                <span>ID:</span>
                                <span
                                    class="font-mono text-[10px] text-foreground"
                                    >{{ selectedNode.id }}</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between text-muted-foreground"
                            >
                                <span>Type:</span>
                                <span class="font-medium text-foreground">{{
                                    selectedNode.type
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between text-muted-foreground"
                            >
                                <span>Children:</span>
                                <span class="font-mono text-foreground">
                                    {{
                                        selectedNode.children
                                            ? selectedNode.children.length
                                            : 0
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer: Quick Actions (if not Root) -->
                <div
                    v-if="!readOnly && selectedNode && !isRoot"
                    class="flex items-center gap-2 border-t bg-muted/10 p-3"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1 text-xs"
                        @click="emit('duplicateNode', selectedNode.id)"
                    >
                        <Copy class="mr-1.5 h-3.5 w-3.5" />
                        Duplicate
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1 border-destructive/30 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="emit('deleteNode', selectedNode.id)"
                    >
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                        Delete
                    </Button>
                </div>
            </template>
        </TooltipProvider>

        <!-- Media Library Picker Modal -->
        <MediaLibraryModal
            v-if="websiteId"
            v-model:open="isMediaModalOpen"
            :website-id="websiteId"
            :can-manage="!readOnly"
            @select="handleMediaSelect"
        />
    </aside>
</template>
