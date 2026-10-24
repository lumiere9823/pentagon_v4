<?php

namespace App\Console\Commands;

use App\Enums\PageReleaseStatus;
use App\Models\Page;
use App\Models\PageRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneSupersededReleasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:prune-superseded
                            {--disk=local : Filesystem disk storing releases}
                            {--dry-run : Simulate pruning without deleting files or updating database}
                            {--keep=5 : Number of superseded releases to retain per page}
                            {--hours=24 : Retention grace period in hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old superseded static releases while preserving active release, rollback buffer, and media integrity (D-035)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diskOption = $this->option('disk');
        $disk = is_string($diskOption) ? $diskOption : 'local';
        $isDryRun = (bool) $this->option('dry-run');
        $keepCount = max(0, (int) $this->option('keep'));
        $graceHours = max(0, (int) $this->option('hours'));

        $storage = Storage::disk($disk);
        $threshold = now()->subHours($graceHours);

        $this->info("Scanning superseded releases (Keep: {$keepCount}, Grace: {$graceHours}h, Disk: {$disk})...");

        // Fetch pages that have releases
        $pages = Page::whereHas('releases', function ($query) {
            $query->where('status', PageReleaseStatus::SUPERSEDED);
        })->get();

        $totalPruned = 0;
        $totalErrors = 0;

        foreach ($pages as $page) {
            // Strictly exclude:
            // 1. Currently active release
            // 2. Release corresponding to pages.published_revision_id
            // 3. Releases created within grace period
            // 4. Top $keepCount superseded releases per page
            $eligibleReleases = PageRelease::where('page_id', $page->id)
                ->where('status', PageReleaseStatus::SUPERSEDED)
                ->when($page->published_revision_id, function ($query, $publishedRevId) {
                    $query->where('page_revision_id', '!=', $publishedRevId);
                })
                ->where('created_at', '<', $threshold)
                ->orderBy('release_number', 'desc')
                ->skip($keepCount)
                ->take(100)
                ->get();

            foreach ($eligibleReleases as $release) {
                $dir = $release->directory_path;

                if ($isDryRun) {
                    $this->line(" [Dry-run] Would prune release #{$release->release_number} (UUID: {$release->id}, Dir: {$dir})");
                    $totalPruned++;

                    continue;
                }

                try {
                    $deleted = true;
                    if ($dir && $storage->exists($dir)) {
                        $deleted = $storage->deleteDirectory($dir);
                    }

                    if ($deleted) {
                        $release->update([
                            'status' => PageReleaseStatus::PRUNED,
                        ]);
                        $totalPruned++;
                        $this->line(" Pruned release #{$release->release_number} (UUID: {$release->id})");
                    } else {
                        Log::warning("Could not delete directory for release {$release->id}: {$dir}");
                        $totalErrors++;
                    }
                } catch (Throwable $e) {
                    Log::warning("Error pruning release {$release->id}: {$e->getMessage()}");
                    $totalErrors++;
                }
            }
        }

        $this->info("Pruning complete. Total releases pruned: {$totalPruned}, Errors encountered: {$totalErrors}");

        return 0;
    }
}
