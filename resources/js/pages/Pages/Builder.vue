<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EditorShell from '@/components/editor/EditorShell.vue';

interface WebsiteSummary {
    id: number;
    name: string;
    slug: string;
}

interface PageSummary {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published' | 'archived';
    updated_at: string | null;
}

const props = defineProps<{
    website: WebsiteSummary;
    page: PageSummary;
    userRole: string;
    can: {
        edit: boolean;
        delete: boolean;
    };
}>();
</script>

<template>
    <Head :title="`Page Builder — ${page.title}`" />

    <EditorShell
        :website="website"
        :page="page"
        :user-role="userRole"
        :can="can"
    />
</template>
