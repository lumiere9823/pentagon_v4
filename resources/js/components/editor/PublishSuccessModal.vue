<script setup lang="ts">
import { ref } from 'vue';
import { CheckCircle2, Copy, Check, ExternalLink, X, Globe } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    open: boolean;
    publicUrl: string;
    pageTitle: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const copied = ref(false);

const copyUrl = async () => {
    try {
        await navigator.clipboard.writeText(props.publicUrl);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Clipboard access failed
    }
};

const close = () => {
    emit('update:open', false);
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        @click.self="close"
    >
        <div
            class="w-full max-w-md rounded-xl border bg-background p-6 shadow-2xl transition-all"
            role="dialog"
            aria-modal="true"
            aria-labelledby="publish-success-title"
        >
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-green-500/10 text-green-600 dark:text-green-400"
                    >
                        <CheckCircle2 class="h-6 w-6" />
                    </div>
                    <div>
                        <h3
                            id="publish-success-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Page Published
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Your page is now live and publicly accessible.
                        </p>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                    @click="close"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>

            <div class="mt-5 space-y-3">
                <label class="text-xs font-medium text-foreground">
                    Public Live URL
                </label>
                <div class="flex items-center gap-2">
                    <div
                        class="flex flex-1 items-center gap-2 overflow-hidden rounded-lg border bg-muted/40 px-3 py-2 font-mono text-xs text-foreground select-all"
                    >
                        <Globe
                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                        />
                        <span class="truncate">{{ publicUrl }}</span>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 shrink-0 gap-1 text-xs"
                        @click="copyUrl"
                    >
                        <Check
                            v-if="copied"
                            class="h-3.5 w-3.5 text-green-500"
                        />
                        <Copy v-else class="h-3.5 w-3.5" />
                        <span>{{ copied ? 'Copied' : 'Copy' }}</span>
                    </Button>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-2">
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-xs"
                    @click="close"
                >
                    Close
                </Button>
                <Button
                    size="sm"
                    class="gap-1.5 bg-primary text-xs text-primary-foreground"
                    as-child
                >
                    <a
                        :href="publicUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span>Visit Site</span>
                        <ExternalLink class="h-3.5 w-3.5" />
                    </a>
                </Button>
            </div>
        </div>
    </div>
</template>
