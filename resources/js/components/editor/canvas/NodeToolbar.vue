<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Copy,
    CornerLeftUp,
    Lock,
    Trash2,
} from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = withDefaults(
    defineProps<{
        componentType: string;
        isRoot: boolean;
        canMoveUp: boolean;
        canMoveDown: boolean;
        isLocked?: boolean;
    }>(),
    {
        isLocked: false,
    },
);

const emit = defineEmits<{
    (e: 'moveUp'): void;
    (e: 'moveDown'): void;
    (e: 'duplicate'): void;
    (e: 'delete'): void;
    (e: 'selectParent'): void;
}>();

const handleMoveUp = () => {
    if (props.isLocked || !props.canMoveUp) return;
    emit('moveUp');
};

const handleMoveDown = () => {
    if (props.isLocked || !props.canMoveDown) return;
    emit('moveDown');
};

const handleDuplicate = () => {
    if (props.isLocked) return;
    emit('duplicate');
};

const handleDelete = () => {
    if (props.isLocked) return;
    emit('delete');
};
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <div
            class="absolute -top-9 left-2 z-30 flex items-center gap-0.5 rounded-md border border-primary/40 bg-primary px-1.5 py-0.5 text-primary-foreground shadow-md transition-opacity select-none"
            @click.stop
        >
            <span
                class="mr-1 flex items-center gap-1 text-[11px] font-semibold tracking-wide"
            >
                <Lock
                    v-if="isLocked"
                    class="h-3 w-3 text-amber-200"
                    title="Locked"
                    aria-label="Component is locked"
                />
                {{ componentType }}
            </span>

            <template v-if="!isRoot">
                <div class="mx-0.5 h-3 w-px bg-primary-foreground/30" />

                <!-- Select Parent -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Select Parent"
                            class="flex h-5 w-5 items-center justify-center rounded-sm hover:bg-white/20"
                            @click="emit('selectParent')"
                        >
                            <CornerLeftUp class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">Select Parent</p>
                    </TooltipContent>
                </Tooltip>

                <!-- Move Up -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Move Up"
                            :disabled="!canMoveUp || isLocked"
                            class="flex h-5 w-5 items-center justify-center rounded-sm hover:bg-white/20 disabled:opacity-30 disabled:hover:bg-transparent"
                            @click="handleMoveUp"
                        >
                            <ArrowUp class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">
                            {{ isLocked ? 'Locked (cannot move)' : 'Move Up' }}
                        </p>
                    </TooltipContent>
                </Tooltip>

                <!-- Move Down -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Move Down"
                            :disabled="!canMoveDown || isLocked"
                            class="flex h-5 w-5 items-center justify-center rounded-sm hover:bg-white/20 disabled:opacity-30 disabled:hover:bg-transparent"
                            @click="handleMoveDown"
                        >
                            <ArrowDown class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">
                            {{
                                isLocked ? 'Locked (cannot move)' : 'Move Down'
                            }}
                        </p>
                    </TooltipContent>
                </Tooltip>

                <!-- Duplicate -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Duplicate"
                            :disabled="isLocked"
                            class="flex h-5 w-5 items-center justify-center rounded-sm hover:bg-white/20 disabled:opacity-30 disabled:hover:bg-transparent"
                            @click="handleDuplicate"
                        >
                            <Copy class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">
                            {{
                                isLocked
                                    ? 'Locked (cannot duplicate)'
                                    : 'Duplicate'
                            }}
                        </p>
                    </TooltipContent>
                </Tooltip>

                <!-- Delete -->
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            aria-label="Delete"
                            :disabled="isLocked"
                            class="flex h-5 w-5 items-center justify-center rounded-sm text-primary-foreground hover:bg-destructive hover:text-destructive-foreground disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-primary-foreground"
                            @click="handleDelete"
                        >
                            <Trash2 class="h-3 w-3" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        <p class="text-[11px]">
                            {{
                                isLocked
                                    ? 'Locked (cannot delete)'
                                    : 'Delete Node'
                            }}
                        </p>
                    </TooltipContent>
                </Tooltip>
            </template>
        </div>
    </TooltipProvider>
</template>
