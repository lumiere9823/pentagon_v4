<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    AlertCircle,
    CheckCircle2,
    Code2,
    Eye,
    Globe,
    Image as ImageIcon,
    RotateCcw,
    Search,
    Share2,
    X,
} from '@lucide/vue';
import MediaLibraryModal from '@/components/media/MediaLibraryModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { MediaAssetItem } from '@/types/media';
import type { PageSeoMetadata } from '@/types/seo';

interface LocalSeoForm {
    meta_title: string;
    meta_description: string;
    canonical_url: string;
    og_title: string;
    og_description: string;
    og_image_url: string;
    twitter_card: 'summary' | 'summary_large_image';
    noindex: boolean;
    nofollow: boolean;
    schema_type: 'WebPage' | 'Article' | 'Organization';
}

const props = defineProps<{
    open: boolean;
    websiteId: number;
    websiteName: string;
    websiteSlug: string;
    pageTitle: string;
    pageSlug: string;
    modelValue: PageSeoMetadata;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:modelValue', value: PageSeoMetadata): void;
    (e: 'save'): void;
}>();

const activeTab = ref<'seo' | 'social' | 'schema'>('seo');
const mediaLibraryOpen = ref(false);
const structuredDataRaw = ref<string>('');

const localSeo = ref<LocalSeoForm>({
    meta_title: '',
    meta_description: '',
    canonical_url: '',
    og_title: '',
    og_description: '',
    og_image_url: '',
    twitter_card: 'summary_large_image',
    noindex: false,
    nofollow: false,
    schema_type: 'WebPage',
});

watch(
    () => props.modelValue,
    (val) => {
        localSeo.value = {
            meta_title: val?.meta_title ?? '',
            meta_description: val?.meta_description ?? '',
            canonical_url: val?.canonical_url ?? '',
            og_title: val?.og_title ?? '',
            og_description: val?.og_description ?? '',
            og_image_url: val?.og_image_url ?? '',
            twitter_card: val?.twitter_card ?? 'summary_large_image',
            noindex: Boolean(val?.noindex),
            nofollow: Boolean(val?.nofollow),
            schema_type: val?.schema_type ?? 'WebPage',
        };

        if (val?.structured_data_json) {
            try {
                structuredDataRaw.value = JSON.stringify(
                    val.structured_data_json,
                    null,
                    2,
                );
            } catch {
                structuredDataRaw.value = '';
            }
        } else {
            structuredDataRaw.value = '';
        }
    },
    { immediate: true, deep: true },
);

const effectiveTitle = computed(
    () => localSeo.value.meta_title || props.pageTitle,
);
const effectiveDescription = computed(
    () =>
        localSeo.value.meta_description ||
        'No custom description provided for this page.',
);
const effectiveCanonical = computed(
    () =>
        localSeo.value.canonical_url ||
        `https://${props.websiteSlug}.pentagon.site/${props.pageSlug}`,
);

const titleLength = computed(() => localSeo.value.meta_title.length);
const descLength = computed(() => localSeo.value.meta_description.length);

const structuredDataError = computed<string | null>(() => {
    const raw = structuredDataRaw.value.trim();
    if (!raw) return null;
    try {
        const parsed = JSON.parse(raw);
        if (
            typeof parsed !== 'object' ||
            parsed === null ||
            Array.isArray(parsed)
        ) {
            return 'Structured data must be a valid JSON object.';
        }
        return null;
    } catch (e: any) {
        return e?.message || 'Invalid JSON syntax';
    }
});

const parsedStructuredData = computed<Record<string, unknown> | null>(() => {
    const raw = structuredDataRaw.value.trim();
    if (!raw || structuredDataError.value) return null;
    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
});

