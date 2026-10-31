<script setup lang="ts">
import type { DocumentNode } from '@/types/document';

defineProps<{ node: DocumentNode }>();
</script>

<template>
    <div class="space-y-1.5 rounded-lg border bg-muted/20 p-3 text-xs">
        <div
            class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
        >
            Node Identity
        </div>
        <div class="flex items-center justify-between text-muted-foreground">
            <span>ID:</span>
            <span class="font-mono text-[10px] text-foreground">{{
                node.id
            }}</span>
        </div>
        <div class="flex items-center justify-between text-muted-foreground">
            <span>Type:</span>
            <span class="font-medium text-foreground">{{ node.type }}</span>
        </div>
        <div class="flex items-center justify-between text-muted-foreground">
            <span>Children:</span>
            <span class="font-mono text-foreground">{{
                node.children?.length ?? 0
            }}</span>
        </div>
    </div>
</template>
