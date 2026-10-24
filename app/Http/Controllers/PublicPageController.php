<?php

namespace App\Http\Controllers;

use App\Domains\SEO\Services\RedirectManager;
use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PublicPageController extends Controller
{
    /**
     * Serve the active published static release for a website page with ETag caching,
     * or resolve 301/302 redirects if the page has been moved.
     */
    public function show(Request $request, string $websiteSlug, string $pageSlug): SymfonyResponse
    {
        /** @var Website|null $website */
        $website = Website::where('slug', $websiteSlug)->first();
        if (! $website) {
            abort(404);
        }

        /** @var Page|null $page */
        $page = Page::where('website_id', $website->id)
            ->where('slug', $pageSlug)
            ->first();

        if (! $page || $page->status !== PageStatus::PUBLISHED) {
            $redirect = app(RedirectManager::class)->resolveRedirect($website, $pageSlug);
            if ($redirect) {
                return redirect()->route('public.sites.pages.show', [
                    'website' => $website->slug,
                    'page' => $redirect['target_slug'],
                ], $redirect['status_code']);
            }

            abort(404);
        }

        /** @var PageRelease|null $activeRelease */
        $activeRelease = PageRelease::where('page_id', $page->id)
            ->where('status', PageReleaseStatus::ACTIVE)
            ->first();

        if (! $activeRelease || empty($activeRelease->directory_path)) {
            abort(404);
        }

        $storage = Storage::disk('local');
        $indexPath = $activeRelease->directory_path.'/index.html';

        if (! $storage->exists($indexPath)) {
            abort(404);
        }

        $etag = $activeRelease->manifest_json['html_hash'] ?? hash('sha256', $storage->get($indexPath));
        $clientEtag = trim((string) $request->header('If-None-Match'), '"');

        if (! empty($clientEtag) && $clientEtag === $etag) {
            return response('', 304, [
                'ETag' => '"'.$etag.'"',
                'Cache-Control' => 'public, max-age=300, must-revalidate',
            ]);
        }

        $htmlContent = $storage->get($indexPath);

        return response($htmlContent, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'ETag' => '"'.$etag.'"',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ]);
    }
}
