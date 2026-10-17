<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import {
    AlertCircle,
    Box,
    Columns,
    FileText,
    Grid3X3,
    Heading,
    HelpCircle,
    Image,
    Minus,
    MousePointerClick,
    MoveVertical,
    PanelLeftClose,
    Plus,
    RefreshCw,
    Search,
    Sparkles,
    Square,
    X,
} from '@lucide/vue';
import {
    COMPONENT_CATEGORIES,
    type ComponentCategoryId,
} from '@/config/editor';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    ComponentMetadata,
    ComponentRegistryResponse,
    CoreComponentType,
} from '@/types/document';

const props = withDefaults(
    defineProps<{
        readOnly?: boolean;
    }>(),
    {
        readOnly: false,
    },
);

const emit = defineEmits<{
    (e: 'insertComponent', type: CoreComponentType): void;
    (e: 'close'): void;
}>();

const components = ref<ComponentMetadata[]>([]);
const isLoading = ref<boolean>(true);
const errorMessage = ref<string | null>(null);

const searchQuery = ref<string>('');
const activeCategory = ref<ComponentCategoryId>('all');

// Map component types to Lucide icons
const iconMap: Record<CoreComponentType, any> = {
    Section: Square,
    Container: Box,
    Heading: Heading,
    RichText: FileText,
    Image: Image,
    Button: MousePointerClick,
    Hero: Sparkles,
    Columns: Columns,
    CardGrid: Grid3X3,
    FAQ: HelpCircle,
    Spacer: MoveVertical,
    Divider: Minus,
};

const getComponentIcon = (type: CoreComponentType) => {
    return iconMap[type] || Box;
};

// Fetch registry metadata from backend
const fetchRegistry = async () => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await fetch('/api/components/registry', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(
                `Failed to load registry: ${response.status} ${response.statusText}`,
            );
        }

        const data: ComponentRegistryResponse = await response.json();

        if (!data || typeof data.components !== 'object') {
            throw new Error('Invalid registry contract received from server.');
        }

        components.value = Object.values(data.components);
    } catch (err) {
        errorMessage.value =
            err instanceof Error
                ? err.message
                : 'An unexpected error occurred while fetching the component registry.';
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchRegistry();
});

// Filter components based on category and search query
const filteredComponents = computed(() => {
    return components.value.filter((comp) => {
        const matchesCategory =
            activeCategory.value === 'all' ||
            comp.category === activeCategory.value;

        const q = searchQuery.value.trim().toLowerCase();
        const matchesSearch =
            q === '' ||
            comp.label.toLowerCase().includes(q) ||
            comp.type.toLowerCase().includes(q) ||
            comp.category.toLowerCase().includes(q);

        return matchesCategory && matchesSearch;
    });
});

// Group components by category
const groupedComponents = computed(() => {
    const groups: Record<string, ComponentMetadata[]> = {};

    filteredComponents.value.forEach((comp) => {
        const cat = comp.category;
        if (!groups[cat]) {
            groups[cat] = [];
        }
        groups[cat].push(comp);
    });

    return groups;
});

// HTML5 Drag-and-Drop start
const handleDragStart = (e: DragEvent, type: CoreComponentType) => {
    if (props.readOnly) {
        e.preventDefault();
        return;
    }
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.setData('application/json', JSON.stringify({ type }));
        // Fallback plain text
        e.dataTransfer.setData('text/plain', type);
    }
};

