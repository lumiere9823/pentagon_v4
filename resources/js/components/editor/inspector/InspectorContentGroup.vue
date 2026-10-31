<script setup lang="ts">
import {
    Image as ImageIcon,
    Link as LinkIcon,
    Plus,
    Trash2,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    getFieldValidationGuidance,
    getFaqItemValidationGuidance,
} from '@/lib/inspectorHelpers';
import type { DocumentNode, InspectorPropertyMetadata } from '@/types/document';

defineProps<{
    node: DocumentNode;
    fields: InspectorPropertyMetadata[];
    readOnly: boolean;
    websiteId?: number;
}>();

const emit = defineEmits<{
    (e: 'updateProp', payload: { key: string; value: unknown }): void;
    (e: 'openMediaLibrary'): void;
}>();

function getFieldGuidance(
    field: InspectorPropertyMetadata,
    node: DocumentNode,
) {
    return getFieldValidationGuidance(field, node.props[field.key]);
}

function getFaqGuidance(
    item: { question?: string; answer?: string },
    field: InspectorPropertyMetadata,
) {
    return getFaqItemValidationGuidance(item, field.itemSchema);
}

function addFaqItem(node: DocumentNode, field: InspectorPropertyMetadata) {
    const currentItems = Array.isArray(node.props[field.key])
        ? [
              ...(node.props[field.key] as Array<{
                  question?: string;
                  answer?: string;
              }>),
          ]
        : [];
    const max = field.maxItems ?? 20;
    if (currentItems.length >= max) {
        return;
    }
    currentItems.push({
        question: 'New Question',
        answer: '<p>Answer details here.</p>',
    });
    emit('updateProp', { key: field.key, value: currentItems });
}

function updateFaqItem(
    node: DocumentNode,
    key: string,
    index: number,
    field: 'question' | 'answer',
    value: string,
) {
    const currentItems = Array.isArray(node.props[key])
        ? [...(node.props[key] as unknown[])]
        : [];
    if (!currentItems[index]) return;

    currentItems[index] = { ...currentItems[index], [field]: value };
    emit('updateProp', { key, value: currentItems });
}

