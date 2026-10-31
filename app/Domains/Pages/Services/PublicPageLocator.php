<?php

namespace App\Domains\Pages\Services;

use App\Domains\Pages\Contracts\PublicPageLocatorInterface;
use App\Models\Page;
use App\Models\Website;

class PublicPageLocator implements PublicPageLocatorInterface
{
    /**
     * Find the homepage for a given website.
     */
    public function findHomepage(Website $website): ?Page
    {
        return Page::where('website_id', $website->id)
            ->where('is_homepage', true)
            ->first();
    }

    /**
     * Determine whether the given page is designated as homepage.
     */
    public function isHomepage(Page $page): bool
    {
        return (bool) $page->is_homepage;
    }

    /**
     * Get the canonical relative path for a page.
     * Homepage: /sites/{website-slug}
     * Ordinary page: /sites/{website-slug}/{page-slug}
     */
    public function getCanonicalPath(Page $page, ?Website $website = null): string
    {
        $site = $website ?? $page->website;
        $websiteSlug = $site ? $site->slug : 'unknown';

        if ($this->isHomepage($page)) {
            return "/sites/{$websiteSlug}";
        }

        return "/sites/{$websiteSlug}/{$page->slug}";
    }

    /**
     * Get the absolute canonical URL for a page.
     */
    public function getCanonicalUrl(Page $page, ?Website $website = null): string
    {
        return url($this->getCanonicalPath($page, $website));
    }
}
