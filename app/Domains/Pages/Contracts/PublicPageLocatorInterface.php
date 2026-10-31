<?php

namespace App\Domains\Pages\Contracts;

use App\Models\Page;
use App\Models\Website;

interface PublicPageLocatorInterface
{
    /**
     * Find the homepage for a given website.
     */
    public function findHomepage(Website $website): ?Page;

    /**
     * Determine whether the given page is designated as homepage.
     */
    public function isHomepage(Page $page): bool;

    /**
     * Get the canonical relative path for a page.
     * Homepage: /sites/{website-slug}
     * Ordinary page: /sites/{website-slug}/{page-slug}
     */
    public function getCanonicalPath(Page $page, ?Website $website = null): string;

    /**
     * Get the absolute canonical URL for a page.
     */
    public function getCanonicalUrl(Page $page, ?Website $website = null): string;
}
