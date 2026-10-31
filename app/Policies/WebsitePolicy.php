<?php

namespace App\Policies;

use App\Enums\WebsiteRole;
use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Website $website): bool
    {
        return $website->hasMember($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Website $website): bool
    {
        return $website->getMemberRole($user) === WebsiteRole::OWNER;
    }

    /**
     * Determine whether the user can update or delete form submissions.
     */
    public function manageFormSubmissions(User $user, Website $website): bool
    {
        return $website->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Website $website): bool
    {
        return $website->getMemberRole($user) === WebsiteRole::OWNER;
    }
}
