<script setup lang="ts">
import { ref, toRef } from 'vue';
import { AlertTriangle, RefreshCw, UploadCloud } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useModalFocus } from '@/composables/useModalFocus';

interface Props {
    open: boolean;
    serverLockVersion: number;
    serverUpdatedAt?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'reload'): void;
    (e: 'overwrite'): void;
}>();

const modalRef = ref<HTMLElement | null>(null);

useModalFocus(toRef(props, 'open'), modalRef, {
    closeOnEscape: false,
});
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            class="relative w-full max-w-md rounded-xl border border-destructive/30 bg-background p-6 shadow-2xl focus:outline-hidden"
            role="dialog"
            aria-modal="true"
            aria-labelledby="conflict-modal-title"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                >
                    <AlertTriangle class="h-5 w-5" />
                </div>
                <div>
                    <h3
                        id="conflict-modal-title"
                        class="text-base font-semibold text-foreground"
                    >
                        Conflict Detected
                    </h3>
                    <p
                        class="mt-1.5 text-xs leading-relaxed text-muted-foreground"
                    >
                        This page was modified in another session or tab (Server
                        Version #{{ serverLockVersion }}). Saving your local
                        changes directly would overwrite those edits.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5 text-xs"
                    @click="emit('reload')"
                >
                    <RefreshCw class="h-3.5 w-3.5" />
                    <span>Reload Server Version</span>
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    class="gap-1.5 text-xs"
                    @click="emit('overwrite')"
                >
                    <UploadCloud class="h-3.5 w-3.5" />
                    <span>Overwrite (Keep Mine)</span>
                </Button>
            </div>
        </div>
    </div>
</template>