const resetToSchemaBoilerplate = () => {
    const template: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type': localSeo.value.schema_type,
        name: effectiveTitle.value,
        description: effectiveDescription.value,
        url: effectiveCanonical.value,
    };

    if (localSeo.value.schema_type === 'Article') {
        template.headline = effectiveTitle.value;
        template.author = {
            '@type': 'Person',
            name: 'Editorial Team',
        };
    } else if (localSeo.value.schema_type === 'Organization') {
        template.legalName = props.websiteName;
    }

    structuredDataRaw.value = JSON.stringify(template, null, 2);
};

const handleMediaSelect = (asset: MediaAssetItem) => {
    localSeo.value.og_image_url = asset.url;
    mediaLibraryOpen.value = false;
};

const handleSave = () => {
    if (structuredDataError.value) {
        activeTab.value = 'schema';
        return;
    }

    emit('update:modelValue', {
        meta_title: localSeo.value.meta_title.trim() || null,
        meta_description: localSeo.value.meta_description.trim() || null,
        canonical_url: localSeo.value.canonical_url.trim() || null,
        og_title: localSeo.value.og_title.trim() || null,
        og_description: localSeo.value.og_description.trim() || null,
        og_image_url: localSeo.value.og_image_url.trim() || null,
        twitter_card: localSeo.value.twitter_card,
        noindex: localSeo.value.noindex,
        nofollow: localSeo.value.nofollow,
        schema_type: localSeo.value.schema_type,
        structured_data_json: parsedStructuredData.value,
    });
    emit('save');
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent
            class="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden p-0"
        >
            <DialogHeader class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="rounded-md bg-primary/10 p-2 text-primary">
                            <Globe class="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-semibold"
                                >SEO & Social Sharing</DialogTitle
                            >
                            <DialogDescription
                                class="text-xs text-muted-foreground"
                            >
                                Configure search engine visibility, social share
                                cards, and structured schema for {{ pageTitle }}
                            </DialogDescription>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="mt-4 flex border-b">
                    <button
                        type="button"
                        class="flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'seo'
                                ? 'border-primary font-semibold text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        data-testid="seo-tab-btn"
                        @click="activeTab = 'seo'"
                    >
                        <Search class="h-4 w-4" />
                        <span>Search Engine (Google)</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'social'
                                ? 'border-primary font-semibold text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        data-testid="social-tab-btn"
                        @click="activeTab = 'social'"
                    >
                        <Share2 class="h-4 w-4" />
                        <span>Social Cards (OG / Twitter)</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                        :class="
                            activeTab === 'schema'
                                ? 'border-primary font-semibold text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        data-testid="schema-tab-btn"
                        @click="activeTab = 'schema'"
                    >
                        <Code2 class="h-4 w-4" />
                        <span>Structured Data (Schema.org)</span>
                    </button>
                </div>
            </DialogHeader>

            <div class="flex-1 space-y-6 overflow-y-auto px-6 py-4">
                <!-- Tab: General SEO -->
                <div v-if="activeTab === 'seo'" class="space-y-6">
                    <!-- Google SERP Card Preview -->
                    <div
                        class="rounded-lg border bg-slate-50/50 p-4 dark:bg-slate-900/40"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >Google Search Snippet Preview</span
                            >
                            <Badge variant="outline" class="text-[10px]"
                                >Desktop & Mobile</Badge
                            >
                        </div>
                        <div
                            class="rounded-md border bg-white p-4 font-sans shadow-xs dark:bg-slate-950"
                        >
                            <div
                                class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400"
                            >
                                <span
                                    >https://{{
                                        websiteSlug
                                    }}.pentagon.site</span
                                >
                                <span>›</span>
                                <span>{{ pageSlug }}</span>
                            </div>
                            <h3
                                class="mt-1 line-clamp-1 cursor-pointer text-base font-medium text-blue-600 hover:underline dark:text-blue-400"
                            >
                                {{ effectiveTitle }} - {{ websiteName }}
                            </h3>
                            <p
                                class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-700 dark:text-slate-300"
                            >
                                {{ effectiveDescription }}
                            </p>
                        </div>
                    </div>

                    <!-- Meta Title Field -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label
                                for="meta_title"
                                class="text-xs font-semibold"
                                >Page Title (Meta Title)</Label
                            >
                            <span
                                class="text-[11px]"
                                :class="
                                    titleLength > 60
                                        ? 'font-semibold text-amber-600'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ titleLength }}/60 chars (Recommended: 50-60)
                            </span>
                        </div>
                        <Input
                            id="meta_title"
                            v-model="localSeo.meta_title"
                            :placeholder="pageTitle"
                            class="h-9 text-xs"
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Leave empty to default to the page title: "{{
                                pageTitle
                            }}".
                        </p>
                    </div>

                    <!-- Meta Description Field -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label
                                for="meta_description"
                                class="text-xs font-semibold"
                                >Meta Description</Label
                            >
                            <span
                                class="text-[11px]"
                                :class="
                                    descLength > 160
                                        ? 'font-semibold text-amber-600'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ descLength }}/160 chars (Recommended:
                                150-160)
                            </span>
                        </div>
                        <Textarea
                            id="meta_description"
                            v-model="localSeo.meta_description"
                            placeholder="Briefly describe the content of this page to entice search engine visitors..."
                            class="h-20 text-xs"
                        />
                    </div>

                    <!-- Canonical URL Field -->
                    <div class="space-y-2">
                        <Label for="canonical_url" class="text-xs font-semibold"
                            >Canonical URL Override</Label
                        >
                        <Input
                            id="canonical_url"
                            v-model="localSeo.canonical_url"
                            :placeholder="effectiveCanonical"
                            class="h-9 font-mono text-xs"
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Defaults automatically to the authoritative
                            published page URL.
                        </p>
                    </div>

                    <!-- Indexing & Robot Directives (noindex & nofollow) -->
                    <div class="space-y-3 rounded-lg border bg-muted/20 p-4">
                        <div class="flex items-start gap-3">
                            <input
                                id="noindex"
                                v-model="localSeo.noindex"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <div class="space-y-0.5">
                                <label
                                    for="noindex"
                                    class="cursor-pointer text-xs font-medium"
                                >
                                    Discourage search engines from indexing this
                                    page (noindex)
                                </label>
                                <p class="text-[11px] text-muted-foreground">
                                    Adds a &lt;meta name="robots"
                                    content="noindex, nofollow"&gt; header tag
                                    and excludes the page from the XML sitemap.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 border-t pt-3">
                            <input
                                id="nofollow"
                                v-model="localSeo.nofollow"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <div class="space-y-0.5">
                                <label
                                    for="nofollow"
                                    class="cursor-pointer text-xs font-medium"
                                >
                                    Instruct search engines not to follow
                                    outbound links (nofollow)
                                </label>
                                <p class="text-[11px] text-muted-foreground">
                                    Adds "nofollow" to the robots directive so
                                    crawler bots don't pass PageRank to links.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Social Cards -->
                <div v-else-if="activeTab === 'social'" class="space-y-6">
                    <!-- Social Card Preview -->
                    <div
                        class="rounded-lg border bg-slate-50/50 p-4 dark:bg-slate-900/40"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >Social Card Preview ({{
                                    localSeo.twitter_card ===
                                    'summary_large_image'
                                        ? 'Large Image Card'
                                        : 'Summary Card'
                                }})</span
                            >
                            <Badge variant="outline" class="text-[10px]"
                                >OpenGraph & Twitter</Badge
                            >
                        </div>

                        <div
                            class="mx-auto max-w-md overflow-hidden rounded-md border bg-white shadow-xs dark:bg-slate-950"
                            :class="{
                                'flex items-center':
                                    localSeo.twitter_card === 'summary',
                            }"
                        >
                            <!-- Image banner -->
                            <div
                                class="flex items-center justify-center overflow-hidden bg-slate-200 dark:bg-slate-800"
                                :class="
                                    localSeo.twitter_card ===
                                    'summary_large_image'
                                        ? 'aspect-video w-full'
                                        : 'h-28 w-28 shrink-0'
                                "
                            >
                                <img
                                    v-if="localSeo.og_image_url"
                                    :src="localSeo.og_image_url"
                                    alt="Social preview banner"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex flex-col items-center gap-1 text-muted-foreground"
                                >
                                    <ImageIcon class="h-7 w-7 stroke-1" />
                                    <span class="text-[10px]">No image</span>
                                </div>
                            </div>
                            <!-- Card body -->
                            <div
                                class="flex-1 p-3"
                                :class="{
                                    'border-t':
                                        localSeo.twitter_card ===
                                        'summary_large_image',
                                }"
                            >
                                <div
                                    class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{ websiteSlug }}.pentagon.site
                                </div>
                                <h4
                                    class="mt-0.5 line-clamp-1 text-sm font-semibold text-foreground"
                                >
                                    {{ localSeo.og_title || effectiveTitle }}
                                </h4>
                                <p
                                    class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                                >
                                    {{
                                        localSeo.og_description ||
                                        effectiveDescription
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Twitter Card Selector -->
                    <div class="space-y-2">
                        <Label class="text-xs font-semibold"
                            >Twitter / X Card Layout</Label
                        >
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                class="flex flex-col items-start rounded-lg border p-3 text-left transition-colors"
                                :class="
                                    localSeo.twitter_card ===
                                    'summary_large_image'
                                        ? 'border-primary bg-primary/5 text-primary'
                                        : 'border-border bg-card text-muted-foreground hover:text-foreground'
                                "
                                @click="
                                    localSeo.twitter_card =
                                        'summary_large_image'
                                "
                            >
                                <span class="text-xs font-semibold"
                                    >Large Image Card</span
                                >
                                <span
                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                >
                                    Prominent 1.91:1 banner above text
                                    (Recommended)
                                </span>
                            </button>
                            <button
                                type="button"
                                class="flex flex-col items-start rounded-lg border p-3 text-left transition-colors"
                                :class="
                                    localSeo.twitter_card === 'summary'
                                        ? 'border-primary bg-primary/5 text-primary'
                                        : 'border-border bg-card text-muted-foreground hover:text-foreground'
                                "
                                @click="localSeo.twitter_card = 'summary'"
                            >
                                <span class="text-xs font-semibold"
                                    >Summary Card</span
                                >
                                <span
                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                >
                                    Compact square thumbnail alongside text
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- OG Image Picker -->
                    <div class="space-y-2">
                        <Label class="text-xs font-semibold"
                            >Social Share Image (og:image /
                            twitter:image)</Label
                        >
                        <div class="flex items-center gap-2">
                            <Input
                                v-model="localSeo.og_image_url"
                                placeholder="/storage/media/..."
                                class="h-9 flex-1 font-mono text-xs"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9 shrink-0 gap-1 text-xs"
                                @click="mediaLibraryOpen = true"
                            >
                                <ImageIcon class="h-3.5 w-3.5" />
                                <span>Choose Image</span>
                            </Button>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Recommended dimensions: 1200 x 630 pixels (Aspect
                            ratio 1.91:1) in JPG, PNG, or WebP format.
                        </p>
                    </div>

                    <!-- Custom OG Title -->
                    <div class="space-y-2">
                        <Label for="og_title" class="text-xs font-semibold"
                            >Custom Social Title (og:title)</Label
                        >
                        <Input
                            id="og_title"
                            v-model="localSeo.og_title"
                            :placeholder="effectiveTitle"
                            class="h-9 text-xs"
                        />
                    </div>

                    <!-- Custom OG Description -->
                    <div class="space-y-2">
                        <Label
                            for="og_description"
                            class="text-xs font-semibold"
                            >Custom Social Description (og:description)</Label
                        >
                        <Textarea
                            id="og_description"
                            v-model="localSeo.og_description"
                            :placeholder="effectiveDescription"
                            class="h-16 text-xs"
                        />
                    </div>
                </div>

                <!-- Tab: Structured Data (Schema.org) -->
                <div v-else-if="activeTab === 'schema'" class="space-y-6">
                    <div class="rounded-lg border bg-muted/20 p-4">
                        <div
                            class="flex items-center gap-2 text-xs font-semibold text-foreground"
                        >
                            <Code2 class="h-4 w-4 text-primary" />
                            <span>JSON-LD Structured Data (Schema.org)</span>
                        </div>
                        <p
                            class="mt-1 text-xs leading-relaxed text-muted-foreground"
                        >
                            Structured data helps search engines understand the
                            context of your page and enables rich results in
                            Google search.
                        </p>
                    </div>

                    <!-- Schema Type Selection -->
                    <div class="space-y-2">
                        <Label for="schema_type" class="text-xs font-semibold"
                            >Primary Schema Type</Label
                        >
                        <select
                            id="schema_type"
                            v-model="localSeo.schema_type"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-xs transition-colors"
                        >
                            <option value="WebPage">
                                WebPage — Standard general content
                            </option>
                            <option value="Article">
                                Article — News story, blog post, or article
                            </option>
                            <option value="Organization">
                                Organization — Brand, enterprise, or company
                                homepage
                            </option>
                        </select>
                    </div>

                    <!-- JSON-LD Editor with Syntax Validation -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label
                                for="structured_data"
                                class="text-xs font-semibold"
                            >
                                Custom JSON-LD Code
                            </Label>
                            <div class="flex items-center gap-2">
                                <Badge
                                    v-if="structuredDataError"
                                    variant="destructive"
                                    class="text-[10px]"
                                >
                                    JSON Syntax Error
                                </Badge>
                                <Badge
                                    v-else-if="structuredDataRaw.trim()"
                                    variant="default"
                                    class="bg-emerald-600 text-[10px] text-white"
                                >
                                    Valid JSON-LD
                                </Badge>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-6 gap-1 text-[11px]"
                                    @click="resetToSchemaBoilerplate"
                                >
                                    <RotateCcw class="h-3 w-3" />
                                    <span>Reset to Template</span>
                                </Button>
                            </div>
                        </div>

                        <Textarea
                            id="structured_data"
                            v-model="structuredDataRaw"
                            placeholder='{\n  "@context": "https://schema.org",\n  "@type": "WebPage",\n  "name": "..."\n}'
                            class="h-40 font-mono text-xs leading-relaxed"
                            :class="{
                                'border-destructive':
                                    Boolean(structuredDataError),
                            }"
                        />

                        <div
                            v-if="structuredDataError"
                            role="alert"
                            class="flex items-center gap-1.5 text-xs text-destructive"
                        >
                            <AlertCircle class="h-3.5 w-3.5 shrink-0" />
                            <span>{{ structuredDataError }}</span>
                        </div>
                        <p v-else class="text-[11px] text-muted-foreground">
                            Leave empty to automatically compile a standard
                            Schema.org payload during release publishing.
                        </p>
                    </div>
                </div>
            </div>

            <DialogFooter class="border-t bg-muted/10 px-6 py-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs"
                    @click="emit('update:open', false)"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    size="sm"
                    class="h-8 gap-1.5 text-xs"
                    data-testid="apply-seo-btn"
                    :disabled="Boolean(structuredDataError)"
                    @click="handleSave"
                >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    <span>Apply & Save SEO</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Media Library Modal for picking OG Image -->
    <MediaLibraryModal
        :open="mediaLibraryOpen"
        :website-id="websiteId"
        mode="select"
        @update:open="(val) => (mediaLibraryOpen = val)"
        @select="handleMediaSelect"
    />
</template>
