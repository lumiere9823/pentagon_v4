<script setup lang="ts">
import { ref } from 'vue';
import {
    Info,
    MousePointer,
    Paintbrush,
    Settings,
    SlidersHorizontal,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const activeTab = ref<'content' | 'style' | 'settings'>('content');

const tabs = [
    { id: 'content' as const, label: 'Content', icon: SlidersHorizontal },
    { id: 'style' as const, label: 'Style', icon: Paintbrush },
    { id: 'settings' as const, label: 'Settings', icon: Settings },
];
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <aside
            class="flex h-full w-72 shrink-0 flex-col border-l bg-background sm:w-80"
            aria-label="Component Inspector"
        >
            <!-- Inspector Header -->
            <div class="flex h-12 items-center justify-between border-b px-3">
                <div class="flex items-center gap-2">
                    <SlidersHorizontal class="h-4 w-4 text-muted-foreground" />
                    <h2
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Inspector
                    </h2>
                </div>
                <Badge variant="outline" class="text-[10px]">
                    No Selection
                </Badge>
            </div>

            <!-- Inactive Inspector Tabs (Phase 05 readiness) -->
            <div class="flex border-b bg-muted/20">
                <Tooltip v-for="tab in tabs" :key="tab.id">
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center gap-1.5 border-b-2 py-2 text-xs font-medium opacity-60 transition-colors"
                            :class="
                                activeTab === tab.id
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground'
                            "
                        >
                            <component :is="tab.icon" class="h-3 w-3" />
                            <span>{{ tab.label }}</span>
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p class="text-xs">
                            Property forms will be activated upon node selection
                            in Phase 05
                        </p>
                    </TooltipContent>
                </Tooltip>
            </div>

            <!-- Inspector Content Body: Empty / Unselected Node State -->
            <div
                class="flex flex-1 flex-col items-center justify-center p-6 text-center"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                >
                    <MousePointer class="h-6 w-6" />
                </div>

                <h3 class="mt-3 text-sm font-semibold text-foreground">
                    No Component Selected
                </h3>

                <p class="mt-1 max-w-[220px] text-xs text-muted-foreground">
                    Select a component from the canvas or component tree to
                    inspect its props, typography, spacing, and styles.
                </p>

                <!-- Information Box -->
                <div
                    class="mt-6 w-full rounded-lg border border-border/60 bg-muted/30 p-3 text-left"
                >
                    <div
                        class="flex items-center gap-1.5 text-xs font-medium text-foreground"
                    >
                        <Info class="h-3.5 w-3.5 text-primary" />
                        <span>Phase 05 Integration</span>
                    </div>
                    <p
                        class="mt-1 text-[11px] leading-relaxed text-muted-foreground"
                    >
                        When a component node is active, this panel will
                        automatically render schema-driven property inputs
                        corresponding to the selected Core Component definition.
                    </p>
                </div>
            </div>

            <!-- Footer Meta -->
            <div
                class="border-t bg-muted/20 px-3 py-2 text-[10px] text-muted-foreground"
            >
                Inspector Shell v1.0
            </div>
        </aside>
    </TooltipProvider>
</template>
