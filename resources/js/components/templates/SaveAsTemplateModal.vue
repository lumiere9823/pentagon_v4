<script setup lang="ts">
import { ref, toRef } from 'vue';
import { Bookmark, CheckCircle2, Loader2, X } from '@lucide/vue';
import { useModalFocus } from '@/composables/useModalFocus';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DocumentSchema } from '@/types/document';
import { requestJson } from '@/lib/httpClient';

const props = defineProps<{
    open: boolean;
    websiteId: number;
    document: DocumentSchema;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved', template: any): void;
}>();

const name = ref('');
const description = ref('');
const category = ref('general');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const modalRef = ref<HTMLElement | null>(null);

useModalFocus(toRef(props, 'open'), modalRef, {
    onClose: () => handleClose(),
    initialFocusSelector: 'input',
});

const resetForm = () => {
    name.value = '';
    description.value = '';
    category.value = 'general';
    errorMessage.value = null;
    successMessage.value = null;
    isSubmitting.value = false;
};

const handleClose = () => {
    emit('update:open', false);
    resetForm();
};

const handleSave = async () => {
    if (!name.value.trim()) {
        errorMessage.value = 'Please provide a template name.';
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        const response = await requestJson<any>(
            `/websites/${props.websiteId}/templates`,
            {
                method: 'POST',
                body: {
                    name: name.value.trim(),
                    description: description.value.trim() || null,
                    category: category.value,
                    document_json: props.document,
                },
            },
        );
        const data = response.data;

        if (!response.ok) {
            errorMessage.value =
                data.message ||
                'Failed to save template. Please check the document contents.';
            return;
        }

        successMessage.value = 'Custom template saved successfully!';
        emit('saved', data.data);

        setTimeout(() => {
            handleClose();
        }, 1200);
    } catch (err: any) {
        errorMessage.value =
            err?.message || 'Network error occurred while saving template.';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        @click.self="handleClose"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-labelledby="save-template-title"
            class="relative w-full max-w-md animate-in rounded-xl border bg-background p-6 shadow-xl duration-150 zoom-in-95 fade-in focus:outline-hidden"
        >
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Bookmark class="h-4 w-4" />
                    </div>
                    <div>
                        <h2
                            id="save-template-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Save as Custom Template
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Create a reusable blueprint from your current canvas
                            layout.
                        </p>
                    </div>
                </div>

                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 rounded-full"
                    @click="handleClose"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>

            <!-- Success alert -->
            <div
                v-if="successMessage"
                class="mt-4 flex items-center gap-2 rounded-lg bg-emerald-500/10 p-3 text-xs font-medium text-emerald-600 dark:text-emerald-400"
            >
                <CheckCircle2 class="h-4 w-4 shrink-0" />
                <span>{{ successMessage }}</span>
            </div>

            <!-- Error alert -->
            <div
                v-if="errorMessage"
                class="mt-4 rounded-lg bg-destructive/10 p-3 text-xs font-medium text-destructive"
            >
                {{ errorMessage }}
            </div>

            <form @submit.prevent="handleSave" class="mt-4 space-y-4">
                <div class="space-y-1.5">
                    <Label for="template-name" class="text-xs font-medium"
                        >Template Name</Label
                    >
                    <Input
                        id="template-name"
                        v-model="name"
                        placeholder="e.g. Minimalist SaaS Landing"
                        required
                        maxlength="128"
                        class="h-9 text-xs"
                        :disabled="isSubmitting"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="template-desc" class="text-xs font-medium"
                        >Description (Optional)</Label
                    >
                    <textarea
                        id="template-desc"
                        v-model="description"
                        rows="3"
                        placeholder="Describe the layout, purpose, or intended audience..."
                        maxlength="1000"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring focus:outline-none disabled:opacity-50"
                        :disabled="isSubmitting"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="template-category" class="text-xs font-medium"
                        >Category</Label
                    >
                    <select
                        id="template-category"
                        v-model="category"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus:ring-1 focus:ring-ring focus:outline-none disabled:opacity-50"
                        :disabled="isSubmitting"
                    >
                        <option value="general">General</option>
                        <option value="landing">Landing Page</option>
                        <option value="portfolio">Portfolio & Creative</option>
                        <option value="lead_gen">Lead Generation</option>
                        <option value="business">Corporate / Business</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 border-t pt-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        :disabled="isSubmitting"
                        @click="handleClose"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        class="h-8 gap-1.5 text-xs"
                        :disabled="isSubmitting || !name.trim()"
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="h-3.5 w-3.5 animate-spin"
                        />
                        <span>{{
                            isSubmitting ? 'Saving...' : 'Save Template'
                        }}</span>
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