function removeFaqItem(node: DocumentNode, key: string, index: number) {
    const currentItems = Array.isArray(node.props[key])
        ? [...(node.props[key] as unknown[])]
        : [];
    currentItems.splice(index, 1);
    emit('updateProp', { key, value: currentItems });
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="fields.length === 0"
            class="py-6 text-center text-xs text-muted-foreground"
        >
            No content properties for this component.
        </div>

        <template v-for="field in fields" :key="field.path">
            <div class="space-y-1.5">
                <!-- FAQ List Control -->
                <div v-if="field.control === 'faq-list'" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <Label class="text-xs font-semibold">{{
                                field.label
                            }}</Label>
                            <span
                                class="text-[10px]"
                                :class="
                                    ((node.props[field.key] as any[]) || [])
                                        .length >= (field.maxItems ?? 20)
                                        ? 'font-semibold text-amber-600 dark:text-amber-400'
                                        : 'text-muted-foreground'
                                "
                                data-testid="faq-count"
                            >
                                ({{
                                    ((node.props[field.key] as any[]) || [])
                                        .length
                                }}/{{ field.maxItems ?? 20 }})
                            </span>
                        </div>
                        <Button
                            v-if="!readOnly"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-6 gap-1 px-2 text-[11px]"
                            :disabled="
                                ((node.props[field.key] as any[]) || [])
                                    .length >= (field.maxItems ?? 20)
                            "
                            data-testid="add-faq-btn"
                            @click="addFaqItem(node, field)"
                        >
                            <Plus class="h-3 w-3" />
                            <span>Add FAQ</span>
                        </Button>
                    </div>

                    <div
                        v-if="
                            ((node.props[field.key] as any[]) || []).length >=
                            (field.maxItems ?? 20)
                        "
                        class="rounded-md border border-amber-500/20 bg-amber-500/10 px-2.5 py-1.5 text-[11px] text-amber-700 dark:text-amber-400"
                        data-testid="faq-limit-notice"
                    >
                        Maximum limit of {{ field.maxItems ?? 20 }} FAQ items
                        reached.
                    </div>

                    <div
                        v-for="(item, index) in (node.props[
                            field.key
                        ] as any[]) || []"
                        :key="index"
                        class="space-y-2 rounded-lg border bg-muted/20 p-2.5"
                        data-testid="faq-item"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="text-[10px] font-semibold text-muted-foreground"
                            >
                                Item #{{ index + 1 }}
                            </span>
                            <button
                                v-if="!readOnly"
                                type="button"
                                aria-label="Remove item"
                                class="text-destructive hover:opacity-80"
                                @click="removeFaqItem(node, field.key, index)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Question Input -->
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[10px] font-medium text-muted-foreground"
                                >
                                    Question
                                    <span
                                        class="font-bold text-destructive"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                </span>
                                <span
                                    v-if="field.itemSchema?.question?.maxLength"
                                    class="text-[9px]"
                                    :class="
                                        (item.question || '').length >=
                                        field.itemSchema.question.maxLength
                                            ? 'font-semibold text-destructive'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ (item.question || '').length }}/{{
                                        field.itemSchema.question.maxLength
                                    }}
                                </span>
                            </div>
                            <Input
                                :model-value="item.question"
                                :disabled="readOnly"
                                :maxlength="
                                    field.itemSchema?.question?.maxLength ?? 255
                                "
                                placeholder="Question..."
                                class="h-7 text-xs"
                                :class="{
                                    'border-destructive focus-visible:ring-destructive/30':
                                        Boolean(
                                            getFaqGuidance(item, field)
                                                .questionError,
                                        ),
                                }"
                                aria-required="true"
                                :aria-invalid="
                                    Boolean(
                                        getFaqGuidance(item, field)
                                            .questionError,
                                    )
                                "
                                data-testid="faq-question-input"
                                @update:model-value="
                                    updateFaqItem(
                                        node,
                                        field.key,
                                        index,
                                        'question',
                                        String($event),
                                    )
                                "
                            />
                            <p
                                v-if="getFaqGuidance(item, field).questionError"
                                role="alert"
                                class="text-[10px] font-medium text-destructive"
                                data-testid="faq-question-error"
                            >
                                {{ getFaqGuidance(item, field).questionError }}
                            </p>
                        </div>

                        <!-- Answer Textarea -->
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[10px] font-medium text-muted-foreground"
                                >
                                    Answer
                                    <span
                                        class="font-bold text-destructive"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                </span>
                                <span
                                    v-if="field.itemSchema?.answer?.maxLength"
                                    class="text-[9px]"
                                    :class="
                                        (item.answer || '').length >=
                                        field.itemSchema.answer.maxLength
                                            ? 'font-semibold text-destructive'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ (item.answer || '').length }}/{{
                                        field.itemSchema.answer.maxLength
                                    }}
                                </span>
                            </div>
                            <Textarea
                                :model-value="item.answer"
                                :disabled="readOnly"
                                :maxlength="
                                    field.itemSchema?.answer?.maxLength ?? 2000
                                "
                                placeholder="Answer (HTML allowed)..."
                                class="min-h-12 text-xs"
                                :class="{
                                    'border-destructive focus-visible:ring-destructive/30':
                                        Boolean(
                                            getFaqGuidance(item, field)
                                                .answerError,
                                        ),
                                }"
                                aria-required="true"
                                :aria-invalid="
                                    Boolean(
                                        getFaqGuidance(item, field).answerError,
                                    )
                                "
                                data-testid="faq-answer-input"
                                @update:model-value="
                                    updateFaqItem(
                                        node,
                                        field.key,
                                        index,
                                        'answer',
                                        String($event),
                                    )
                                "
                            />
                            <p
                                v-if="getFaqGuidance(item, field).answerError"
                                role="alert"
                                class="text-[10px] font-medium text-destructive"
                                data-testid="faq-answer-error"
                            >
                                {{ getFaqGuidance(item, field).answerError }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Textarea Control -->
                <div v-else-if="field.control === 'textarea'" class="space-y-1">
                    <div class="flex items-center justify-between">
                        <Label
                            :for="'prop-' + field.key"
                            class="text-xs font-medium"
                        >
                            {{ field.label }}
                            <span
                                v-if="field.required"
                                class="ml-0.5 font-bold text-destructive"
                                aria-hidden="true"
                                >*</span
                            >
                        </Label>
                        <span
                            v-if="field.maxLength"
                            class="text-[10px]"
                            :class="
                                String(node.props[field.key] || '').length >=
                                field.maxLength
                                    ? 'font-semibold text-destructive'
                                    : String(node.props[field.key] || '')
                                            .length >=
                                        field.maxLength * 0.9
                                      ? 'text-amber-600 dark:text-amber-400'
                                      : 'text-muted-foreground'
                            "
                        >
                            {{ String(node.props[field.key] || '').length }}/{{
                                field.maxLength
                            }}
                        </span>
                    </div>
                    <Textarea
                        :id="'prop-' + field.key"
                        :model-value="String(node.props[field.key] || '')"
                        :disabled="readOnly"
                        :maxlength="field.maxLength"
                        :aria-required="field.required ? 'true' : undefined"
                        :aria-invalid="!getFieldGuidance(field, node).isValid"
                        :aria-describedby="
                            !getFieldGuidance(field, node).isValid
                                ? 'prop-' + field.key + '-error'
                                : undefined
                        "
                        class="min-h-24 font-mono text-xs"
                        :class="{
                            'border-destructive focus-visible:ring-destructive/30':
                                !getFieldGuidance(field, node).isValid,
                        }"
                        @update:model-value="
                            emit('updateProp', {
                                key: field.key,
                                value: $event,
                            })
                        "
                    />
                    <p
                        v-if="!getFieldGuidance(field, node).isValid"
                        :id="'prop-' + field.key + '-error'"
                        role="alert"
                        class="text-[11px] font-medium text-destructive"
                        data-testid="field-error"
                    >
                        {{ getFieldGuidance(field, node).error }}
                    </p>
                    <p v-else class="text-[10px] text-muted-foreground">
                        Safe HTML is supported. Scripts and event handlers are
                        automatically sanitized.
                    </p>
                </div>

                <!-- URL or Media Control -->
                <div
                    v-else-if="
                        field.control === 'url' || field.control === 'media'
                    "
                    class="space-y-1"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <Label
                                :for="'prop-' + field.key"
                                class="text-xs font-medium"
                            >
                                {{ field.label }}
                                <span
                                    v-if="field.required"
                                    class="ml-0.5 font-bold text-destructive"
                                    aria-hidden="true"
                                    >*</span
                                >
                            </Label>
                            <span
                                v-if="field.maxLength"
                                class="text-[10px]"
                                :class="
                                    String(node.props[field.key] || '')
                                        .length >= field.maxLength
                                        ? 'font-semibold text-destructive'
                                        : String(node.props[field.key] || '')
                                                .length >=
                                            field.maxLength * 0.9
                                          ? 'text-amber-600 dark:text-amber-400'
                                          : 'text-muted-foreground'
                                "
                            >
                                {{
                                    String(node.props[field.key] || '').length
                                }}/{{ field.maxLength }}
                            </span>
                        </div>
                        <Button
                            v-if="field.control === 'media' && websiteId"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-6 gap-1 px-2 text-[11px]"
                            :disabled="readOnly"
                            @click="emit('openMediaLibrary')"
                        >
                            <ImageIcon class="h-3 w-3" />
                            Choose from Library
                        </Button>
                    </div>
                    <div class="relative">
                        <LinkIcon
                            class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-muted-foreground"
                        />
                        <Input
                            :id="'prop-' + field.key"
                            :model-value="String(node.props[field.key] || '')"
                            :disabled="readOnly"
                            :maxlength="field.maxLength"
                            :aria-required="field.required ? 'true' : undefined"
                            :aria-invalid="
                                !getFieldGuidance(field, node).isValid
                            "
                            :aria-describedby="
                                !getFieldGuidance(field, node).isValid
                                    ? 'prop-' + field.key + '-error'
                                    : undefined
                            "
                            placeholder="https://..."
                            class="h-8 pl-8 font-mono text-xs"
                            :class="{
                                'border-destructive focus-visible:ring-destructive/30':
                                    !getFieldGuidance(field, node).isValid,
                            }"
                            @update:model-value="
                                emit('updateProp', {
                                    key: field.key,
                                    value: $event,
                                })
                            "
                        />
                    </div>
                    <p
                        v-if="!getFieldGuidance(field, node).isValid"
                        :id="'prop-' + field.key + '-error'"
                        role="alert"
                        class="text-[11px] font-medium text-destructive"
                        data-testid="field-error"
                    >
                        {{ getFieldGuidance(field, node).error }}
                    </p>
                </div>
            </div>
        </template>

        <!-- Standard Text Controls use a separate loop so Vue's template
             type checker keeps the loop variable in scope after the
             conditional control chain above. -->
        <div
            v-for="field in fields.filter(
                (candidate) =>
                    candidate.control !== 'faq-list' &&
                    candidate.control !== 'textarea' &&
                    candidate.control !== 'url' &&
                    candidate.control !== 'media',
            )"
            :key="field.path"
            class="space-y-1"
        >
            <div class="flex items-center justify-between">
                <Label :for="'prop-' + field.key" class="text-xs font-medium">
                    {{ field.label }}
                    <span
                        v-if="field.required"
                        class="ml-0.5 font-bold text-destructive"
                        aria-hidden="true"
                        >*</span
                    >
                </Label>
                <span
                    v-if="field.maxLength"
                    class="text-[10px]"
                    :class="
                        String(node.props[field.key] || '').length >=
                        field.maxLength
                            ? 'font-semibold text-destructive'
                            : String(node.props[field.key] || '').length >=
                                field.maxLength * 0.9
                              ? 'text-amber-600 dark:text-amber-400'
                              : 'text-muted-foreground'
                    "
                >
                    {{ String(node.props[field.key] || '').length }}/{{
                        field.maxLength
                    }}
                </span>
            </div>
            <Input
                :id="'prop-' + field.key"
                :model-value="String(node.props[field.key] || '')"
                :disabled="readOnly"
                :maxlength="field.maxLength"
                :aria-required="field.required ? 'true' : undefined"
                :aria-invalid="!getFieldGuidance(field, node).isValid"
                :aria-describedby="
                    !getFieldGuidance(field, node).isValid
                        ? 'prop-' + field.key + '-error'
                        : undefined
                "
                class="h-8 text-xs"
                :class="{
                    'border-destructive focus-visible:ring-destructive/30':
                        !getFieldGuidance(field, node).isValid,
                }"
                @update:model-value="
                    emit('updateProp', {
                        key: field.key,
                        value: $event,
                    })
                "
            />
            <p
                v-if="!getFieldGuidance(field, node).isValid"
                :id="'prop-' + field.key + '-error'"
                role="alert"
                class="text-[11px] font-medium text-destructive"
                data-testid="field-error"
            >
                {{ getFieldGuidance(field, node).error }}
            </p>
        </div>
    </div>
</template>
