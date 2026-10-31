<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Archive,
    ArrowLeft,
    CheckCircle2,
    Clock,
    Eye,
    FileText,
    Inbox,
    Mail,
    ShieldAlert,
    Trash2,
    X,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useModalFocus } from '@/composables/useModalFocus';

export type SubmissionStatus = 'new' | 'read' | 'archived' | 'spam';

export interface FormSubmissionItem {
    id: string;
    website_id: number;
    page_id: number | null;
    form_name: string;
    payload: Record<string, unknown>;
    ip_address: string | null;
    user_agent: string | null;
    status: SubmissionStatus;
    created_at: string;
    page?: { id: number; title: string; slug: string } | null;
}

export interface PaginatedSubmissions {
    data: FormSubmissionItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    website: { id: number; name: string; slug: string };
    submissions: PaginatedSubmissions;
    distinctFormNames: string[];
    filters: { form_name: string | null; status: string | null };
    userRole: 'owner' | 'editor' | 'viewer';
    can: { manage_submissions: boolean };
}>();

defineOptions({
    layout: (pageProps: { website: { id: number; name: string } }) => ({
        breadcrumbs: [
            { title: 'Websites', href: '/websites' },
            {
                title: pageProps.website.name,
                href: `/websites/${pageProps.website.id}`,
            },
            {
                title: 'Submissions',
                href: `/websites/${pageProps.website.id}/submissions`,
            },
        ],
    }),
});

