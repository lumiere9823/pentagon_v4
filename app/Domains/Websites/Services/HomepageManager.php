<?php

namespace App\Domains\Websites\Services;

use App\Domains\Websites\Contracts\HomepageManagerInterface;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HomepageManager implements HomepageManagerInterface
{
    /**
     * Assign the specified page as the website's sole homepage.
     */
    public function assign(Website $website, Page $page, User $actor): void
    {
        if ((int) $page->website_id !== (int) $website->id) {
            throw new InvalidArgumentException('Page does not belong to the specified website.');
        }

        DB::transaction(function () use ($website, $page) {
            // Lock the website record to serialize concurrent assignments for this website
            Website::where('id', $website->id)->lockForUpdate()->first();

            // Lock existing homepage rows for this website
            Page::where('website_id', $website->id)
                ->where('is_homepage', true)
                ->lockForUpdate()
                ->get();

            // Lock target page row
            $target = Page::where('id', $page->id)
                ->where('website_id', $website->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($target->is_homepage) {
                return;
            }

            // Clear old homepage flag
            Page::where('website_id', $website->id)
                ->where('is_homepage', true)
                ->update(['is_homepage' => false]);

            // Set new homepage flag
            $target->update(['is_homepage' => true]);
        });
    }
}
