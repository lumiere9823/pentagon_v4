<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileText, Globe, Plus, ShieldCheck } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type WebsiteItem = {
    id: number;
    name: string;
    slug: string;
    status: string;
    pages_count: number;
    role: 'owner' | 'editor' | 'viewer';
    is_owner: boolean;
    created_at: string;
};

defineProps<{
    websites: WebsiteItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Websites',
        href: '/websites',
    },
];

const getRoleBadgeVariant = (role: string) => {
    switch (role) {
        case 'owner':
            return 'default';
        case 'editor':
            return 'secondary';
        default:
            return 'outline';
    }
};
</script>

<template>
    <Head title="Websites" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Websites</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage your published domains, web pages, and team
                        permissions.
                    </p>
                </div>
                <div>
                    <Button as-child>
                        <Link href="/websites/create">
                            <Plus class="mr-2 h-4 w-4" />
                            New Website
                        </Link>
                    </Button>
                </div>
            </div>

            <div
                v-if="websites.length === 0"
                class="flex min-h-[350px] flex-col items-center justify-center rounded-xl border border-dashed p-8 text-center"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Globe class="h-7 w-7" />
                </div>
                <h3 class="mt-4 text-lg font-semibold">No websites yet</h3>
                <p class="mt-2 mb-4 max-w-sm text-sm text-muted-foreground">
                    Get started by creating your first website. You'll be able
                    to design pages, customize layouts, and publish them online.
                </p>
                <Button as-child>
                    <Link href="/websites/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Website
                    </Link>
                </Button>
            </div>

            <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="site in websites"
                    :key="site.id"
                    class="flex flex-col justify-between transition-shadow hover:shadow-md"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle
                                class="line-clamp-1 text-lg font-semibold"
                                >{{ site.name }}</CardTitle
                            >
                            <Badge
                                :variant="getRoleBadgeVariant(site.role)"
                                class="capitalize"
                            >
                                <ShieldCheck
                                    v-if="site.is_owner"
                                    class="mr-1 h-3 w-3"
                                />
                                {{ site.role }}
                            </Badge>
                        </div>
                        <CardDescription
                            class="font-mono text-xs text-muted-foreground"
                        >
                            /{{ site.slug }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="py-2">
                        <div
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <FileText class="h-4 w-4" />
                            <span
                                >{{ site.pages_count }}
                                {{
                                    site.pages_count === 1 ? 'page' : 'pages'
                                }}</span
                            >
                        </div>
                    </CardContent>
                    <CardFooter class="pt-3">
                        <Button
                            variant="outline"
                            class="w-full justify-between"
                            as-child
                        >
                            <Link :href="`/websites/${site.id}`">
                                <span>Manage Site</span>
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
