<?php

namespace App\Http\Controllers;

use App\Domains\Publishing\Exceptions\PublishConcurrencyException;
use App\Domains\Publishing\Services\PagePublishingService;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class PageReleaseController extends Controller
{
    public function __construct(
        protected PagePublishingService $publishingService
    ) {}

    /**
     * List immutable releases for the specified page.
     */
    public function index(Request $request, Website $website, Page $page): JsonResponse
    {
        if ((int) $page->website_id !== (int) $website->id) {
            abort(404);
        }

        Gate::authorize('view', $page);

        $query = $page->releases()
            ->with(['creator:id,name,email', 'revision:id,revision_number,change_summary'])
            ->orderBy('release_number', 'desc');

        if ($request->filled('limit')) {
            $limit = min(max((int) $request->query('limit'), 1), 100);
            $query->limit($limit);
        }

        $releases = $query->get();

        return response()->json([
            'releases' => $releases,
        ]);
    }

    /**
     * Rollback the public live release to a previous release (D-033).
     */
    public function rollback(
        Request $request,
        Website $website,
        Page $page,
        PageRelease $release
    ): JsonResponse {
        if ((int) $page->website_id !== (int) $website->id || (int) $release->page_id !== (int) $page->id) {
            abort(404);
        }

        Gate::authorize('update', $page);

        try {
            $result = $this->publishingService->rollbackToRelease($page, $release, $request->user());

            return response()->json([
                'success' => true,
                'message' => $result['instant']
                    ? "Successfully rolled back public release to Release #{$release->release_number}."
                    : "Successfully re-rendered and rolled back public release to Release #{$release->release_number}.",
                'instant' => $result['instant'],
                'recompiled' => $result['recompiled'],
                'release' => $result['release'],
            ]);
        } catch (PublishConcurrencyException $e) {
            return response()->json([
                'success' => false,
                'error' => 'publish_in_progress',
                'message' => $e->getMessage(),
            ], 409);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'rollback_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
