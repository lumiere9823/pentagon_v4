<script setup lang="ts">
import { ref, toRef, watch } from 'vue';
import {
    AlertCircle,
    Check,
    Copy,
    ExternalLink,
    Link as LinkIcon,
    Loader2,
    RefreshCw,
    ShieldCheck,
    X,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useModalFocus } from '@/composables/useModalFocus';
import { requestJson } from '@/lib/httpClient';

const props = defineProps<{
    open: boolean;
    websiteId: number;
    pageId: number;
    pageTitle?: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const modalRef = ref<HTMLElement | null>(null);
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);
const signedUrl = ref<string | null>(null);
const expiresAt = ref<string | null>(null);
const isCopied = ref(false);

useModalFocus(toRef(props, 'open'), modalRef, {
    onClose: () => emit('update:open', false),
});

const generateShareLink = async () => {
    isLoading.value = true;
    errorMessage.value = null;
    isCopied.value = false;

    try {
        const response = await requestJson<{
            signed_url: string;
            expires_at: string;
        }>(
            `/websites/${props.websiteId}/pages/${props.pageId}/preview/share-link`,
            {
                method: 'POST',
            },
        );

        if (response.ok && response.data?.signed_url) {
            signedUrl.value = response.data.signed_url;
            expiresAt.value = response.data.expires_at;
        } else {
            errorMessage.value =
                (response.data as any)?.message ||
                'Failed to generate signed preview link.';
        }
    } catch (err: any) {
        errorMessage.value =
            err?.message || 'Network error while generating preview link.';
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            generateShareLink();
        } else {
            signedUrl.value = null;
            expiresAt.value = null;
            errorMessage.value = null;
            isCopied.value = false;
        }
    },
    { immediate: true },
);

const copyToClipboard = async () => {
    if (!signedUrl.value) return;

    try {
        await navigator.clipboard.writeText(signedUrl.value);
        isCopied.value = true;
        setTimeout(() => {
            isCopied.value = false;
        }, 2500);
    } catch {
        // Fallback for environments where navigator.clipboard might fail
        const input = document.getElementById(
            'preview-share-url-input',
        ) as HTMLInputElement | null;
        if (input) {
            input.select();
            document.execCommand('copy');
            isCopied.value = true;
            setTimeout(() => {
                isCopied.value = false;
            }, 2500);
        }
    }
};

const formatExpiry = (isoString: string | null) => {
    if (!isoString) return '60 minutes';
    try {
        const date = new Date(isoString);
        return date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '60 minutes';
    }
};
</script>

<template>
    <teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="emit('update:open', false)"
        >
            <div
                ref="modalRef"
                role="dialog"
                aria-modal="true"
                aria-labelledby="share-preview-title"
                tabindex="-1"
                class="relative w-full max-w-lg space-y-4 rounded-xl border bg-background p-6 shadow-xl"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2">
                        <LinkIcon class="h-5 w-5 text-primary" />
                        <h2
                            id="share-preview-title"
                            class="text-base font-semibold"
                        >
                            Share Draft Preview
                        </h2>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-muted-foreground"
                        aria-label="Close dialog"
                        @click="emit('update:open', false)"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Modal Content -->
                <div class="space-y-4 text-sm">
                    <p class="text-xs text-muted-foreground">
                        Generate a secure, cryptographically signed preview
                        link. Anyone with this link can view the current draft
                        of
                        <strong class="font-medium text-foreground">{{
                            pageTitle || 'this page'
                        }}</strong>
                        without logging in.
                    </p>

                    <!-- Loading State -->
                    <div
                        v-if="isLoading"
                        class="flex items-center justify-center gap-2 py-8 text-muted-foreground"
                    >
                        <Loader2 class="h-5 w-5 animate-spin" />
                        <span class="text-xs">Generating secure link...</span>
                    </div>

                    <!-- Error Alert -->
                    <div
                        v-else-if="errorMessage"
                        role="alert"
                        class="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive"
                    >
                        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                        <div class="flex-1">
                            <p class="font-medium">{{ errorMessage }}</p>
                            <Button
                                variant="outline"
                                size="sm"
                                class="mt-2 h-7 gap-1 text-xs"
                                @click="generateShareLink"
                            >
                                <RefreshCw class="h-3 w-3" />
                                Try Again
                            </Button>
                        </div>
                    </div>

                    <!-- Link Display & Action -->
                    <div v-else-if="signedUrl" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <input
                                id="preview-share-url-input"
                                :value="signedUrl"
                                readonly
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 font-mono text-xs shadow-xs transition-colors select-all"
                                aria-label="Signed preview URL"
                            />
                            <Button
                                type="button"
                                variant="default"
                                size="sm"
                                class="h-9 shrink-0 gap-1.5 text-xs"
                                data-testid="copy-share-link-btn"
                                @click="copyToClipboard"
                            >
                                <Check
                                    v-if="isCopied"
                                    class="h-3.5 w-3.5 text-green-300"
                                />
                                <Copy v-else class="h-3.5 w-3.5" />
                                <span>{{ isCopied ? 'Copied!' : 'Copy' }}</span>
                            </Button>
                        </div>

                        <!-- Info details -->
                        <div
                            class="flex items-center justify-between rounded-lg bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                        >
                            <div class="flex items-center gap-1.5">
                                <ShieldCheck class="h-4 w-4 text-emerald-500" />
                                <span>Expiring temporary signature</span>
                            </div>
                            <span
                                >Expires around
                                {{ formatExpiry(expiresAt) }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between border-t pt-3">
                    <Button
                        v-if="signedUrl"
                        variant="outline"
                        size="sm"
                        as-child
                        class="h-8 gap-1.5 text-xs"
                    >
                        <a
                            :href="signedUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink class="h-3.5 w-3.5" />
                            <span>Open in New Tab</span>
                        </a>
                    </Button>
                    <div v-else />

                    <Button
                        variant="secondary"
                        size="sm"
                        class="h-8 text-xs"
                        @click="emit('update:open', false)"
                    >
                        Close
                    </Button>
                </div>
            </div>
        </div>
    </teleport>
</template>
