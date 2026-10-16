<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Http\Requests\StorePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function create(Request $request, Website $website): Response
    {
        $this->authorize('create', [Page::class, $website]);

        return Inertia::render('Pages/Create', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'slug' => $website->slug,
            ],
        ]);
    }

    public function store(StorePageRequest $request, Website $website): RedirectResponse
    {
        $user = $request->user();

        $page = $website->pages()->create([
            'title' => $request->validated('title'),
            'slug' => $request->validated('slug'),
            'status' => PageStatus::DRAFT,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('websites.show', $website)
            ->with('success', 'Page created successfully.');
    }

    public function edit(Request $request, Website $website, Page $page): Response
    {
        // Enforce IDOR protection: Page must belong to this website
        if ($page->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('update', $page);

        return Inertia::render('Pages/Edit', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'slug' => $website->slug,
            ],
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'created_at' => $page->created_at?->toIso8601String(),
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
            'statuses' => PageStatus::values(),
        ]);
    }

    public function update(UpdatePageRequest $request, Website $website, Page $page): RedirectResponse
    {
        // Enforce IDOR protection: Page must belong to this website
        if ($page->website_id !== $website->id) {
            abort(404);
        }

        $user = $request->user();

        $page->update([
            'title' => $request->validated('title'),
            'slug' => $request->validated('slug'),
            'status' => PageStatus::from($request->validated('status')),
            'updated_by' => $user->id,
        ]);

        return redirect()->route('websites.show', $website)
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(Request $request, Website $website, Page $page): RedirectResponse
    {
        // Enforce IDOR protection: Page must belong to this website
        if ($page->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('delete', $page);

        $page->delete();

        return redirect()->route('websites.show', $website)
            ->with('success', 'Page deleted successfully.');
    }
}
