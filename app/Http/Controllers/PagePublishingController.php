<?php

namespace App\Http\Controllers;

use App\Domains\Publishing\Exceptions\MediaUnavailableException;
use App\Domains\Publishing\Exceptions\PublishConcurrencyException;
use App\Domains\Publishing\Exceptions\PublishRenderingException;
use App\Domains\Publishing\Exceptions\PublishValidationException;
use App\Domains\Publishing\Exceptions\ReleaseStagingException;
use App\Domains\Publishing\Services\PagePublishingService;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class PagePublishingController extends Controller
{
    public function __construct(
        protected PagePublishingService $publishingService
    ) {}

    /**
     * Publish the specified page to a static release.
     */
    public function publish(Request $request, Website $website, Page $page): JsonResponse
    {
        // Strict IDOR protection & tenant scoping
        if ((int) $page->website_id !== (int) $website->id) {
            abort(404);
        }

        Gate::authorize('update', $page);

        try {
            $release = $this->publishingService->publish($page, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Page published successfully.',
                'release' => $release,
                'public_url' => route('public.sites.pages.show', [
                    'website' => $website->slug,
                    'page' => $page->slug,
                ]),
            ]);
        } catch (PublishConcurrencyException $e) {
            return response()->json([
                'success' => false,
                'error' => 'publish_in_progress',
                'message' => $e->getMessage(),
            ], 409);
        } catch (MediaUnavailableException $e) {
            return response()->json([
                'success' => false,
                'error' => 'media_asset_unavailable',
                'message' => $e->getMessage(),
                'unavailable_asset_ids' => $e->getUnavailableIds(),
            ], 422);
        } catch (PublishValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], 422);
        } catch (PublishRenderingException $e) {
            return response()->json([
                'success' => false,
                'error' => 'render_failed',
                'message' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], 422);
        } catch (ReleaseStagingException $e) {
            return response()->json([
                'success' => false,
                'error' => 'staging_failed',
                'message' => $e->getMessage(),
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'publish_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unpublish the specified page (transition status back to draft, taking public URL offline).
     */
    public function unpublish(Request $request, Website $website, Page $page): JsonResponse|RedirectResponse
    {
        // Strict IDOR protection & tenant scoping
        if ((int) $page->website_id !== (int) $website->id) {
            abort(404);
        }

        Gate::authorize('update', $page);

        $page->update([
            'status' => PageStatus::DRAFT,
            'updated_by' => $request->user()?->id,
        ]);

        if (! $request->wantsJson()) {
            return redirect()->back()->with('success', 'Page unpublished successfully. Public URL is now offline.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Page unpublished successfully. Public URL is now offline.',
            'status' => PageStatus::DRAFT->value,
        ]);
    }
}
