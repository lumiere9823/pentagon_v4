<?php

namespace App\Policies;

use App\Enums\WebsiteRole;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;

class PagePolicy
{
    /**
     * Determine whether the user can view any pages of the website.
     */
    public function viewAny(User $user, Website $website): bool
    {
        return $website->hasMember($user);
    }

    /**
     * Determine whether the user can view the page.
     */
    public function view(User $user, Page $page): bool
    {
        return $page->website->hasMember($user);
    }

    /**
     * Determine whether the user can create pages in the website.
     */
    public function create(User $user, Website $website): bool
    {
        return $website->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]);
    }

    /**
     * Determine whether the user can update the page.
     */
    public function update(User $user, Page $page): bool
    {
        return $page->website->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]);
    }

    /**
     * Determine whether the user can delete the page.
     */
    public function delete(User $user, Page $page): bool
    {
        return $page->website->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]);
    }
}
