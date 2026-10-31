<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Globe } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Websites', href: '/websites' },
            { title: 'New Website', href: '/websites/create' },
        ],
    },
});

const form = useForm({
    name: '',
    slug: '',
});

const onNameChange = () => {
    if (!form.slug || form.slug === '') {
        form.slug = form.name
            .toLowerCase()
            .trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
};

const submit = () => {
    form.post('/websites');
};
</script>

<template>
    <Head title="Create Website" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-6">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon" as-child>
                <Link href="/websites">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Create Website
                </h1>
                <p class="text-sm text-muted-foreground">
                    Set up a new website workspace to start creating and
                    publishing pages.
                </p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Globe class="h-5 w-5" />
                    </div>
                    <div>
                        <CardTitle>Website Details</CardTitle>
                        <CardDescription>
                            Enter the display name and slug identifier for your
                            website.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-2">
                        <Label for="name">Website Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="My Portfolio, Acme Store, etc."
                            required
                            autofocus
                            @blur="onNameChange"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="slug">URL Slug</Label>
                        <div
                            class="flex items-center rounded-md border bg-muted/40 px-3"
                        >
                            <span class="text-xs text-muted-foreground">/</span>
                            <input
                                id="slug"
                                v-model="form.slug"
                                type="text"
                                class="w-full border-0 bg-transparent px-2 py-2 text-sm focus:ring-0 focus:outline-none"
                                placeholder="acme-store"
                                required
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Only lowercase alphanumeric characters and dashes
                            are allowed.
                        </p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4">
                        <Button variant="outline" type="button" as-child>
                            <Link href="/websites">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{
                                form.processing
                                    ? 'Creating...'
                                    : 'Create Website'
                            }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <FileText class="h-5 w-5" />
                    </div>
                    <div>
                        <CardTitle>Starter pages</CardTitle>
                        <CardDescription>
                            Your website will be provisioned with three draft
                            starter pages.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="divide-y rounded-lg border bg-muted/20">
                    <div
                        class="flex items-center justify-between p-3 text-sm"
                        data-testid="starter-page-home"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-foreground"
                                >Home</span
                            >
                            <Badge variant="secondary" class="text-[10px]">
                                Homepage
                            </Badge>
                        </div>
                        <span class="font-mono text-xs text-muted-foreground"
                            >/</span
                        >
                    </div>
                    <div
                        class="flex items-center justify-between p-3 text-sm"
                        data-testid="starter-page-about"
                    >
                        <span class="font-medium text-foreground">About</span>
                        <span class="font-mono text-xs text-muted-foreground"
                            >/about</span
                        >
                    </div>
                    <div
                        class="flex items-center justify-between p-3 text-sm"
                        data-testid="starter-page-contact"
                    >
                        <span class="font-medium text-foreground">Contact</span>
                        <span class="font-mono text-xs text-muted-foreground"
                            >/contact</span
                        >
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    Starter pages are created as drafts and remain private until
                    published. You can edit content, customize SEO, or change
                    page slugs before publishing.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
