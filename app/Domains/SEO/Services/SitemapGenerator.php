<?php

namespace App\Domains\SEO\Services;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Website;
use DOMDocument;

class SitemapGenerator
{
    /**
     * Generate W3C compliant XML sitemap for a website.
     * Only includes published pages with an active release and without `noindex`.
     */
    public function generateForWebsite(Website $website): string
    {
        $pages = Page::where('website_id', $website->id)
            ->where('status', PageStatus::PUBLISHED)
            ->with('activeRelease')
            ->orderBy('id')
            ->get()
            ->filter(function (Page $page) {
                if (! $page->activeRelease) {
                    return false;
                }

                $seo = $page->seo_metadata ?? [];

                return empty($seo['noindex']);
            });

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $urlset = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $dom->appendChild($urlset);

        foreach ($pages as $page) {
            $urlElement = $dom->createElement('url');

            // Location
            $canonicalOverride = $page->seo_metadata['canonical_url'] ?? null;
            $loc = ! empty($canonicalOverride)
                ? $canonicalOverride
                : url("/sites/{$website->slug}/{$page->slug}");

            $locElement = $dom->createElement('loc', htmlspecialchars($loc, ENT_QUOTES, 'UTF-8'));
            $urlElement->appendChild($locElement);

            // Lastmod
            $lastmodTimestamp = $page->activeRelease->activated_at ?? $page->updated_at;
            if ($lastmodTimestamp) {
                $lastmodElement = $dom->createElement('lastmod', $lastmodTimestamp->toIso8601String());
                $urlElement->appendChild($lastmodElement);
            }

            // Changefreq
            $changefreqElement = $dom->createElement('changefreq', 'weekly');
            $urlElement->appendChild($changefreqElement);

            // Priority
            $priority = ($page->slug === 'home' || $page->slug === 'index') ? '1.0' : '0.8';
            $priorityElement = $dom->createElement('priority', $priority);
            $urlElement->appendChild($priorityElement);

            $urlset->appendChild($urlElement);
        }

        $xml = $dom->saveXML();

        return $xml ?: "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"></urlset>";
    }
}
