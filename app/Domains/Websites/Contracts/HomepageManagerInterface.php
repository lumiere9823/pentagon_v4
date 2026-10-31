<?php

namespace App\Domains\Websites\Contracts;

use App\Models\Page;
use App\Models\User;
use App\Models\Website;

interface HomepageManagerInterface
{
    /**
     * Assign the specified page as the website's sole homepage.
     */
    public function assign(Website $website, Page $page, User $actor): void;
}
