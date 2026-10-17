<script setup lang="ts">
import { ref } from 'vue';
import { AlertCircle, CheckCircle2, Loader2, UploadCloud } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MediaAssetItem } from '@/types/media';

const props = withDefaults(
    defineProps<{
        websiteId: number;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'uploaded', asset: MediaAssetItem): void;
}>();

const isDragging = ref(false);
const isUploading = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const altText = ref('');
const fileInputRef = ref<HTMLInputElement | null>(null);

const MAX_BYTES = 5 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function getCsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        return meta.getAttribute('content');
    }
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

function handleDragOver(e: DragEvent) {
    if (props.disabled || isUploading.value) return;
    e.preventDefault();
    isDragging.value = true;
}

function handleDragLeave(e: DragEvent) {
    e.preventDefault();
    isDragging.value = false;
}

function handleDrop(e: DragEvent) {
    if (props.disabled || isUploading.value) return;
    e.preventDefault();
    isDragging.value = false;

    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        processAndUploadFile(files[0]);
    }
}

function handleFileSelect(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        processAndUploadFile(target.files[0]);
    }
}

async function processAndUploadFile(file: File) {
    errorMessage.value = null;
    successMessage.value = null;

    // Client-side pre-validation
    if (file.size > MAX_BYTES) {
        errorMessage.value =
            'File size exceeds 5 MB. Please select a smaller image.';
        return;
    }

    if (!ALLOWED_TYPES.includes(file.type)) {
        errorMessage.value =
            'Invalid image type. Allowed formats: JPEG, PNG, WebP, GIF. (SVG is not supported).';
        return;
    }

    isUploading.value = true;

    try {
        const formData = new FormData();
        formData.append('file', file);
        if (altText.value.trim()) {
            formData.append('alt_text', altText.value.trim());
        }

        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        const csrf = getCsrfToken();
        if (csrf) {
            headers['X-XSRF-TOKEN'] = csrf;
            headers['X-CSRF-TOKEN'] = csrf;
        }

        const response = await fetch(`/websites/${props.websiteId}/media`, {
            method: 'POST',
            headers,
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422 && data.errors?.file) {
                errorMessage.value = Array.isArray(data.errors.file)
                    ? data.errors.file.join(' ')
                    : String(data.errors.file);
            } else {
                errorMessage.value =
                    data.message ||
                    `Upload failed with status ${response.status}.`;
            }
            return;
        }

        successMessage.value = 'Image uploaded successfully!';
        altText.value = '';
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }

        emit('uploaded', data.data);
    } catch (err) {
        errorMessage.value =
            err instanceof Error ? err.message : 'Network error during upload.';
    } finally {
        isUploading.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Error Alert -->
        <div
            v-if="errorMessage"
            class="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-xs text-destructive"
            role="alert"
        >
            <AlertCircle class="h-4 w-4 shrink-0" />
            <span>{{ errorMessage }}</span>
        </div>

        <!-- Success Alert -->
        <div
            v-if="successMessage"
            class="flex items-center gap-2 rounded-md border border-emerald-500/50 bg-emerald-500/10 p-3 text-xs text-emerald-600 dark:text-emerald-400"
            role="status"
        >
            <CheckCircle2 class="h-4 w-4 shrink-0" />
            <span>{{ successMessage }}</span>
        </div>

        <!-- Drag & Drop Area -->
        <div
            class="relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center transition-colors"
            :class="[
                isDragging
                    ? 'border-primary bg-primary/5'
                    : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                disabled || isUploading
                    ? 'pointer-events-none cursor-not-allowed opacity-60'
                    : 'cursor-pointer',
            ]"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            @click="fileInputRef?.click()"
        >
            <input
                ref="fileInputRef"
                type="file"
                class="hidden"
                accept="image/jpeg,image/png,image/webp,image/gif"
                :disabled="disabled || isUploading"
                @change="handleFileSelect"
            />

            <template v-if="isUploading">
                <Loader2 class="mb-3 h-10 w-10 animate-spin text-primary" />
                <p class="text-sm font-medium text-foreground">
                    Uploading image...
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Please wait while the image is verified and saved.
                </p>
            </template>
            <template v-else>
                <UploadCloud class="mb-3 h-10 w-10 text-muted-foreground" />
                <p class="text-sm font-medium text-foreground">
                    Drag and drop your image here, or
                    <span class="text-primary underline">browse</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Supports JPEG, PNG, WebP, GIF (Max 5 MB, up to 4096×4096px)
                </p>
            </template>
        </div>

        <!-- Optional Alt Text Before Upload -->
        <div class="space-y-1.5 text-left">
            <Label for="upload-alt-text" class="text-xs font-medium">
                Alt Text / Description (Optional)
            </Label>
            <Input
                id="upload-alt-text"
                v-model="altText"
                placeholder="Brief description for screen readers and SEO..."
                class="h-8 text-xs"
                :disabled="disabled || isUploading"
                maxlength="255"
            />
        </div>
    </div>
</template>
