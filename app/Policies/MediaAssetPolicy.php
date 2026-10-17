<?php

namespace App\Policies;

use App\Enums\WebsiteRole;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Website;

class MediaAssetPolicy
{
    /**
     * Determine whether the user can view any media assets of the website.
     */
    public function viewAny(User $user, Website $website): bool
    {
        return $website->hasMember($user);
    }

    /**
     * Determine whether the user can view the specific media asset.
     */
    public function view(User $user, MediaAsset $mediaAsset): bool
    {
        return $mediaAsset->website->hasMember($user);
    }

    /**
     * Determine whether the user can upload/create media assets in the website.
     */
    public function create(User $user, Website $website): bool
    {
        return $website->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]);
    }

    /**
     * Determine whether the user can update the media asset metadata.
     */
    public function update(User $user, MediaAsset $mediaAsset): bool
    {
        return $mediaAsset->website->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]);
    }

    /**
     * Determine whether the user can delete the media asset.
     */
    public function delete(User $user, MediaAsset $mediaAsset): bool
    {
        return $mediaAsset->website->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]);
    }
}
