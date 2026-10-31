<?php

namespace App\Http\Controllers;

use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Domains\SEO\Services\RedirectManager;
use App\Domains\Templates\Services\TemplateInstantiationService;
use App\Enums\PageStatus;
use App\Http\Requests\StorePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageTemplate;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function create(Request $request, Website $website): Response
    {
        $this->authorize('create', [Page::class, $website]);

        $templates = PageTemplate::forWebsite($website->id)
            ->active()
            ->orderByRaw('CASE WHEN website_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'slug', 'description', 'category', 'thumbnail_path', 'website_id']);

        return Inertia::render('Pages/Create', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'slug' => $website->slug,
            ],
            'templates' => $templates,
        ]);
    }

    public function store(
        StorePageRequest $request,
        Website $website,
        TemplateInstantiationService $instantiationService
    ): RedirectResponse {
        $user = $request->user();

        $page = $website->pages()->create([
            'title' => $request->validated('title'),
            'slug' => $request->validated('slug'),
            'status' => PageStatus::DRAFT,
            'lock_version' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        if ($request->filled('template_id')) {
            $templateId = (int) $request->input('template_id');
            $template = PageTemplate::forWebsite($website->id)
                ->active()
                ->where('id', $templateId)
                ->first();

            if ($template) {
                $initialDocument = $instantiationService->instantiate($template, $page->title);
                $page->document_json = $initialDocument;
                $page->save();

                PageRevision::create([
                    'page_id' => $page->id,
                    'revision_number' => 1,
                    'schema_version' => 1,
                    'document_json' => $initialDocument,
                    'document_hash' => PageRevision::canonicalizeDocument($initialDocument),
                    'change_summary' => 'Initialized from template: '.$template->name,
                    'is_autosave' => false,
                    'created_by' => $user->id,
                    'created_at' => now(),
                ]);

                return redirect()->route('websites.pages.builder', [
                    'website' => $website,
                    'page' => $page,
                    'template' => $template->id,
                ]);
            }
        }

        // Canonical empty Document Schema v1 document for template-free creation
        $emptyDoc = DocumentValidator::createEmptyDocument($page->title);
        $validationResult = (new DocumentValidator)->validate($emptyDoc);
        if ($validationResult->isValid()) {
            $page->document_json = $validationResult->getSanitizedDocument() ?? $emptyDoc;
            $page->save();
        }

        return redirect()->route('websites.show', $website)
            ->with('success', 'Page created successfully.');
    }

    public function edit(Request $request, Website $website, Page $page): Response
    {
        // Enforce IDOR protection: Page must belong to this website
        if ($page->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('update', $page);

        return Inertia::render('Pages/Edit', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'slug' => $website->slug,
            ],
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'created_at' => $page->created_at?->toIso8601String(),
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
            'statuses' => PageStatus::values(),
        ]);
    }

    public function builder(
        Request $request,
        Website $website,
        Page $page,
        TemplateInstantiationService $instantiationService
    ): Response {
        // Enforce IDOR protection: Page must belong to this website
        if ($page->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('view', $page);

        // Priority 1: Persisted document from database
        $initialDocument = $page->document_json;

        // Priority 2: Fallback to template instantiation if document_json is null and template query parameter is present
        if ($initialDocument === null && $request->filled('template')) {
            $template = PageTemplate::forWebsite($website->id)
                ->active()
                ->where('id', $request->input('template'))
                ->first();

            if ($template) {
                $initialDocument = $instantiationService->instantiate($template, $page->title);
            }
        }

        $user = $request->user();
        $membership = $website->memberships()->where('user_id', $user->id)->first();
        $userRole = $membership?->role->value ?? 'viewer';

        return Inertia::render('Pages/Builder', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'slug' => $website->slug,
            ],
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'lock_version' => $page->lock_version,
                'updated_at' => $page->updated_at?->toIso8601String(),
                'document_json' => $initialDocument,
                'seo_metadata' => $page->seo_metadata ?? [],
            ],
            'userRole' => $userRole,
            'initialDocument' => $initialDocument,
            'can' => [
                'edit' => $user->can('update', $page),
                'delete' => $user->can('delete', $page),
            ],
        ]);
    }

    public function update(UpdatePageRequest $request, Website $website, Page $page): RedirectResponse
    {
        // Enforce IDOR protection: Page must belong to this website
        if ($page->website_id !== $website->id) {
            abort(404);
        }

        $user = $request->user();

        $oldSlug = $page->slug;
        $newSlug = $request->validated('slug');

        $page->update([
            'title' => $request->validated('title'),
            'slug' => $newSlug,
            'status' => PageStatus::from($request->validated('status')),
            'updated_by' => $user->id,
        ]);

        if ($oldSlug !== $newSlug && ($page->status === PageStatus::PUBLISHED || $page->published_revision_id !== null)) {
            app(RedirectManager::class)->createRedirect($website, $oldSlug, $newSlug);
        }

        return redirect()->route('websites.show', $website)
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(Request $request, Website $website, Page $page): RedirectResponse
    {
        // Enforce IDOR protection: Page must belong to this website
        if ($page->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('delete', $page);

        if ($page->is_homepage) {
            throw ValidationException::withMessages([
                'page' => 'Set another page as homepage before deleting this page.',
            ]);
        }

        $page->delete();

        return redirect()->route('websites.show', $website)
            ->with('success', 'Page deleted successfully.');
    }
}
