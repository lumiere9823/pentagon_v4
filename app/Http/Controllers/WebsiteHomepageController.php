<?php

namespace App\Http\Controllers;

use App\Domains\Websites\Contracts\HomepageManagerInterface;
use App\Http\Requests\AssignHomepageRequest;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;

class WebsiteHomepageController extends Controller
{
    public function update(
        AssignHomepageRequest $request,
        Website $website,
        HomepageManagerInterface $homepageManager
    ): RedirectResponse {
        /** @var Page $page */
        $page = Page::where('website_id', $website->id)
            ->where('id', (int) $request->validated('page_id'))
            ->firstOrFail();

        $homepageManager->assign($website, $page, $request->user());

        return redirect()->route('websites.show', $website)
            ->with('success', "'{$page->title}' is now the homepage.");
    }
}
