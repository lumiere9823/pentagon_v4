<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    Eye,
    LayoutGrid,
    PanelLeftClose,
    PanelLeftOpen,
    PanelRightClose,
    PanelRightOpen,
    Redo2,
    Save,
    Undo2,
    UploadCloud,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import ViewportSwitcher from './ViewportSwitcher.vue';
import type { ViewportMode } from '@/types/document';

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

const props = withDefaults(
    defineProps<{
        website: WebsiteSummary;
        page: PageSummary;
        viewport: ViewportMode;
        leftSidebarOpen: boolean;
        rightPanelOpen: boolean;
        userRole: string;
        canEdit: boolean;
        canUndo?: boolean;
        canRedo?: boolean;
    }>(),
    {
        canUndo: false,
        canRedo: false,
    },
);

const emit = defineEmits<{
    (e: 'update:viewport', value: ViewportMode): void;
    (e: 'toggleLeftSidebar'): void;
    (e: 'toggleRightPanel'): void;
    (e: 'undo'): void;
    (e: 'redo'): void;
}>();

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'published':
            return {
                label: 'Published',
                variant: 'default' as const,
                icon: CheckCircle2,
            };
        case 'archived':
            return {
                label: 'Archived',
                variant: 'destructive' as const,
                icon: Clock,
            };
        default:
            return {
                label: 'Draft',
                variant: 'secondary' as const,
                icon: Clock,
            };
    }
};
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <header
            class="flex h-13 w-full shrink-0 items-center justify-between border-b bg-background px-3 select-none sm:px-4"
        >
            <!-- Left Area: Navigation & Page Identity -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <!-- App Sidebar Trigger (Collapses/Expands Navigation Sidebar) -->
                <SidebarTrigger class="h-8 w-8" />

                <!-- Back to Website Pages Button -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            as-child
                        >
                            <Link
                                :href="`/websites/${website.id}`"
                                aria-label="Back to Website Pages"
                            >
                                <ArrowLeft class="h-4 w-4" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">Back to Website Pages</p>
                    </TooltipContent>
                </Tooltip>

                <!-- Component Library Trigger (Collapses/Expands Component Library) -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :class="{
                                'bg-accent text-accent-foreground':
                                    leftSidebarOpen,
                            }"
                            :aria-label="
                                leftSidebarOpen
                                    ? 'Collapse Component Library'
                                    : 'Expand Component Library'
                            "
                            @click="emit('toggleLeftSidebar')"
                        >
                            <LayoutGrid class="h-4 w-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            {{
                                leftSidebarOpen
                                    ? 'Collapse Components (Ctrl+B)'
                                    : 'Expand Components (Ctrl+B)'
                            }}
                        </p>
                    </TooltipContent>
                </Tooltip>

                <div class="h-4 w-px bg-border" />

                <!-- Page Meta Information -->
                <div class="flex items-center gap-2 overflow-hidden">
                    <span
                        class="hidden text-xs text-muted-foreground md:inline"
                    >
                        {{ website.name }} /
                    </span>
                    <span
                        class="truncate text-sm font-semibold text-foreground"
                    >
                        {{ page.title }}
                    </span>
                    <Badge
                        :variant="getStatusBadge(page.status).variant"
                        class="hidden text-[10px] capitalize sm:inline-flex"
                    >
                        <component
                            :is="getStatusBadge(page.status).icon"
                            class="mr-1 h-2.5 w-2.5"
                        />
                        {{ getStatusBadge(page.status).label }}
                    </Badge>
                </div>
            </div>

            <!-- Center Area: Viewport Switcher & History Actions -->
            <div class="flex items-center gap-3">
                <!-- Undo / Redo Actions -->
                <div class="flex items-center gap-0.5">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                :disabled="!canEdit || !canUndo"
                                class="h-8 w-8"
                                aria-label="Undo"
                                @click="emit('undo')"
                            >
                                <Undo2 class="h-3.5 w-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            <p class="text-xs">Undo (Ctrl+Z)</p>
                        </TooltipContent>
                    </Tooltip>

                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                :disabled="!canEdit || !canRedo"
                                class="h-8 w-8"
                                aria-label="Redo"
                                @click="emit('redo')"
                            >
                                <Redo2 class="h-3.5 w-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            <p class="text-xs">Redo (Ctrl+Y or Ctrl+Shift+Z)</p>
                        </TooltipContent>
                    </Tooltip>
                </div>

                <div class="hidden h-4 w-px bg-border sm:block" />

                <!-- Viewport Switcher -->
                <ViewportSwitcher
                    :model-value="viewport"
                    @update:model-value="emit('update:viewport', $event)"
                />
            </div>

            <!-- Right Area: Role & Action Placeholders -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <!-- Role / Read-only Badge -->
                <Badge
                    v-if="!canEdit"
                    variant="outline"
                    class="border-amber-500/30 bg-amber-500/10 text-[11px] text-amber-700 dark:text-amber-400"
                >
                    <Eye class="mr-1 h-3 w-3" />
                    Read Only (Viewer)
                </Badge>
                <Badge
                    v-else
                    variant="outline"
                    class="hidden text-[11px] text-muted-foreground lg:inline-flex"
                >
                    Editor
                </Badge>

                <!-- Inspector Toggle -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :aria-label="
                                rightPanelOpen
                                    ? 'Collapse Inspector Panel'
                                    : 'Expand Inspector Panel'
                            "
                            @click="emit('toggleRightPanel')"
                        >
                            <component
                                :is="
                                    rightPanelOpen
                                        ? PanelRightClose
                                        : PanelRightOpen
                                "
                                class="h-4 w-4"
                            />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            {{
                                rightPanelOpen
                                    ? 'Collapse Inspector'
                                    : 'Expand Inspector'
                            }}
                        </p>
                    </TooltipContent>
                </Tooltip>

                <div class="h-4 w-px bg-border" />

                <!-- Action Button Placeholders: Explicitly disabled per Phase 04/05 non-goals -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled
                            class="hidden h-8 gap-1 text-xs opacity-60 md:inline-flex"
                        >
                            <Eye class="h-3.5 w-3.5" />
                            <span>Preview</span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Preview will be enabled in Phase 08
                        </p>
                    </TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled
                            class="h-8 gap-1 text-xs opacity-60"
                        >
                            <Save class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Save Draft</span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Autosave & Persistence will be enabled in Phase 07
                        </p>
                    </TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="default"
                            size="sm"
                            disabled
                            class="h-8 gap-1 text-xs opacity-60"
                        >
                            <UploadCloud class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Publish</span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Publishing pipeline will be enabled in Phase 08
                        </p>
                    </TooltipContent>
                </Tooltip>
            </div>
        </header>
    </TooltipProvider>
</template>
