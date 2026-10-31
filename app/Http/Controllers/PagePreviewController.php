<?php

namespace App\Http\Controllers;

use App\Domains\Publishing\Services\DocumentHtmlRenderer;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class PagePreviewController extends Controller
{
    public function __construct(
        protected DocumentHtmlRenderer $renderer
    ) {}

    /**
     * Authenticated private preview within the CMS editor.
     */
    public function show(Request $request, Website $website, Page $page): Response
    {
        if ((int) $page->website_id !== (int) $website->id) {
            abort(404);
        }

        Gate::authorize('view', $page);

        $document = $this->resolvePreviewDocument($request, $page);

        return $this->renderPreviewResponse($document, $page->title ?? 'Preview');
    }

    /**
     * Expiring cryptographically signed preview for external stakeholders (60m TTL).
     */
    public function showSigned(Request $request, Page $page): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'The preview link has expired or is invalid. Please generate a new preview link from the editor.');
        }

        $document = $this->resolvePreviewDocument($request, $page);

        return $this->renderPreviewResponse($document, $page->title ?? 'Preview');
    }

    /**
     * Generate an expiring signed preview URL for sharing.
     */
    public function shareLink(Request $request, Website $website, Page $page): JsonResponse
    {
        if ((int) $page->website_id !== (int) $website->id) {
            abort(404);
        }

        Gate::authorize('view', $page);

        $expiresAt = now()->addMinutes(60);
        $signedUrl = URL::temporarySignedRoute('preview.signed', $expiresAt, ['page' => $page->id]);

        return response()->json([
            'signed_url' => $signedUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * Resolve document AST from draft or historical revision.
     *
     * @return array<string, mixed>
     */
    protected function resolvePreviewDocument(Request $request, Page $page): array
    {
        if ($request->has('revision')) {
            $revisionId = (int) $request->query('revision');
            /** @var PageRevision|null $revision */
            $revision = PageRevision::where('page_id', $page->id)->where('id', $revisionId)->first();
            if ($revision && ! empty($revision->document_json)) {
                return $revision->document_json;
            }
        }

        $doc = $page->document_json;
        if (empty($doc)) {
            // Fallback minimal valid document if empty
            return [
                'schemaVersion' => 1,
                'root' => [
                    'id' => 'root',
                    'type' => 'Container',
                    'props' => ['padding' => 'medium'],
                    'children' => [
                        [
                            'id' => 'preview-empty-heading',
                            'type' => 'Heading',
                            'props' => ['text' => 'Draft is empty', 'level' => 2],
                            'children' => [],
                        ],
                    ],
                ],
            ];
        }

        return $doc;
    }

    /**
     * Render the preview document with watermark banner and anti-indexing headers.
     *
     * @param  array<string, mixed>  $document
     */
    protected function renderPreviewResponse(array $document, string $pageTitle): Response
    {
        $banner = '<div class="bg-amber-500 text-black px-4 py-2 text-center text-sm font-semibold sticky top-0 z-50 shadow-md">PREVIEW MODE &mdash; Draft snapshot, not for public distribution</div>';
        $renderedHtml = $this->renderer->renderDocument($document, [
            'title' => $pageTitle.' (Preview)',
            'description' => 'Preview mode draft snapshot',
        ], true, $banner);

        return response($renderedHtml, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet',
        ]);
    }
}
