<?php

namespace App\Http\Controllers;

use App\Domains\SEO\Services\SitemapGenerator;
use App\Models\Website;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(
        private SitemapGenerator $sitemapGenerator,
    ) {}

    /**
     * Deliver the dynamic XML sitemap for a website.
     */
    public function show(string $websiteSlug): Response
    {
        /** @var Website|null $website */
        $website = Website::where('slug', $websiteSlug)->first();
        if (! $website) {
            abort(404);
        }

        $xml = $this->sitemapGenerator->generateForWebsite($website);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
