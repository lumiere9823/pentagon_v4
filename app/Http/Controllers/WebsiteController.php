<?php

namespace App\Http\Controllers;

use App\Domains\Websites\Contracts\WebsiteProvisionerInterface;
use App\Enums\WebsiteRole;
use App\Http\Requests\StoreWebsiteRequest;
use App\Http\Requests\UpdateWebsiteRequest;
use App\Models\Page;
use App\Models\PageTemplate;
use App\Models\Website;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $websites = $user->websites()
            ->withCount('pages')
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->map(function (Website $website) use ($user) {
                $pivot = $website->getRelation('pivot');

                return [
                    'id' => $website->id,
                    'name' => $website->name,
                    'slug' => $website->slug,
                    'status' => $website->status,
                    'pages_count' => $website->pages_count,
                    'role' => $pivot instanceof Pivot ? $pivot->getAttribute('role') : null,
                    'is_owner' => $website->owner_id === $user->id,
                    'created_at' => $website->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Websites/Index', [
            'websites' => $websites,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Website::class);

        return Inertia::render('Websites/Create');
    }

    public function store(
        StoreWebsiteRequest $request,
        WebsiteProvisionerInterface $provisioner,
    ): RedirectResponse {
        $website = $provisioner->provision(
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('websites.show', $website)
            ->with('success', 'Website created successfully.');
    }

    public function show(Request $request, Website $website): Response
    {
        $this->authorize('view', $website);

        $user = $request->user();
        $userRole = $website->getMemberRole($user);

        $pages = $website->pages()
            ->with(['creator:id,name', 'updater:id,name'])
            ->orderByDesc('is_homepage')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($page) {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'is_homepage' => (bool) $page->is_homepage,
                    'status' => $page->status->value,
                    'creator' => $page->creator?->only(['id', 'name']),
                    'updater' => $page->updater?->only(['id', 'name']),
                    'created_at' => $page->created_at?->toIso8601String(),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                ];
            });

        $memberships = $website->memberships()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($membership) use ($website) {
                return [
                    'id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'role' => $membership->role->value,
                    'is_primary_owner' => $membership->user_id === $website->owner_id,
                    'user' => $membership->user?->only(['id', 'name', 'email']),
                    'created_at' => $membership->created_at?->toIso8601String(),
                ];
            });

        $templates = $website->templates()
            ->with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'category' => $template->category,
                    'description' => $template->description,
                    'is_active' => $template->is_active,
                    'created_at' => $template->created_at?->toIso8601String(),
                    'creator' => $template->creator?->only(['id', 'name']),
                ];
            });

        return Inertia::render('Websites/Show', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'slug' => $website->slug,
                'status' => $website->status,
                'created_at' => $website->created_at?->toIso8601String(),
            ],
            'pages' => $pages,
            'memberships' => $memberships,
            'templates' => $templates,
            'userRole' => $userRole?->value,
            'can' => [
                'update' => $user->can('update', $website),
                'delete' => $user->can('delete', $website),
                'create_page' => $user->can('create', [Page::class, $website]),
                'manage_members' => $userRole === WebsiteRole::OWNER,
                'manage_templates' => $user->can('create', [PageTemplate::class, $website]),
            ],
        ]);
    }

    public function update(UpdateWebsiteRequest $request, Website $website): RedirectResponse
    {
        $website->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
        ]);

        return redirect()->route('websites.show', $website)
            ->with('success', 'Website updated successfully.');
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('delete', $website);

        $website->delete();

        return redirect()->route('websites.index')
            ->with('success', 'Website deleted successfully.');
    }
}
