<?php

namespace App\Policies;

use App\Enums\WebsiteRole;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;

class WebsiteMembershipPolicy
{
    /**
     * Determine whether the user can view any memberships of the website.
     */
    public function viewAny(User $user, Website $website): bool
    {
        return $website->hasMember($user);
    }

    /**
     * Determine whether the user can create memberships.
     */
    public function create(User $user, Website $website): bool
    {
        return $website->getMemberRole($user) === WebsiteRole::OWNER;
    }

    /**
     * Determine whether the user can update the membership.
     */
    public function update(User $user, WebsiteMembership $membership, ?Website $website = null): bool
    {
        $targetWebsite = $website ?? $membership->website;

        if (! $targetWebsite || $targetWebsite->getMemberRole($user) !== WebsiteRole::OWNER) {
            return false;
        }

        // Prevent IDOR: Membership must belong to the specified website
        if ($membership->website_id !== $targetWebsite->id) {
            return false;
        }

        // Invariant: Cannot alter the website primary owner's membership
        if ($membership->user_id === $targetWebsite->owner_id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the membership.
     */
    public function delete(User $user, WebsiteMembership $membership, ?Website $website = null): bool
    {
        $targetWebsite = $website ?? $membership->website;

        if (! $targetWebsite || $targetWebsite->getMemberRole($user) !== WebsiteRole::OWNER) {
            return false;
        }

        // Prevent IDOR: Membership must belong to the specified website
        if ($membership->website_id !== $targetWebsite->id) {
            return false;
        }

        // Invariant: Cannot remove the website primary owner
        if ($membership->user_id === $targetWebsite->owner_id) {
            return false;
        }

        return true;
    }
}
