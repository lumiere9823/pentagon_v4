<?php

namespace App\Http\Controllers;

use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Domains\Media\Services\MediaConcurrencyLock;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Domains\Templates\Services\TemplateInstantiationService;
use App\Enums\MediaAssetStatus;
use App\Http\Requests\StorePageTemplateRequest;
use App\Http\Requests\UpdatePageTemplateRequest;
use App\Models\MediaAsset;
use App\Models\PageTemplate;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageTemplateController extends Controller
{
    /**
     * List all available templates (global starter templates + website-scoped custom templates).
     */
    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('viewAny', [PageTemplate::class, $website]);

        $query = PageTemplate::forWebsite($website->id)->active();

        if ($request->filled('category')) {
            $query->category((string) $request->input('category'));
        }

        if ($request->filled('search')) {
            $term = '%'.(string) $request->input('search').'%';
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $templates = $query
            ->orderByRaw('CASE WHEN website_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Inspect a specific template.
     */
    public function show(Request $request, Website $website, PageTemplate $template): JsonResponse
    {
        // Enforce IDOR protection: Custom template must belong to this website
        if ($template->website_id !== null && $template->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('view', $template);

        return response()->json([
            'data' => $template,
        ]);
    }

    /**
     * Store a new custom website-scoped template with D-023 concurrency protection.
     */
    public function store(
        StorePageTemplateRequest $request,
        Website $website,
        DocumentValidator $validator,
        MediaUsageScanner $scanner,
        MediaConcurrencyLock $lockService
    ): JsonResponse {
        $this->authorize('create', [PageTemplate::class, $website]);

        $validated = $request->validated();
        $document = $validated['document_json'];

        // 1. Authoritative Schema v1 validation
        $validationResult = $validator->validate($document);
        if (! $validationResult->isValid()) {
            return response()->json([
                'message' => 'Template document failed Schema v1 validation.',
                'errors' => array_map(fn ($e) => $e->toArray(), $validationResult->getErrors()),
            ], 422);
        }

        $canonicalDocument = $validationResult->getSanitizedDocument();
        if ($canonicalDocument === null) {
            return response()->json([
                'message' => 'Template document could not be canonicalized.',
            ], 422);
        }

        // 2. Extract referenced media asset IDs and inspect ownership
        $inspection = $scanner->inspectAssetReferences($canonicalDocument, $website);
        if ($inspection['indeterminate']) {
            return response()->json([
                'message' => 'Template document media references cannot be scanned reliably.',
                'error_code' => 'media_scan_indeterminate',
            ], 422);
        }

        if ($inspection['has_foreign_media'] || ! empty($inspection['unresolved_media_paths'])) {
            return response()->json([
                'message' => 'One or more media assets referenced in this template are unavailable or invalid.',
                'error_code' => 'media_asset_unavailable',
            ], 422);
        }

        $newAssetIds = $inspection['asset_ids'];

        // 3. Coordinate persistence under transaction-scoped advisory locks
        return DB::transaction(function () use (
            $website,
            $validated,
            $canonicalDocument,
            $newAssetIds,
            $lockService,
            $request
        ) {
            if (! empty($newAssetIds)) {
                // 3a. Lock in ascending numerical order
                $lockService->lockAssets($newAssetIds);

                // 3b. Verify under lock: active, untrashed, and owned by this website
                $activeAssets = MediaAsset::where('website_id', $website->id)
                    ->whereIn('id', $newAssetIds)
                    ->where('status', MediaAssetStatus::ACTIVE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                if (count($activeAssets) !== count($newAssetIds)) {
                    return response()->json([
                        'message' => 'One or more media assets referenced in this template are unavailable or invalid.',
                        'error_code' => 'media_asset_unavailable',
                    ], 422);
                }
            }

            // 3c. Generate unique slug within website scope
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug ?: 'custom-template';
            $counter = 1;
            while (PageTemplate::where('website_id', $website->id)->where('slug', $slug)->exists()) {
                $counter++;
                $slug = "{$baseSlug}-{$counter}";
            }

            // 3d. Persist template record
            $template = $website->templates()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? 'general',
                'schema_version' => 1,
                'document_json' => $canonicalDocument,
                'thumbnail_path' => $validated['thumbnail_path'] ?? null,
                'is_active' => true,
                'created_by' => $request->user()?->id,
            ]);

            return response()->json([
                'message' => 'Template created successfully.',
                'data' => $template,
            ], 201);
        });
    }

    /**
     * Update an existing custom website template with D-023 concurrency protection.
     */
    public function update(
        UpdatePageTemplateRequest $request,
        Website $website,
        PageTemplate $template,
        DocumentValidator $validator,
        MediaUsageScanner $scanner,
        MediaConcurrencyLock $lockService
    ): JsonResponse {
        // Global starter templates are immutable
        if ($template->isGlobal()) {
            abort(403, 'Global starter templates are immutable.');
        }

        // IDOR check: Template must belong to this website
        if ($template->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('update', $template);

        $validated = $request->validated();
        $hasDocument = array_key_exists('document_json', $validated);
        $newDocument = $hasDocument ? $validated['document_json'] : null;

        // 1. Authoritative Schema v1 validation of incoming document before transaction
        if ($hasDocument) {
            $validationResult = $validator->validate($newDocument);
            if (! $validationResult->isValid()) {
                return response()->json([
                    'message' => 'Template document failed Schema v1 validation.',
                    'errors' => array_map(fn ($e) => $e->toArray(), $validationResult->getErrors()),
                ], 422);
            }

            $newDocument = $validationResult->getSanitizedDocument();
            if ($newDocument === null) {
                return response()->json([
                    'message' => 'Template document could not be canonicalized.',
                ], 422);
            }

            // Inspect new document references and verify tenant ownership
            $newInspection = $scanner->inspectAssetReferences($newDocument, $website);
            if ($newInspection['indeterminate']) {
                return response()->json([
                    'message' => 'Template document media references cannot be scanned reliably.',
                    'error_code' => 'media_scan_indeterminate',
                ], 422);
            }

            if ($newInspection['has_foreign_media'] || ! empty($newInspection['unresolved_media_paths'])) {
                return response()->json([
                    'message' => 'One or more media assets referenced in this template are unavailable or invalid.',
                    'error_code' => 'media_asset_unavailable',
                ], 422);
            }

            $newAssetIds = $newInspection['asset_ids'];
        } else {
            $newAssetIds = [];
        }

        // 2. Coordinate persistence under transaction-scoped row and advisory locks
        return DB::transaction(function () use (
            $website,
            $template,
            $validated,
            $hasDocument,
            $newDocument,
            $newAssetIds,
            $scanner,
            $lockService
        ) {
            // 3. Reload target template from the correct website scope using lockForUpdate()
            /** @var PageTemplate|null $lockedTemplate */
            $lockedTemplate = PageTemplate::where('website_id', $website->id)
                ->where('id', $template->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTemplate) {
                abort(404);
            }

            // 4. Re-check template mutability on the locked record
            if ($lockedTemplate->isGlobal()) {
                abort(403, 'Global starter templates are immutable.');
            }

            $allLockIds = [];

            // 5. Read and scan old document from this locked record, not from stale route-bound model
            $oldInspection = $scanner->inspectAssetReferences($lockedTemplate->document_json, $website);
            if ($oldInspection['indeterminate']) {
                return response()->json([
                    'message' => 'Existing template media references cannot be scanned reliably.',
                    'error_code' => 'media_scan_indeterminate',
                ], 422);
            }

            $oldAssetIds = $oldInspection['asset_ids'];

            // 7. Build union of freshly scanned old asset IDs and incoming asset IDs
            if ($hasDocument) {
                $allLockIds = array_values(array_unique(array_merge($newAssetIds, $oldAssetIds)));
            } else {
                $allLockIds = $oldAssetIds;
            }

            // 8. Deduplicate and sort IDs numerically in ascending order (handled inside lockAssets)
            if (! empty($allLockIds)) {
                // 9. Acquire D-023 advisory locks
                $lockService->lockAssets($allLockIds);

                // 10. Revalidate referenced assets under locks: active, untrashed, owned by this website
                if (! empty($newAssetIds)) {
                    $activeAssets = MediaAsset::where('website_id', $website->id)
                        ->whereIn('id', $newAssetIds)
                        ->where('status', MediaAssetStatus::ACTIVE)
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->pluck('id')
                        ->all();

                    if (count($activeAssets) !== count($newAssetIds)) {
                        return response()->json([
                            'message' => 'One or more media assets referenced in this template are unavailable or invalid.',
                            'error_code' => 'media_asset_unavailable',
                        ], 422);
                    }
                }
            }

            // 11. Persist the template update in the same transaction
            $updateData = [];
            if (array_key_exists('name', $validated)) {
                $updateData['name'] = $validated['name'];
            }
            if (array_key_exists('description', $validated)) {
                $updateData['description'] = $validated['description'];
            }
            if (array_key_exists('category', $validated)) {
                $updateData['category'] = $validated['category'];
            }
            if ($hasDocument) {
                $updateData['document_json'] = $newDocument;
            }
            if (array_key_exists('thumbnail_path', $validated)) {
                $updateData['thumbnail_path'] = $validated['thumbnail_path'];
            }
            if (array_key_exists('is_active', $validated)) {
                $updateData['is_active'] = $validated['is_active'];
            }

            $lockedTemplate->update($updateData);

            return response()->json([
                'message' => 'Template updated successfully.',
                'data' => $lockedTemplate->fresh(),
            ], 200);
        });
    }

    /**
     * Delete a custom website template.
     */
    public function destroy(Request $request, Website $website, PageTemplate $template): JsonResponse|RedirectResponse
    {
        // Global starter templates are immutable
        if ($template->isGlobal()) {
            abort(403, 'Global starter templates are immutable.');
        }

        // IDOR check: Template must belong to this website
        if ($template->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('delete', $template);

        DB::transaction(function () use ($website, $template) {
            $lockedTemplate = PageTemplate::where('website_id', $website->id)
                ->where('id', $template->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTemplate || $lockedTemplate->isGlobal()) {
                abort(404);
            }

            $lockedTemplate->delete();
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Template deleted successfully.',
            ], 200);
        }

        return redirect()->back()->with('success', 'Template deleted successfully.');
    }

    /**
     * Instantiate a template with renewed unique node IDs.
     */
    public function instantiate(
        Request $request,
        Website $website,
        PageTemplate $template,
        TemplateInstantiationService $instantiationService
    ): JsonResponse {
        // IDOR check: Custom template must belong to this website
        if ($template->website_id !== null && $template->website_id !== $website->id) {
            abort(404);
        }

        // Inactive templates cannot be instantiated
        if (! $template->is_active) {
            abort(404);
        }

        $this->authorize('view', $template);

        $pageTitle = (string) $request->input('title', 'New Page');
        $document = $instantiationService->instantiate($template, $pageTitle);

        return response()->json([
            'template_id' => $template->id,
            'template_name' => $template->name,
            'document' => $document,
        ], 200);
    }
}
