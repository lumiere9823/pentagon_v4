<script setup lang="ts">
import InspectorNodeIdentity from './InspectorNodeIdentity.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    getInheritedPropInfo,
    isPropOverridden,
    resolveResponsiveProp,
} from '@/lib/responsive';
import type {
    DocumentNode,
    InspectorPropertyMetadata,
    ViewportMode,
} from '@/types/document';

const props = defineProps<{
    node: DocumentNode;
    fields: InspectorPropertyMetadata[];
    readOnly: boolean;
    currentViewport: ViewportMode;
}>();

const emit = defineEmits<{
    (e: 'updateProp', payload: { key: string; value: unknown }): void;
    (e: 'resetStyle', key: string): void;
}>();

function getPropValue(field: InspectorPropertyMetadata): unknown {
    return (
        resolveResponsiveProp(props.node, field.key, props.currentViewport) ??
        field.default
    );
}

function getInheritedInfo(key: string) {
    return getInheritedPropInfo(props.node, key, props.currentViewport);
}

function isOverridden(key: string): boolean {
    return isPropOverridden(props.node, key, props.currentViewport);
}
</script>

<template>
    <div class="space-y-4">
        <div v-for="field in fields" :key="field.path" class="space-y-1.5">
            <div
                v-if="currentViewport !== 'desktop' && field.responsive"
                class="flex items-center justify-between"
            >
                <Label
                    :for="'setting-' + field.key"
                    class="text-xs font-medium"
                >
                    {{ field.label }}
                </Label>
                <div class="flex items-center gap-1.5">
                    <template v-if="isOverridden(field.key)">
                        <Badge
                            variant="secondary"
                            class="border border-amber-500/20 bg-amber-500/15 px-1.5 py-0 text-[10px] text-amber-700 dark:text-amber-400"
                        >
                            Overridden
                        </Badge>
                        <Button
                            v-if="!readOnly"
                            variant="ghost"
                            size="sm"
                            class="h-5 px-1.5 text-[10px] text-muted-foreground hover:text-foreground"
                            @click="emit('resetStyle', field.key)"
                        >
                            Reset
                        </Button>
                    </template>
                    <span
                        v-else
                        class="text-[10px] text-muted-foreground italic"
                    >
                        Inherited from {{ getInheritedInfo(field.key).source }}
                    </span>
                </div>
            </div>

            <div
                v-if="field.control === 'toggle'"
                class="flex items-center justify-between rounded-lg border bg-muted/10 p-2.5"
            >
                <Label
                    :for="'setting-' + field.key"
                    class="cursor-pointer text-xs font-medium"
                >
                    {{ field.label }}
                </Label>
                <input
                    :id="'setting-' + field.key"
                    type="checkbox"
                    :checked="Boolean(getPropValue(field))"
                    :disabled="readOnly"
                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                    @change="
                        emit('updateProp', {
                            key: field.key,
                            value: ($event.target as HTMLInputElement).checked,
                        })
                    "
                />
            </div>

            <div v-else-if="field.control === 'select'" class="space-y-1">
                <Label
                    v-if="currentViewport === 'desktop' || !field.responsive"
                    :for="'setting-' + field.key"
                    class="text-xs font-medium"
                >
                    {{ field.label }}
                </Label>
                <select
                    :id="'setting-' + field.key"
                    :value="getPropValue(field)"
                    :disabled="readOnly"
                    class="h-8 w-full rounded-md border border-input bg-transparent px-2.5 text-xs shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @change="
                        emit('updateProp', {
                            key: field.key,
                            value: ($event.target as HTMLSelectElement).value,
                        })
                    "
                >
                    <option
                        v-for="option in field.enum || []"
                        :key="String(option)"
                        :value="option"
                        class="bg-background text-foreground capitalize"
                    >
                        {{ option }}
                    </option>
                </select>
            </div>
        </div>

        <InspectorNodeIdentity :node="node" />
    </div>
</template>
