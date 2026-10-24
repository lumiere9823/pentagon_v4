<?php

namespace App\Http\Controllers;

use App\Domains\Pages\Services\PagePersistenceService;
use App\Http\Requests\SavePageContentRequest;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\JsonResponse;

class PageContentController extends Controller
{
    /**
     * Save page document content (manual or autosave) with optimistic concurrency and D-023 advisory locking.
     */
    public function update(
        SavePageContentRequest $request,
        Website $website,
        Page $page,
        PagePersistenceService $persistenceService
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $result = $persistenceService->savePageContent(
            page: $page,
            website: $website,
            document: $validated['document_json'],
            submittedLockVersion: (int) $validated['lock_version'],
            user: $user,
            changeSummary: $validated['change_summary'] ?? null,
            isAutosave: (bool) ($validated['is_autosave'] ?? false),
            seoMetadata: $validated['seo_metadata'] ?? null
        );

        return response()->json($result, $result['status']);
    }
}
