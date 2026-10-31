<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    CheckCircle2,
    Clock,
    Edit3,
    EyeOff,
    FileText,
    Inbox,
    LayoutTemplate,
    Plus,
    Settings,
    Shield,
    Trash2,
    UserPlus,
    Users,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PageItem = {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published' | 'archived';
    creator: { id: number; name: string } | null;
    updater: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
};

type MembershipItem = {
    id: number;
    user_id: number;
    role: 'owner' | 'editor' | 'viewer';
    is_primary_owner: boolean;
    user: { id: number; name: string; email: string } | null;
    created_at: string;
};

type WebsiteData = {
    id: number;
    name: string;
    slug: string;
    status: string;
    created_at: string;
};

type TemplateItem = {
    id: number;
    name: string;
    slug: string;
    category: string;
    description: string | null;
    is_active: boolean;
    created_at: string | null;
    creator: { id: number; name: string } | null;
};

type CanPermissions = {
    update: boolean;
    delete: boolean;
    create_page: boolean;
    manage_members: boolean;
    manage_templates?: boolean;
};

const props = defineProps<{
    website: WebsiteData;
    pages: PageItem[];
    memberships: MembershipItem[];
    templates?: TemplateItem[];
    userRole: 'owner' | 'editor' | 'viewer';
    can: CanPermissions;
}>();

const activeTab = ref<'pages' | 'templates' | 'members' | 'settings'>('pages');

defineOptions({
    layout: (pageProps: { website: WebsiteData }) => ({
        breadcrumbs: [
            { title: 'Websites', href: '/websites' },
            {
                title: pageProps.website.name,
                href: `/websites/${pageProps.website.id}`,
            },
        ],
    }),
});

// Website settings form
const settingsForm = useForm({
    name: props.website.name,
    slug: props.website.slug,
});

const updateSettings = () => {
    settingsForm.put(`/websites/${props.website.id}`);
};

const deleteWebsite = () => {
    if (
        confirm(
            'Are you sure you want to permanently delete this website and all its pages? This action cannot be undone.',
        )
    ) {
        router.delete(`/websites/${props.website.id}`);
    }
};

// Add member form
const memberForm = useForm({
    email: '',
    role: 'editor',
});

const addMember = () => {
    memberForm.post(`/websites/${props.website.id}/memberships`, {
        onSuccess: () => memberForm.reset(),
    });
};

const updateMemberRole = (membershipId: number, newRole: string) => {
    router.put(`/websites/${props.website.id}/memberships/${membershipId}`, {
        role: newRole,
    });
};

const removeMember = (membershipId: number, userName: string) => {
    if (
        confirm(
            `Are you sure you want to remove ${userName} from this website?`,
        )
    ) {
        router.delete(
            `/websites/${props.website.id}/memberships/${membershipId}`,
        );
    }
};

const deletePage = (pageId: number, pageTitle: string) => {
    if (confirm(`Are you sure you want to delete page "${pageTitle}"?`)) {
        router.delete(`/websites/${props.website.id}/pages/${pageId}`);
    }
};

const unpublishPage = (pageId: number, pageTitle: string) => {
    if (
        confirm(
            `Are you sure you want to unpublish "${pageTitle}"? The page will revert to draft status and become inaccessible to public visitors until published again.`,
        )
    ) {
        router.post(`/websites/${props.website.id}/pages/${pageId}/unpublish`);
    }
};

const deleteTemplate = (templateId: number, templateName: string) => {
    if (
        confirm(
            `Are you sure you want to delete template "${templateName}"? This action cannot be undone.`,
        )
    ) {
        router.delete(`/websites/${props.website.id}/templates/${templateId}`);
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'published':
            return {
                variant: 'default' as const,
                icon: CheckCircle2,
                label: 'Published',
            };
        case 'archived':
            return {
                variant: 'outline' as const,
                icon: Archive,
                label: 'Archived',
            };
        default:
            return {
                variant: 'secondary' as const,
                icon: Clock,
                label: 'Draft',
            };
    }
};
</script>

