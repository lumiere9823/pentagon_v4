<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Deliver the dynamic robots.txt directives for a website.
     */
    public function show(string $websiteSlug): Response
    {
        /** @var Website|null $website */
        $website = Website::where('slug', $websiteSlug)->first();
        if (! $website) {
            abort(404);
        }

        $sitemapUrl = url("/sites/{$website->slug}/sitemap.xml");

        $content = <<<TEXT
User-agent: *
Allow: /sites/{$website->slug}/
Disallow: /preview/
Disallow: /websites/
Disallow: /dashboard

Sitemap: {$sitemapUrl}
TEXT;

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
