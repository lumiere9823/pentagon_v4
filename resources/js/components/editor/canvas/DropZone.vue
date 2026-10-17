<script setup lang="ts">
import { ref } from 'vue';
import type { CoreComponentType } from '@/types/document';

const props = withDefaults(
    defineProps<{
        parentId: string;
        insertIndex: number;
        readOnly?: boolean;
        emptyContainer?: boolean;
    }>(),
    {
        readOnly: false,
        emptyContainer: false,
    },
);

const emit = defineEmits<{
    (
        e: 'dropComponent',
        payload: {
            type: CoreComponentType;
            parentId: string;
            index: number;
        },
    ): void;
}>();

const isOver = ref(false);

const onDragOver = (e: DragEvent) => {
    if (props.readOnly) return;
    e.preventDefault();
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'copy';
    }
    isOver.value = true;
};

const onDragLeave = () => {
    isOver.value = false;
};

const onDrop = (e: DragEvent) => {
    if (props.readOnly) return;
    e.preventDefault();
    isOver.value = false;

    try {
        const rawData = e.dataTransfer?.getData('application/json');
        if (!rawData) return;
        const parsed = JSON.parse(rawData);
        if (parsed && parsed.type) {
            emit('dropComponent', {
                type: parsed.type,
                parentId: props.parentId,
                index: props.insertIndex,
            });
        }
    } catch {
        // Ignore invalid drop payloads
    }
};
</script>

<template>
    <div
        v-if="!readOnly"
        :class="[
            'relative z-20 transition-all duration-150',
            emptyContainer
                ? 'my-2 flex min-h-[90px] w-full flex-col items-center justify-center rounded-lg border-2 border-dashed p-4'
                : 'my-0.5 h-2 w-full',
            emptyContainer
                ? isOver
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border/60 bg-muted/10 text-muted-foreground/80 hover:border-border'
                : isOver
                  ? 'h-3 scale-y-125 rounded-full bg-primary/80 shadow-sm'
                  : 'opacity-0 hover:bg-primary/20 hover:opacity-40',
        ]"
        @dragover="onDragOver"
        @dragenter="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
    >
        <template v-if="emptyContainer">
            <span class="text-xs font-medium">Empty Container</span>
            <span class="text-[11px] opacity-75">
                Drag a component here or click to insert.
            </span>
        </template>
    </div>
</template>