<template>
    <Head :title="website.name" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header section -->
        <div
            class="flex flex-col justify-between gap-4 border-b pb-6 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link href="/websites">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight">
                            {{ website.name }}
                        </h1>
                        <Badge
                            variant="outline"
                            class="font-mono text-xs text-muted-foreground"
                        >
                            /{{ website.slug }}
                        </Badge>
                        <Badge variant="default" class="capitalize">
                            Role: {{ userRole }}
                        </Badge>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button variant="outline" as-child>
                    <Link :href="`/websites/${website.id}/submissions`">
                        <Inbox class="mr-2 h-4 w-4" />
                        Submissions
                    </Link>
                </Button>
                <Button v-if="can.create_page" as-child>
                    <Link :href="`/websites/${website.id}/pages/create`">
                        <Plus class="mr-2 h-4 w-4" />
                        New Page
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Tab navigation -->
        <div class="flex border-b">
            <button
                type="button"
                class="flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'pages'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'pages'"
            >
                <FileText class="h-4 w-4" />
                <span>Pages ({{ pages.length }})</span>
            </button>
            <button
                type="button"
                class="flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'templates'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'templates'"
            >
                <LayoutTemplate class="h-4 w-4" />
                <span>Templates ({{ templates?.length ?? 0 }})</span>
            </button>
            <button
                v-if="can.manage_members"
                type="button"
                class="flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'members'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'members'"
            >
                <Users class="h-4 w-4" />
                <span>Team Members ({{ memberships.length }})</span>
            </button>
            <button
                v-if="can.update"
                type="button"
                class="flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    activeTab === 'settings'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'settings'"
            >
                <Settings class="h-4 w-4" />
                <span>Website Settings</span>
            </button>
            <Link
                :href="`/websites/${website.id}/submissions`"
                class="flex items-center gap-2 border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <Inbox class="h-4 w-4" />
                <span>Submissions</span>
            </Link>
        </div>

        <!-- Tab 1: Pages List -->
        <div v-if="activeTab === 'pages'" class="flex flex-col gap-4">
            <div
                v-if="pages.length === 0"
                class="flex min-h-[300px] flex-col items-center justify-center rounded-xl border border-dashed p-8 text-center"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <FileText class="h-6 w-6" />
                </div>
                <h3 class="mt-4 text-lg font-semibold">No pages created yet</h3>
                <p class="mt-2 mb-4 max-w-sm text-sm text-muted-foreground">
                    Pages represent individual URLs in your website. Start by
                    creating a landing or home page.
                </p>
                <Button v-if="can.create_page" as-child>
                    <Link :href="`/websites/${website.id}/pages/create`">
                        <Plus class="mr-2 h-4 w-4" />
                        Create First Page
                    </Link>
                </Button>
            </div>

            <div v-else class="rounded-xl border bg-card">
                <div
                    class="grid grid-cols-12 gap-4 border-b px-6 py-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    <div class="col-span-5">Page Details</div>
                    <div class="col-span-3">Status</div>
                    <div class="col-span-2">Updated</div>
                    <div class="col-span-2 text-right">Actions</div>
                </div>
                <div
                    v-for="page in pages"
                    :key="page.id"
                    class="grid grid-cols-12 items-center gap-4 border-b px-6 py-4 last:border-b-0 hover:bg-muted/30"
                >
                    <div class="col-span-5">
                        <div class="font-medium text-foreground">
                            {{ page.title }}
                        </div>
                        <div class="font-mono text-xs text-muted-foreground">
                            /{{ page.slug }}
                        </div>
                    </div>
                    <div class="col-span-3">
                        <Badge
                            :variant="getStatusBadge(page.status).variant"
                            class="capitalize"
                        >
                            <component
                                :is="getStatusBadge(page.status).icon"
                                class="mr-1 h-3 w-3"
                            />
                            {{ getStatusBadge(page.status).label }}
                        </Badge>
                    </div>
                    <div class="col-span-2 text-xs text-muted-foreground">
                        {{ new Date(page.updated_at).toLocaleDateString() }}
                    </div>
                    <div class="col-span-2 flex items-center justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1 px-2 text-xs"
                            as-child
                        >
                            <Link
                                :href="`/websites/${website.id}/pages/${page.id}/builder`"
                                title="Open Page Builder"
                            >
                                <LayoutTemplate class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Builder</span>
                            </Link>
                        </Button>
                        <Button
                            v-if="can.create_page"
                            variant="ghost"
                            size="icon"
                            as-child
                        >
                            <Link
                                :href="`/websites/${website.id}/pages/${page.id}/edit`"
                            >
                                <Edit3 class="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button
                            v-if="
                                can.create_page && page.status === 'published'
                            "
                            variant="ghost"
                            size="icon"
                            class="text-amber-600 hover:bg-amber-500/10 dark:text-amber-400"
                            title="Unpublish page"
                            data-testid="unpublish-page-btn"
                            @click="unpublishPage(page.id, page.title)"
                        >
                            <EyeOff class="h-4 w-4" />
                        </Button>
                        <Button
                            v-if="can.create_page"
                            variant="ghost"
                            size="icon"
                            class="text-destructive hover:bg-destructive/10"
                            @click="deletePage(page.id, page.title)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Templates List -->
        <div v-if="activeTab === 'templates'" class="flex flex-col gap-4">
            <div
                v-if="!templates || templates.length === 0"
                class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center"
            >
                <LayoutTemplate class="h-10 w-10 text-muted-foreground/60" />
                <h3 class="mt-3 text-base font-semibold">
                    No custom templates yet
                </h3>
                <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                    Save any layout from the Page Builder canvas as a reusable
                    custom template. Custom templates will appear here and in
                    the New Page creator.
                </p>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="tpl in templates"
                    :key="tpl.id"
                    class="flex flex-col justify-between"
                    data-testid="template-card"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="text-base">{{
                                tpl.name
                            }}</CardTitle>
                            <Badge
                                variant="outline"
                                class="text-[10px] capitalize"
                            >
                                {{ tpl.category }}
                            </Badge>
                        </div>
                        <CardDescription class="line-clamp-2 text-xs">
                            {{
                                tpl.description ||
                                'Custom website layout template.'
                            }}
                        </CardDescription>
                    </CardHeader>

                    <CardFooter
                        class="flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
                    >
                        <span>By {{ tpl.creator?.name || 'Team member' }}</span>
                        <div class="flex items-center gap-1.5">
                            <Button
                                v-if="can.create_page"
                                variant="outline"
                                size="sm"
                                class="h-7 text-xs"
                                as-child
                            >
                                <Link
                                    :href="`/websites/${website.id}/pages/create?template_id=${tpl.id}`"
                                >
                                    Use Template
                                </Link>
                            </Button>
                            <Button
                                v-if="can.manage_templates"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                title="Delete Template"
                                aria-label="Delete template"
                                @click="deleteTemplate(tpl.id, tpl.name)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </div>

        <!-- Tab 2: Team Members -->
        <div
            v-if="activeTab === 'members' && can.manage_members"
            class="grid gap-6 lg:grid-cols-3"
        >
            <div class="lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Team Members</CardTitle>
                        <CardDescription>
                            Users with access to manage or view pages in this
                            website workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-for="member in memberships"
                            :key="member.id"
                            class="flex items-center justify-between border-b px-6 py-4 last:border-b-0"
                        >
                            <div>
                                <div class="font-medium text-foreground">
                                    {{ member.user?.name ?? 'Unknown user' }}
                                    <span
                                        v-if="member.is_primary_owner"
                                        class="ml-1 text-xs font-normal text-muted-foreground"
                                        >(Primary Owner)</span
                                    >
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ member.user?.email }}
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <template v-if="member.is_primary_owner">
                                    <Badge variant="default">Owner</Badge>
                                </template>
                                <template v-else>
                                    <select
                                        class="rounded-md border bg-background px-2.5 py-1 text-xs"
                                        :value="member.role"
                                        @change="
                                            updateMemberRole(
                                                member.id,
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option value="editor">Editor</option>
                                        <option value="viewer">Viewer</option>
                                    </select>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:bg-destructive/10"
                                        @click="
                                            removeMember(
                                                member.id,
                                                member.user?.name ??
                                                    member.user?.email ??
                                                    'member',
                                            )
                                        "
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </template>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div>
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <UserPlus class="h-4 w-4" />
                            Add Team Member
                        </CardTitle>
                        <CardDescription>
                            Add an existing user by email to collaborate on this
                            website.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="addMember" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="member-email">User Email</Label>
                                <Input
                                    id="member-email"
                                    v-model="memberForm.email"
                                    type="email"
                                    placeholder="colleague@example.com"
                                    required
                                />
                                <InputError
                                    :message="memberForm.errors.email"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="member-role">Access Role</Label>
                                <select
                                    id="member-role"
                                    v-model="memberForm.role"
                                    class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="editor">
                                        Editor (Create & edit pages)
                                    </option>
                                    <option value="viewer">
                                        Viewer (Read-only access)
                                    </option>
                                </select>
                                <InputError :message="memberForm.errors.role" />
                            </div>

                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="memberForm.processing"
                            >
                                {{
                                    memberForm.processing
                                        ? 'Adding...'
                                        : 'Add Member'
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Tab 3: Settings -->
        <div
            v-if="activeTab === 'settings' && can.update"
            class="flex max-w-2xl flex-col gap-6"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Website Configuration</CardTitle>
                    <CardDescription>
                        Update the website name or URL slug.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updateSettings" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="settings-name">Website Name</Label>
                            <Input
                                id="settings-name"
                                v-model="settingsForm.name"
                                type="text"
                                required
                            />
                            <InputError :message="settingsForm.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="settings-slug">URL Slug</Label>
                            <Input
                                id="settings-slug"
                                v-model="settingsForm.slug"
                                type="text"
                                required
                            />
                            <InputError :message="settingsForm.errors.slug" />
                        </div>

                        <div class="flex justify-end pt-2">
                            <Button
                                type="submit"
                                :disabled="settingsForm.processing"
                            >
                                {{
                                    settingsForm.processing
                                        ? 'Saving...'
                                        : 'Save Changes'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card v-if="can.delete" class="border-destructive/30">
                <CardHeader>
                    <CardTitle class="text-destructive">Danger Zone</CardTitle>
                    <CardDescription>
                        Permanently delete this website, all associated pages,
                        and revoke all member access.
                    </CardDescription>
                </CardHeader>
                <CardFooter>
                    <Button variant="destructive" @click="deleteWebsite">
                        Delete Website
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </div>
</template>
