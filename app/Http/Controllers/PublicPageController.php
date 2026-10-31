<?php

namespace App\Http\Controllers;

use App\Domains\Pages\Contracts\PublicPageLocatorInterface;
use App\Domains\SEO\Services\RedirectManager;
use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PublicPageController extends Controller
{
    public function __construct(
        protected PublicPageLocatorInterface $pageLocator,
        protected RedirectManager $redirectManager,
    ) {}

    /**
     * Serve the active published static release for the website homepage.
     */
    public function showRoot(Request $request, string $websiteSlug): SymfonyResponse
    {
        /** @var Website|null $website */
        $website = Website::where('slug', $websiteSlug)->first();
        if (! $website) {
            abort(404);
        }

        $homepage = $this->pageLocator->findHomepage($website);
        if (! $homepage) {
            Log::warning("Website {$website->id} ('{$website->slug}') does not have a homepage configured.");
            abort(404);
        }

        return $this->servePageRelease($request, $homepage);
    }

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

        // Technical slug of current homepage permanently redirects (301) to canonical website root
        if ($page && $this->pageLocator->isHomepage($page)) {
            return redirect()->route('public.sites.root', [
                'website' => $website->slug,
            ], 301);
        }

        if (! $page || $page->status !== PageStatus::PUBLISHED) {
            $redirect = $this->redirectManager->resolveRedirect($website, $pageSlug);
            if ($redirect) {
                // If redirect points to the current homepage's technical slug, redirect to root
                $targetPage = Page::where('website_id', $website->id)
                    ->where('slug', $redirect['target_slug'])
                    ->first();

                if ($targetPage && $this->pageLocator->isHomepage($targetPage)) {
                    return redirect()->route('public.sites.root', [
                        'website' => $website->slug,
                    ], $redirect['status_code']);
                }

                return redirect()->route('public.sites.pages.show', [
                    'website' => $website->slug,
                    'page' => $redirect['target_slug'],
                ], $redirect['status_code']);
            }

            abort(404);
        }

        return $this->servePageRelease($request, $page);
    }

    /**
     * Shared static release delivery with ETag caching and headers.
     */
    protected function servePageRelease(Request $request, Page $page): SymfonyResponse
    {
        if ($page->status !== PageStatus::PUBLISHED) {
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

        $etag = $activeRelease->manifest_json['html_hash'] ?? hash('sha256', (string) $storage->get($indexPath));
        $clientEtag = trim((string) $request->header('If-None-Match'), '"');

        if (! empty($clientEtag) && $clientEtag === $etag) {
            return response('', 304, [
                'ETag' => '"'.$etag.'"',
                'Cache-Control' => 'public, max-age=300, must-revalidate',
            ]);
        }

        $htmlContent = (string) $storage->get($indexPath);

        return response($htmlContent, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'ETag' => '"'.$etag.'"',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ]);
    }
}
