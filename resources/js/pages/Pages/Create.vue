<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText, LayoutTemplate } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import TemplateCard, {
    type TemplateSummary,
} from '@/components/templates/TemplateCard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type WebsiteData = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    website: WebsiteData;
    templates?: TemplateSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Websites',
        href: '/websites',
    },
    {
        title: props.website.name,
        href: `/websites/${props.website.id}`,
    },
    {
        title: 'New Page',
        href: `/websites/${props.website.id}/pages/create`,
    },
];

// Locate initial blank template if present
const defaultTemplate = computed(() => {
    if (!props.templates || props.templates.length === 0) return null;
    return (
        props.templates.find((t) => t.slug === 'blank') ?? props.templates[0]
    );
});

const form = useForm({
    title: '',
    slug: '',
    template_id: defaultTemplate.value ? defaultTemplate.value.id : null,
});

const activeCategory = ref<string>('all');

const availableCategories = computed(() => {
    if (!props.templates) return ['all'];
    const cats = new Set<string>();
    for (const t of props.templates) {
        cats.add(t.category);
    }
    return ['all', ...Array.from(cats)];
});

const filteredTemplates = computed(() => {
    if (!props.templates) return [];
    if (activeCategory.value === 'all') return props.templates;
    return props.templates.filter((t) => t.category === activeCategory.value);
});

const onTitleChange = () => {
    if (!form.slug || form.slug === '') {
        form.slug = form.title
            .toLowerCase()
            .trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
};

const selectTemplate = (template: TemplateSummary) => {
    form.template_id = template.id;
};

const submit = () => {
    form.post(`/websites/${props.website.id}/pages`);
};
</script>

<template>
    <Head title="Create Page" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="`/websites/${website.id}`">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Create Page
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Add a new page to
                        <span class="font-semibold text-foreground">{{
                            website.name
                        }}</span
                        >.
                    </p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Page Metadata Card -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <FileText class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>Page Metadata</CardTitle>
                                <CardDescription>
                                    Set the title and URL slug for this page.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-2">
                            <Label for="title">Page Title</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                type="text"
                                placeholder="Home, About Us, Services..."
                                required
                                autofocus
                                @blur="onTitleChange"
                            />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="space-y-2">
                            <Label for="slug">Page Slug</Label>
                            <div
                                class="flex items-center rounded-md border bg-muted/40 px-3"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >/{{ website.slug }}/</span
                                >
                                <input
                                    id="slug"
                                    v-model="form.slug"
                                    type="text"
                                    class="w-full border-0 bg-transparent px-2 py-2 text-sm focus:ring-0 focus:outline-none"
                                    placeholder="about-us"
                                    required
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Must be unique within this website.
                            </p>
                            <InputError :message="form.errors.slug" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Starter Template Picker Card -->
                <Card v-if="templates && templates.length > 0">
                    <CardHeader>
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <LayoutTemplate class="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle
                                        >Choose a Starting Layout</CardTitle
                                    >
                                    <CardDescription>
                                        Select a pre-built blueprint or begin
                                        with a clean canvas.
                                    </CardDescription>
                                </div>
                            </div>

                            <!-- Category Filter Pills -->
                            <div
                                v-if="availableCategories.length > 2"
                                class="flex flex-wrap gap-1"
                            >
                                <button
                                    v-for="cat in availableCategories"
                                    :key="cat"
                                    type="button"
                                    class="rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="[
                                        activeCategory === cat
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80',
                                    ]"
                                    @click="activeCategory = cat"
                                >
                                    {{
                                        cat === 'all'
                                            ? 'All Templates'
                                            : cat.replace('_', ' ')
                                    }}
                                </button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            role="radiogroup"
                            aria-label="Starter Templates"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <TemplateCard
                                v-for="tmpl in filteredTemplates"
                                :key="tmpl.id"
                                :template="tmpl"
                                :selected="form.template_id === tmpl.id"
                                @select="selectTemplate"
                            />
                        </div>

                        <p class="mt-4 text-xs text-muted-foreground">
                            Note: Instantiating a template copies layout and
                            components with fresh, independent IDs. Permanent
                            page persistence and autosave will be activated in
                            Phase 07.
                        </p>
                    </CardContent>
                </Card>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Button variant="outline" type="button" as-child>
                        <Link :href="`/websites/${website.id}`">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? 'Creating...'
                                : 'Create Page & Open Editor'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
