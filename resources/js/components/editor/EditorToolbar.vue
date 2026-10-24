<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Bookmark,
    CheckCircle2,
    Clock,
    Eye,
    Globe,
    History,
    LayoutGrid,
    Loader2,
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
        saveStatus?: 'saved' | 'saving' | 'dirty' | 'error' | 'conflict';
        isSaving?: boolean;
        isDirty?: boolean;
        isPublishing?: boolean;
    }>(),
    {
        canUndo: false,
        canRedo: false,
        saveStatus: 'saved',
        isSaving: false,
        isDirty: false,
        isPublishing: false,
    },
);

const emit = defineEmits<{
    (e: 'update:viewport', value: ViewportMode): void;
    (e: 'toggleLeftSidebar'): void;
    (e: 'toggleRightPanel'): void;
    (e: 'undo'): void;
    (e: 'redo'): void;
    (e: 'saveAsTemplate'): void;
    (e: 'save'): void;
    (e: 'openRevisions'): void;
    (e: 'openSeo'): void;
    (e: 'publish'): void;
    (e: 'preview'): void;
    (e: 'sharePreview'): void;
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

                <!-- Action Buttons: Preview & Publish enabled in Phase 08 -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="hidden h-8 gap-1 text-xs md:inline-flex"
                            @click="emit('preview')"
                        >
                            <Eye class="h-3.5 w-3.5" />
                            <span>Preview</span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Open full-page draft preview in a new tab
                        </p>
                    </TooltipContent>
                </Tooltip>

                <Tooltip v-if="canEdit">
                    <TooltipTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1 text-xs"
                            @click="emit('saveAsTemplate')"
                        >
                            <Bookmark class="h-3.5 w-3.5" />
                            <span class="hidden md:inline"
                                >Save as Template</span
                            >
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Save current canvas layout as a custom template
                        </p>
                    </TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 gap-1 text-xs"
                            @click="emit('openRevisions')"
                        >
                            <History class="h-3.5 w-3.5" />
                            <span class="hidden md:inline">Revisions</span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Browse and restore immutable revision snapshots
                        </p>
                    </TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 gap-1 text-xs"
                            @click="emit('openSeo')"
                        >
                            <Globe class="h-3.5 w-3.5" />
                            <span class="hidden md:inline">SEO</span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Configure page metadata, OpenGraph cards, and search
                            engine visibility
                        </p>
                    </TooltipContent>
                </Tooltip>

                <Tooltip v-if="canEdit">
                    <TooltipTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isSaving"
                            class="h-8 gap-1.5 text-xs transition-colors"
                            :class="{
                                'border-destructive/40 text-destructive':
                                    saveStatus === 'error' ||
                                    saveStatus === 'conflict',
                                'border-amber-500/40 text-amber-600 dark:text-amber-400':
                                    saveStatus === 'dirty',
                                'text-muted-foreground':
                                    saveStatus === 'saved' && !isDirty,
                            }"
                            @click="emit('save')"
                        >
                            <Loader2
                                v-if="isSaving"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <AlertTriangle
                                v-else-if="saveStatus === 'conflict'"
                                class="h-3.5 w-3.5"
                            />
                            <AlertCircle
                                v-else-if="saveStatus === 'error'"
                                class="h-3.5 w-3.5"
                            />
                            <CheckCircle2
                                v-else-if="saveStatus === 'saved' && !isDirty"
                                class="h-3.5 w-3.5 text-green-500"
                            />
                            <Save v-else class="h-3.5 w-3.5" />

                            <span class="hidden sm:inline">
                                {{
                                    isSaving
                                        ? 'Saving...'
                                        : saveStatus === 'conflict'
                                          ? 'Conflict'
                                          : saveStatus === 'error'
                                            ? 'Save Error'
                                            : isDirty
                                              ? 'Save Draft*'
                                              : 'Saved'
                                }}
                            </span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            {{
                                saveStatus === 'conflict'
                                    ? 'Conflict detected — click to resolve'
                                    : isDirty
                                      ? 'Save Draft (Ctrl+S)'
                                      : 'All changes saved'
                            }}
                        </p>
                    </TooltipContent>
                </Tooltip>

                <Tooltip v-if="canEdit">
                    <TooltipTrigger as-child>
                        <Button
                            variant="default"
                            size="sm"
                            :disabled="isPublishing || isSaving"
                            class="h-8 gap-1.5 bg-primary text-xs text-primary-foreground transition-all hover:bg-primary/90"
                            @click="emit('publish')"
                        >
                            <Loader2
                                v-if="isPublishing"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <UploadCloud v-else class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">
                                {{ isPublishing ? 'Publishing...' : 'Publish' }}
                            </span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Compile and publish static release to public web
                        </p>
                    </TooltipContent>
                </Tooltip>
            </div>
        </header>
    </TooltipProvider>
</template>
