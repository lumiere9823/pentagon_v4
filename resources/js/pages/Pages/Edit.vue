<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Edit, LayoutTemplate } from '@lucide/vue';
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

type WebsiteData = {
    id: number;
    name: string;
    slug: string;
};

type PageData = {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published' | 'archived';
    created_at: string;
    updated_at: string;
};

const props = defineProps<{
    website: WebsiteData;
    page: PageData;
    statuses: string[];
}>();

defineOptions({
    layout: (pageProps: { website: WebsiteData; page: PageData }) => ({
        breadcrumbs: [
            { title: 'Websites', href: '/websites' },
            {
                title: pageProps.website.name,
                href: `/websites/${pageProps.website.id}`,
            },
            {
                title: pageProps.page.title,
                href: `/websites/${pageProps.website.id}`,
            },
            {
                title: 'Edit Metadata',
                href: `/websites/${pageProps.website.id}/pages/${pageProps.page.id}/edit`,
            },
        ],
    }),
});

const form = useForm({
    title: props.page.title,
    slug: props.page.slug,
    status: props.page.status,
});

const submit = () => {
    form.put(`/websites/${props.website.id}/pages/${props.page.id}`);
};
</script>

<template>
    <Head :title="`Edit ${page.title}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="`/websites/${website.id}`">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Edit Page Metadata
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Update title, slug or publish lifecycle status for this
                        page.
                    </p>
                </div>
            </div>

            <Button as-child>
                <Link
                    :href="`/websites/${website.id}/pages/${page.id}/builder`"
                >
                    <LayoutTemplate class="mr-1.5 h-4 w-4" />
                    Open Page Builder
                </Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Edit class="h-5 w-5" />
                    </div>
                    <div>
                        <CardTitle>Page Settings</CardTitle>
                        <CardDescription>
                            Modify page parameters and editorial lifecycle
                            status.
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
                            required
                            autofocus
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
                                required
                            />
                        </div>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="space-y-2">
                        <Label for="status">Page Status</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            required
                        >
                            <option
                                v-for="st in statuses"
                                :key="st"
                                :value="st"
                                class="capitalize"
                            >
                                {{ st.charAt(0).toUpperCase() + st.slice(1) }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Controls visibility and editorial lifecycle stage.
                        </p>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4">
                        <Button variant="outline" type="button" as-child>
                            <Link :href="`/websites/${website.id}`"
                                >Cancel</Link
                            >
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
