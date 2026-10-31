<?php

namespace App\Console\Commands;

use App\Domains\Websites\Services\StarterPageCreator;
use App\Domains\Websites\Services\StarterSetCatalog;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillWebsitesHomepagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websites:backfill-homepages {--commit : Commit changes to the database (default is dry-run)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Idempotently backfill missing homepages for existing websites.';

    public function handle(
        StarterSetCatalog $starterCatalog,
        StarterPageCreator $starterPageCreator,
    ): int {
        $isCommit = (bool) $this->option('commit');

        $websites = Website::whereDoesntHave('pages', function ($q) {
            $q->where('is_homepage', true);
        })->with('owner')->orderBy('id')->get();

        if ($websites->isEmpty()) {
            $this->info('All websites already have a homepage configured. Zero changes required.');

            return self::SUCCESS;
        }

        if (! $isCommit) {
            $this->warn('DRY-RUN MODE: No changes will be persisted. Run with --commit to apply mutations.');
        }

        $homeSelected = 0;
        $oldestSelected = 0;
        $homeCreated = 0;

        foreach ($websites as $website) {
            // 1. Prefer existing page with slug 'home'
            /** @var Page|null $homePage */
            $homePage = Page::where('website_id', $website->id)
                ->where('slug', 'home')
                ->first();

            if ($homePage) {
                $this->line("Website {$website->id} ('{$website->slug}'): Assigned existing home page: {$homePage->slug} (ID: {$homePage->id})");
                if ($isCommit) {
                    DB::transaction(function () use ($homePage) {
                        $homePage->update(['is_homepage' => true]);
                    });
                }
                $homeSelected++;

                continue;
            }

            // 2. Fallback to oldest existing page
            /** @var Page|null $oldestPage */
            $oldestPage = Page::where('website_id', $website->id)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($oldestPage) {
                $this->line("Website {$website->id} ('{$website->slug}'): Assigned oldest page: {$oldestPage->slug} (ID: {$oldestPage->id})");
                if ($isCommit) {
                    DB::transaction(function () use ($oldestPage) {
                        $oldestPage->update(['is_homepage' => true]);
                    });
                }
                $oldestSelected++;

                continue;
            }

            // 3. Otherwise create canonical Home page + revision 1
            $this->line("Website {$website->id} ('{$website->slug}'): Created canonical Home page and revision 1");
            if ($isCommit) {
                DB::transaction(function () use ($website, $starterCatalog, $starterPageCreator) {
                    $homeDef = $starterCatalog->getCanonicalHomeDefinition();
                    $starterPageCreator->create($website, $homeDef, $website->owner);
                });
            }
            $homeCreated++;
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Mode', $isCommit ? 'COMMITTED' : 'DRY-RUN'],
            ['Total websites processed', $websites->count()],
            ["Existing 'home' pages assigned", $homeSelected],
            ['Oldest existing pages assigned', $oldestSelected],
            ['New canonical Home pages created', $homeCreated],
        ]);

        $this->info("Total websites processed: {$websites->count()}");

        return self::SUCCESS;
    }
}
