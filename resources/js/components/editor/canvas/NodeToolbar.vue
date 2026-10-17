<script setup lang="ts">
import { ArrowDown, ArrowUp, Copy, Trash2 } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{
    componentType: string;
    isRoot: boolean;
    canMoveUp: boolean;
    canMoveDown: boolean;
}>();

const emit = defineEmits<{
    (e: 'moveUp'): void;
    (e: 'moveDown'): void;
    (e: 'duplicate'): void;
    (e: 'delete'): void;
}>();
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <div
            class="absolute -top-9 left-2 z-30 flex items-center gap-0.5 rounded-md border border-primary/40 bg-primary px-1.5 py-0.5 text-primary-foreground shadow-md transition-opacity select-none"
            @click.stop
        >
            <span class="mr-1 text-[11px] font-semibold tracking-wide">
                {{ componentType }}
            </span>

            <template v-if="!isRoot">
                <div class="mx-0.5 h-3 w-px bg-primary-foreground/30" />

                <!-- Move Up -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Move Up"
                            :disabled="!canMoveUp"
                            class="flex h-5 w-5 items-center justify-center rounded-sm hover:bg-white/20 disabled:opacity-30 disabled:hover:bg-transparent"
                            @click="emit('moveUp')"
                        >
                            <ArrowUp class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">Move Up</p>
                    </TooltipContent>
                </Tooltip>

                <!-- Move Down -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Move Down"
                            :disabled="!canMoveDown"
                            class="flex h-5 w-5 items-center justify-center rounded-sm hover:bg-white/20 disabled:opacity-30 disabled:hover:bg-transparent"
                            @click="emit('moveDown')"
                        >
                            <ArrowDown class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">Move Down</p>
                    </TooltipContent>
                </Tooltip>

                <!-- Duplicate -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Duplicate"
                            class="flex h-5 w-5 items-center justify-center rounded-sm hover:bg-white/20"
                            @click="emit('duplicate')"
                        >
                            <Copy class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">Duplicate</p>
                    </TooltipContent>
                </Tooltip>

                <!-- Delete -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Delete"
                            class="flex h-5 w-5 items-center justify-center rounded-sm text-primary-foreground hover:bg-destructive hover:text-destructive-foreground"
                            @click="emit('delete')"
                        >
                            <Trash2 class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">Delete Node</p>
                    </TooltipContent>
                </Tooltip>
            </template>
        </div>
    </TooltipProvider>
</template>