// Filter actions
const applyStatusFilter = (status: string | null) => {
    router.get(
        `/websites/${props.website.id}/submissions`,
        {
            status: status || undefined,
            form_name: props.filters.form_name || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const applyFormFilter = (formName: string | null) => {
    router.get(
        `/websites/${props.website.id}/submissions`,
        {
            status: props.filters.status || undefined,
            form_name: formName || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

// Modal Detail Inspection State
const activeDetailModal = ref(false);
const selectedSubmission = ref<FormSubmissionItem | null>(null);
const detailModalRef = ref<HTMLElement | null>(null);

useModalFocus(activeDetailModal, detailModalRef, {
    onClose: () => {
        activeDetailModal.value = false;
        selectedSubmission.value = null;
    },
});

const openDetails = (sub: FormSubmissionItem) => {
    selectedSubmission.value = sub;
    activeDetailModal.value = true;
};

// Status update action
const isUpdatingStatus = ref(false);
const updateStatus = (
    submission: FormSubmissionItem,
    newStatus: SubmissionStatus,
) => {
    if (!props.can.manage_submissions || isUpdatingStatus.value) return;

    isUpdatingStatus.value = true;
    router.patch(
        `/websites/${props.website.id}/submissions/${submission.id}`,
        { status: newStatus },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                submission.status = newStatus;
                if (selectedSubmission.value?.id === submission.id) {
                    selectedSubmission.value.status = newStatus;
                }
            },
            onFinish: () => {
                isUpdatingStatus.value = false;
            },
        },
    );
};

// Deletion State & Modal
const activeDeleteModal = ref(false);
const submissionToDelete = ref<FormSubmissionItem | null>(null);
const deleteModalRef = ref<HTMLElement | null>(null);
const isDeleting = ref(false);

useModalFocus(activeDeleteModal, deleteModalRef, {
    onClose: () => {
        activeDeleteModal.value = false;
        submissionToDelete.value = null;
    },
});

const confirmDelete = (sub: FormSubmissionItem) => {
    if (!props.can.manage_submissions) return;
    submissionToDelete.value = sub;
    activeDeleteModal.value = true;
};

const executeDelete = () => {
    if (!props.can.manage_submissions || !submissionToDelete.value) return;

    isDeleting.value = true;
    router.delete(
        `/websites/${props.website.id}/submissions/${submissionToDelete.value.id}`,
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                activeDeleteModal.value = false;
                submissionToDelete.value = null;
                if (selectedSubmission.value) {
                    activeDetailModal.value = false;
                    selectedSubmission.value = null;
                }
            },
            onFinish: () => {
                isDeleting.value = false;
            },
        },
    );
};

function formatPayloadSummary(payload: Record<string, unknown>): string {
    const entries = Object.entries(payload).slice(0, 3);
    if (entries.length === 0) return 'No payload fields';
    return entries
        .map(
            ([k, v]) =>
                `${k}: ${typeof v === 'string' ? v : JSON.stringify(v)}`,
        )
        .join(', ');
}

function formatDate(isoString: string): string {
    try {
        const d = new Date(isoString);
        return d.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return isoString;
    }
}
</script>

<template>
    <Head :title="`Submissions - ${website.name}`" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link
                        :href="`/websites/${website.id}`"
                        aria-label="Back to Website"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground"
                    >
                        <Inbox class="h-6 w-6 text-primary" />
                        Form Submissions
                    </h1>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ website.name }} &bull; Total:
                        {{ submissions.total }} submissions received
                    </p>
                </div>
            </div>

            <!-- Viewer Notice Badge if read-only -->
            <div
                v-if="!can.manage_submissions"
                class="flex items-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 text-xs text-amber-700 dark:text-amber-400"
            >
                <AlertCircle class="h-4 w-4 shrink-0" />
                <span>Viewer mode: Submissions are read-only.</span>
            </div>
        </div>

        <!-- Filter Controls -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b pb-4"
        >
            <!-- Status Filter Pills -->
            <div
                class="flex flex-wrap items-center gap-1.5"
                role="tablist"
                aria-label="Filter by submission status"
            >
                <Button
                    type="button"
                    size="sm"
                    :variant="filters.status === null ? 'default' : 'outline'"
                    class="h-8 text-xs font-medium"
                    @click="applyStatusFilter(null)"
                >
                    All ({{ submissions.total }})
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="filters.status === 'new' ? 'default' : 'outline'"
                    class="h-8 text-xs font-medium"
                    @click="applyStatusFilter('new')"
                >
                    New
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="filters.status === 'read' ? 'default' : 'outline'"
                    class="h-8 text-xs font-medium"
                    @click="applyStatusFilter('read')"
                >
                    Read
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="
                        filters.status === 'archived' ? 'default' : 'outline'
                    "
                    class="h-8 text-xs font-medium"
                    @click="applyStatusFilter('archived')"
                >
                    Archived
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="filters.status === 'spam' ? 'default' : 'outline'"
                    class="h-8 text-xs font-medium"
                    @click="applyStatusFilter('spam')"
                >
                    Spam
                </Button>
            </div>

            <!-- Form Name Filter -->
            <div
                v-if="distinctFormNames.length > 1"
                class="flex items-center gap-2 text-xs"
            >
                <span class="font-medium text-muted-foreground">Form:</span>
                <select
                    :value="filters.form_name || ''"
                    class="h-8 rounded-md border border-input bg-background px-2.5 text-xs text-foreground focus:ring-2 focus:ring-ring focus:outline-none"
                    @change="
                        applyFormFilter(
                            ($event.target as HTMLSelectElement).value || null,
                        )
                    "
                >
                    <option value="">All Forms</option>
                    <option
                        v-for="name in distinctFormNames"
                        :key="name"
                        :value="name"
                    >
                        {{ name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Submissions Content List -->
        <div
            v-if="submissions.data.length === 0"
            class="flex min-h-[320px] flex-col items-center justify-center rounded-xl border border-dashed bg-card p-8 text-center"
        >
            <div
                class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary"
            >
                <Inbox class="h-6 w-6" />
            </div>
            <h2 class="mt-4 text-base font-semibold">No submissions found</h2>
            <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                {{
                    filters.status || filters.form_name
                        ? 'No submissions match your current filter criteria.'
                        : 'Form submissions generated from published pages will appear here.'
                }}
            </p>
            <Button
                v-if="filters.status || filters.form_name"
                variant="outline"
                size="sm"
                class="mt-4 text-xs"
                @click="router.get(`/websites/${website.id}/submissions`)"
            >
                Clear Filters
            </Button>
        </div>

        <!-- Submissions Table -->
        <div v-else class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div
                class="grid grid-cols-12 gap-3 border-b bg-muted/40 px-4 py-3 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
            >
                <div class="col-span-3 sm:col-span-2">Received</div>
                <div class="col-span-2 sm:col-span-2">Form</div>
                <div class="hidden sm:col-span-2 sm:block">Page</div>
                <div class="col-span-4 sm:col-span-4">
                    Submitted Data Preview
                </div>
                <div class="col-span-3 text-right sm:col-span-2">Actions</div>
            </div>

            <div class="divide-y">
                <div
                    v-for="sub in submissions.data"
                    :key="sub.id"
                    class="grid grid-cols-12 items-center gap-3 px-4 py-3 text-xs transition-colors hover:bg-muted/30"
                    :class="{
                        'bg-primary/5 font-medium': sub.status === 'new',
                    }"
                    data-testid="submission-row"
                >
                    <!-- Date & Status Badge -->
                    <div class="col-span-3 flex flex-col gap-1 sm:col-span-2">
                        <span class="text-[11px] font-medium text-foreground">{{
                            formatDate(sub.created_at)
                        }}</span>
                        <div class="flex items-center gap-1.5">
                            <Badge
                                :variant="
                                    sub.status === 'new'
                                        ? 'default'
                                        : sub.status === 'spam'
                                          ? 'destructive'
                                          : sub.status === 'archived'
                                            ? 'secondary'
                                            : 'outline'
                                "
                                class="px-1.5 py-0 text-[10px] capitalize"
                            >
                                {{ sub.status }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Form Name -->
                    <div class="col-span-2 truncate sm:col-span-2">
                        <span
                            class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-foreground"
                        >
                            {{ sub.form_name }}
                        </span>
                    </div>

                    <!-- Page Source -->
                    <div
                        class="hidden truncate text-[11px] text-muted-foreground sm:col-span-2 sm:block"
                    >
                        <span
                            v-if="sub.page"
                            class="flex items-center gap-1"
                            :title="sub.page.title"
                        >
                            <FileText
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            <span class="truncate">{{ sub.page.title }}</span>
                        </span>
                        <span v-else class="text-muted-foreground/60 italic"
                            >Direct / Global</span
                        >
                    </div>

                    <!-- Data Preview -->
                    <div
                        class="col-span-4 truncate text-[11px] text-muted-foreground sm:col-span-4"
                    >
                        <span
                            class="truncate"
                            :title="formatPayloadSummary(sub.payload)"
                        >
                            {{ formatPayloadSummary(sub.payload) }}
                        </span>
                    </div>

                    <!-- Actions -->
                    <div
                        class="col-span-3 flex items-center justify-end gap-1 sm:col-span-2"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 w-7 p-0"
                            title="Inspect Details"
                            data-testid="inspect-btn"
                            @click="openDetails(sub)"
                        >
                            <Eye class="h-3.5 w-3.5" />
                            <span class="sr-only">Inspect Details</span>
                        </Button>

                        <template v-if="can.manage_submissions">
                            <!-- Toggle Read / Archived -->
                            <Button
                                v-if="sub.status === 'new'"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0 text-primary"
                                title="Mark as Read"
                                data-testid="mark-read-btn"
                                @click="updateStatus(sub, 'read')"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                <span class="sr-only">Mark as Read</span>
                            </Button>
                            <Button
                                v-else-if="sub.status === 'read'"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0 text-muted-foreground"
                                title="Archive"
                                data-testid="archive-btn"
                                @click="updateStatus(sub, 'archived')"
                            >
                                <Archive class="h-3.5 w-3.5" />
                                <span class="sr-only">Archive</span>
                            </Button>

                            <!-- Delete -->
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 w-7 p-0 text-destructive hover:bg-destructive/10"
                                title="Delete Submission"
                                data-testid="delete-btn"
                                @click="confirmDelete(sub)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span class="sr-only">Delete</span>
                            </Button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="submissions.last_page > 1"
                class="flex items-center justify-between border-t bg-muted/20 px-4 py-3 text-xs text-muted-foreground"
            >
                <span
                    >Page {{ submissions.current_page }} of
                    {{ submissions.last_page }}</span
                >
                <div class="flex items-center gap-1.5">
                    <Button
                        v-for="(link, i) in submissions.links"
                        :key="i"
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="!link.url"
                        class="h-7 px-2.5 text-xs"
                        as-child
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Inspect Submission Modal Dialog -->
        <teleport to="body">
            <div
                v-if="activeDetailModal && selectedSubmission"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
                @click.self="activeDetailModal = false"
            >
                <div
                    ref="detailModalRef"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="submission-details-title"
                    tabindex="-1"
                    class="relative max-h-[90vh] w-full max-w-2xl space-y-4 overflow-y-auto rounded-xl border bg-background p-6 shadow-xl"
                >
                    <div
                        class="flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <Mail class="h-5 w-5 text-primary" />
                            <h2
                                id="submission-details-title"
                                class="text-base font-semibold"
                            >
                                Submission Details
                            </h2>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-muted-foreground"
                            aria-label="Close dialog"
                            @click="activeDetailModal = false"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <!-- Meta Information Grid -->
                    <div
                        class="grid grid-cols-2 gap-3 rounded-lg bg-muted/30 p-3 text-xs sm:grid-cols-4"
                    >
                        <div>
                            <span
                                class="block text-[10px] font-semibold text-muted-foreground uppercase"
                                >Form Name</span
                            >
                            <span class="font-mono font-medium">{{
                                selectedSubmission.form_name
                            }}</span>
                        </div>
                        <div>
                            <span
                                class="block text-[10px] font-semibold text-muted-foreground uppercase"
                                >Status</span
                            >
                            <Badge
                                variant="outline"
                                class="mt-0.5 text-[10px] capitalize"
                                >{{ selectedSubmission.status }}</Badge
                            >
                        </div>
                        <div>
                            <span
                                class="block text-[10px] font-semibold text-muted-foreground uppercase"
                                >Submitted</span
                            >
                            <span class="font-medium">{{
                                formatDate(selectedSubmission.created_at)
                            }}</span>
                        </div>
                        <div>
                            <span
                                class="block text-[10px] font-semibold text-muted-foreground uppercase"
                                >Source Page</span
                            >
                            <span class="block truncate">{{
                                selectedSubmission.page?.title ||
                                'Global / Unknown'
                            }}</span>
                        </div>
                    </div>

                    <!-- Submitted Fields Key-Value Table -->
                    <div class="space-y-2">
                        <h3
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Submitted Field Values
                        </h3>
                        <div
                            class="divide-y overflow-hidden rounded-lg border text-xs"
                        >
                            <div
                                v-for="(val, key) in selectedSubmission.payload"
                                :key="key"
                                class="grid grid-cols-3 gap-2 bg-card p-3 hover:bg-muted/20"
                            >
                                <span
                                    class="font-semibold break-all text-foreground"
                                    >{{ key }}</span
                                >
                                <span
                                    class="col-span-2 font-mono break-all whitespace-pre-wrap text-muted-foreground"
                                >
                                    {{
                                        typeof val === 'object' && val !== null
                                            ? JSON.stringify(val, null, 2)
                                            : String(val)
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Client Security & Network Meta -->
                    <div
                        class="space-y-1 border-t pt-3 text-[11px] text-muted-foreground"
                    >
                        <div
                            v-if="selectedSubmission.ip_address"
                            class="flex gap-2"
                        >
                            <span class="font-medium text-foreground"
                                >IP Address:</span
                            >
                            <span class="font-mono">{{
                                selectedSubmission.ip_address
                            }}</span>
                        </div>
                        <div
                            v-if="selectedSubmission.user_agent"
                            class="flex gap-2"
                        >
                            <span class="font-medium text-foreground"
                                >User Agent:</span
                            >
                            <span
                                class="truncate font-mono"
                                :title="selectedSubmission.user_agent"
                                >{{ selectedSubmission.user_agent }}</span
                            >
                        </div>
                    </div>

                    <!-- Modal Footer Actions -->
                    <div
                        class="flex items-center justify-between border-t pt-4"
                    >
                        <div
                            v-if="can.manage_submissions"
                            class="flex items-center gap-2"
                        >
                            <Button
                                v-if="selectedSubmission.status !== 'read'"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs"
                                @click="
                                    updateStatus(selectedSubmission, 'read')
                                "
                            >
                                Mark Read
                            </Button>
                            <Button
                                v-if="selectedSubmission.status !== 'archived'"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs"
                                @click="
                                    updateStatus(selectedSubmission, 'archived')
                                "
                            >
                                Archive
                            </Button>
                            <Button
                                v-if="selectedSubmission.status !== 'spam'"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 text-xs text-amber-600 hover:text-amber-700"
                                @click="
                                    updateStatus(selectedSubmission, 'spam')
                                "
                            >
                                Mark Spam
                            </Button>
                        </div>
                        <div
                            v-else
                            class="text-xs text-muted-foreground italic"
                        >
                            Read-only viewer
                        </div>

                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="h-8 text-xs"
                            @click="activeDetailModal = false"
                        >
                            Close
                        </Button>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Delete Confirmation Modal Dialog -->
        <teleport to="body">
            <div
                v-if="activeDeleteModal && submissionToDelete"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
                @click.self="activeDeleteModal = false"
            >
                <div
                    ref="deleteModalRef"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="delete-dialog-title"
                    tabindex="-1"
                    class="relative w-full max-w-md space-y-4 rounded-xl border bg-background p-6 shadow-xl"
                >
                    <div class="flex items-center gap-3 text-destructive">
                        <Trash2 class="h-5 w-5" />
                        <h2
                            id="delete-dialog-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Delete Form Submission
                        </h2>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Are you sure you want to permanently delete this
                        submission from form
                        <strong class="text-foreground"
                            >"{{ submissionToDelete.form_name }}"</strong
                        >? This action cannot be undone.
                    </p>

                    <div
                        class="flex items-center justify-end gap-2 border-t pt-4"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs"
                            :disabled="isDeleting"
                            @click="activeDeleteModal = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            class="h-8 text-xs"
                            :disabled="isDeleting"
                            @click="executeDelete"
                        >
                            {{
                                isDeleting ? 'Deleting...' : 'Delete Submission'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
</template>
