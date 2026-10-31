<?php

namespace App\Http\Controllers;

use App\Domains\Pages\Services\PagePersistenceService;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageRevisionController extends Controller
{
    /**
     * List historical revisions for a page.
     */
    public function index(Request $request, Website $website, Page $page): JsonResponse
    {
        // Prevent IDOR: Page must belong to this website
        if ($page->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('view', $page);

        $query = $page->revisions()
            ->with(['creator:id,name'])
            ->orderBy('revision_number', 'desc');

        if ($request->filled('limit')) {
            $limit = min(max((int) $request->query('limit'), 1), 100);
            $query->limit($limit);
        }

        $revisions = $query->get([
            'id',
            'page_id',
            'revision_number',
            'schema_version',
            'document_hash',
            'change_summary',
            'is_autosave',
            'created_by',
            'created_at',
        ]);

        return response()->json([
            'revisions' => $revisions,
        ]);
    }

    /**
     * Inspect a specific immutable historical revision document.
     */
    public function show(Request $request, Website $website, Page $page, PageRevision $revision): JsonResponse
    {
        // Prevent IDOR: Page must belong to this website, and revision must belong to this page
        if ($page->website_id !== $website->id || $revision->page_id !== $page->id) {
            abort(404);
        }

        $this->authorize('view', $page);

        $revision->load('creator:id,name');

        return response()->json([
            'revision' => $revision,
        ]);
    }

    /**
     * Roll back the page working draft to an immutable historical revision.
     * Strictly creates a new sequential revision (D-029).
     */
    public function rollback(
        Request $request,
        Website $website,
        Page $page,
        PageRevision $revision,
        PagePersistenceService $persistenceService
    ): JsonResponse {
        // Prevent IDOR: Page must belong to this website, and revision must belong to this page
        if ($page->website_id !== $website->id || $revision->page_id !== $page->id) {
            abort(404);
        }

        $this->authorize('update', $page);

        $result = $persistenceService->rollbackToRevision(
            page: $page,
            website: $website,
            targetRevision: $revision,
            user: $request->user()
        );

        return response()->json($result, $result['status']);
    }
}
