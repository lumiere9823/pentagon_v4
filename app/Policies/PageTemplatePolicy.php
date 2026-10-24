<?php

namespace App\Policies;

use App\Enums\WebsiteRole;
use App\Models\PageTemplate;
use App\Models\User;
use App\Models\Website;
use Illuminate\Auth\Access\Response;

class PageTemplatePolicy
{
    /**
     * Determine whether the user can view any templates for the website.
     */
    public function viewAny(User $user, Website $website): bool
    {
        return $website->hasMember($user);
    }

    /**
     * Determine whether the user can view the specific template.
     */
    public function view(User $user, PageTemplate $template, ?Website $website = null): bool
    {
        if ($template->isGlobal()) {
            return true;
        }

        if ($website !== null && $template->website_id !== $website->id) {
            abort(404);
        }

        return $template->website?->hasMember($user) ?? false;
    }

    /**
     * Determine whether the user can create custom templates in the website.
     */
    public function create(User $user, Website $website): bool
    {
        return $website->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]);
    }

    /**
     * Determine whether the user can update the template.
     * Global starter templates are strictly immutable to tenant users.
     */
    public function update(User $user, PageTemplate $template, ?Website $website = null): Response
    {
        if ($template->isGlobal()) {
            return Response::deny('Global starter templates are immutable.', 403);
        }

        if ($website !== null && $template->website_id !== $website->id) {
            abort(404);
        }

        return ($template->website?->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]) ?? false)
            ? Response::allow()
            : Response::deny();
    }

    /**
     * Determine whether the user can delete the template.
     * Global starter templates are strictly immutable to tenant users.
     */
    public function delete(User $user, PageTemplate $template, ?Website $website = null): Response
    {
        if ($template->isGlobal()) {
            return Response::deny('Global starter templates are immutable.', 403);
        }

        if ($website !== null && $template->website_id !== $website->id) {
            abort(404);
        }

        return ($template->website?->hasMember($user, [WebsiteRole::OWNER, WebsiteRole::EDITOR]) ?? false)
            ? Response::allow()
            : Response::deny();
    }
}
