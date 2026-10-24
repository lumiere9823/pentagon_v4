<?php

namespace App\Http\Controllers;

use App\Domains\Forms\Services\FormSubmissionService;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    public function __construct(
        private FormSubmissionService $formSubmissionService,
    ) {}

    /**
     * Handle public dynamic form submission.
     */
    public function submit(Request $request, string $websiteSlug, string $formName = 'contact'): JsonResponse
    {
        /** @var Website|null $website */
        $website = Website::where('slug', $websiteSlug)->first();
        if (! $website) {
            abort(404);
        }

        $page = null;
        $pageSlug = $request->input('_page_slug');
        if (! empty($pageSlug) && is_string($pageSlug)) {
            $page = Page::where('website_id', $website->id)
                ->where('slug', $pageSlug)
                ->first();
        }

        $result = $this->formSubmissionService->submit(
            website: $website,
            formName: $formName,
            rawPayload: $request->all(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            page: $page,
        );

        $headers = [
            'Cache-Control' => 'no-store, no-cache, private, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your submission.',
            'id' => $result['is_spam'] ? null : $result['submission']?->id,
        ], 201, $headers);
    }
}