const handleItemClick = (type: CoreComponentType) => {
    if (props.readOnly) return;
    emit('insertComponent', type);
};
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <aside
            class="flex h-full w-72 shrink-0 flex-col border-r bg-background select-none sm:w-80"
            aria-label="Component Library"
        >
            <!-- Header & Search -->
            <div class="flex flex-col gap-2.5 border-b p-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Components
                        </h2>
                        <Badge
                            v-if="!isLoading && !errorMessage"
                            variant="secondary"
                            class="h-5 px-1.5 text-[10px]"
                        >
                            {{ filteredComponents.length }}
                        </Badge>
                    </div>

                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7 text-muted-foreground hover:text-foreground"
                                aria-label="Collapse Components Sidebar"
                                @click="emit('close')"
                            >
                                <PanelLeftClose class="h-3.5 w-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="right">
                            <p class="text-xs">Collapse Sidebar</p>
                        </TooltipContent>
                    </Tooltip>
                </div>

                <!-- Search Input -->
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search components..."
                        aria-label="Search components"
                        class="h-8 pr-7 pl-8 text-xs"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        aria-label="Clear search"
                        class="absolute top-2 right-2 text-muted-foreground hover:text-foreground"
                        @click="searchQuery = ''"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>

                <!-- Category Filter Pills -->
                <div
                    class="no-scrollbar flex items-center gap-1 overflow-x-auto pb-0.5"
                >
                    <button
                        v-for="cat in COMPONENT_CATEGORIES"
                        :key="cat.id"
                        type="button"
                        class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-medium transition-colors"
                        :class="
                            activeCategory === cat.id
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                        "
                        @click="activeCategory = cat.id"
                    >
                        {{ cat.label }}
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-3">
                <!-- Loading State -->
                <div v-if="isLoading" class="space-y-3">
                    <div v-for="i in 6" :key="i" class="space-y-1.5">
                        <Skeleton class="h-12 w-full rounded-lg" />
                    </div>
                </div>

                <!-- Error State -->
                <div v-else-if="errorMessage" class="py-4">
                    <Alert variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle class="text-xs font-semibold">
                            Failed to load components
                        </AlertTitle>
                        <AlertDescription class="text-xs">
                            {{ errorMessage }}
                        </AlertDescription>
                    </Alert>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3 w-full gap-1.5 text-xs"
                        @click="fetchRegistry"
                    >
                        <RefreshCw class="h-3 w-3" />
                        Retry Connection
                    </Button>
                </div>

                <!-- Empty Search State -->
                <div
                    v-else-if="filteredComponents.length === 0"
                    class="flex flex-col items-center justify-center py-10 text-center"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
                    >
                        <Search class="h-5 w-5" />
                    </div>
                    <p class="mt-2 text-xs font-medium text-foreground">
                        No components found
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        Try adjusting your search query or category filter.
                    </p>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="mt-2 h-7 text-xs text-primary"
                        @click="
                            searchQuery = '';
                            activeCategory = 'all';
                        "
                    >
                        Reset filters
                    </Button>
                </div>

                <!-- Component List (Categorized) -->
                <div v-else class="space-y-4">
                    <div
                        v-for="(comps, categoryName) in groupedComponents"
                        :key="categoryName"
                        class="space-y-1.5"
                    >
                        <div
                            class="text-[11px] font-semibold tracking-wider text-muted-foreground/80 uppercase"
                        >
                            {{ categoryName }}
                        </div>

                        <div class="grid grid-cols-1 gap-1.5">
                            <Tooltip v-for="comp in comps" :key="comp.type">
                                <TooltipTrigger as-child>
                                    <div
                                        tabindex="0"
                                        :draggable="!readOnly"
                                        :class="[
                                            'group flex items-center justify-between rounded-lg border border-border/60 bg-card p-2 text-left shadow-2xs transition-all',
                                            readOnly
                                                ? 'cursor-not-allowed opacity-60'
                                                : 'cursor-grab hover:border-primary/40 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing',
                                        ]"
                                        @dragstart="
                                            handleDragStart($event, comp.type)
                                        "
                                        @click="handleItemClick(comp.type)"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-md bg-muted text-muted-foreground transition-colors group-hover:bg-primary/10 group-hover:text-primary"
                                            >
                                                <component
                                                    :is="
                                                        getComponentIcon(
                                                            comp.type,
                                                        )
                                                    "
                                                    class="h-4 w-4"
                                                />
                                            </div>
                                            <div>
                                                <div
                                                    class="text-xs font-medium text-foreground"
                                                >
                                                    {{ comp.label }}
                                                </div>
                                                <div
                                                    class="text-[10px] text-muted-foreground"
                                                >
                                                    {{
                                                        comp.allowChildren
                                                            ? 'Container'
                                                            : 'Leaf Component'
                                                    }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1.5">
                                            <Badge
                                                variant="outline"
                                                class="text-[9px] font-normal opacity-80"
                                            >
                                                {{ comp.category }}
                                            </Badge>
                                            <Button
                                                v-if="!readOnly"
                                                variant="ghost"
                                                size="icon"
                                                class="h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100"
                                                title="Click to insert"
                                                @click.stop="
                                                    handleItemClick(comp.type)
                                                "
                                            >
                                                <Plus class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent side="right" :side-offset="8">
                                    <div class="space-y-1 text-xs">
                                        <div class="font-semibold">
                                            {{ comp.label }}
                                        </div>
                                        <div
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Type: {{ comp.type }}
                                            {{
                                                comp.allowChildren
                                                    ? '(Holds children)'
                                                    : ''
                                            }}
                                        </div>
                                        <div
                                            v-if="!readOnly"
                                            class="text-[10px] text-primary"
                                        >
                                            Click to insert or drag to canvas
                                        </div>
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Notice -->
            <div
                class="border-t bg-muted/30 px-3 py-2 text-[10px] text-muted-foreground"
            >
                <span class="font-medium">Drag-and-Drop:</span> Drag any item to
                canvas or click to append.
            </div>
        </aside>
    </TooltipProvider>
</template>
