<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
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

const form = useForm({
    title: '',
    slug: '',
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

const submit = () => {
    form.post(`/websites/${props.website.id}/pages`);
};
</script>

<template>
    <Head title="Create Page" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-6">
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
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
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

                        <div class="flex items-center justify-end gap-3 pt-4">
                            <Button variant="outline" type="button" as-child>
                                <Link :href="`/websites/${website.id}`"
                                    >Cancel</Link
                                >
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{
                                    form.processing
                                        ? 'Creating...'
                                        : 'Create Page'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
