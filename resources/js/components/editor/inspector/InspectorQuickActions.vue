<script setup lang="ts">
import { Copy, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { DocumentNode } from '@/types/document';

defineProps<{
    node: DocumentNode;
    readOnly: boolean;
    isRoot: boolean;
}>();

const emit = defineEmits<{
    (e: 'duplicate', nodeId: string): void;
    (e: 'delete', nodeId: string): void;
}>();
</script>

<template>
    <div
        v-if="!readOnly && !isRoot"
        class="flex items-center gap-2 border-t bg-muted/10 p-3"
    >
        <Button
            variant="outline"
            size="sm"
            class="flex-1 text-xs"
            @click="emit('duplicate', node.id)"
        >
            <Copy class="mr-1.5 h-3.5 w-3.5" />
            Duplicate
        </Button>
        <Button
            variant="outline"
            size="sm"
            class="flex-1 border-destructive/30 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
            @click="emit('delete', node.id)"
        >
            <Trash2 class="mr-1.5 h-3.5 w-3.5" />
            Delete
        </Button>
    </div>
</template>
