<?php

namespace App\Console\Commands;

use App\Domains\Publishing\Services\ReleaseManifestVerifier;
use App\Enums\PageReleaseStatus;
use App\Models\PageRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReconcileReleasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:reconcile
                            {--disk=local : Filesystem disk storing releases}
                            {--dry-run : Simulate reconciliation without deleting or updating}
                            {--staging-ttl=15 : Minutes after which uncommitted staging folders are deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile release state between disk and database, pruning stale staging folders (D-032)';

    /**
     * Execute the console command.
     */
    public function handle(ReleaseManifestVerifier $manifestVerifier): int
    {
        $diskOption = $this->option('disk');
        $disk = is_string($diskOption) ? $diskOption : 'local';
        $isDryRun = (bool) $this->option('dry-run');
        $stagingTtlMinutes = max(1, (int) $this->option('staging-ttl'));

        $storage = Storage::disk($disk);
        $this->info("Reconciling releases on disk '{$disk}' (Staging TTL: {$stagingTtlMinutes}m)...");

        $staleStagingCount = 0;
        $failedPendingCount = 0;
        $activeVerifiedCount = 0;
        $activeCorruptCount = 0;

        // 1. Scan and purge abandoned staging folders
        // Release structure: releases/{website_id}/{page_id}/staging_{release_uuid}
        $releaseRoot = 'releases';
        if ($storage->exists($releaseRoot)) {
            $websiteDirs = $storage->directories($releaseRoot);
            foreach ($websiteDirs as $websiteDir) {
                $pageDirs = $storage->directories($websiteDir);
                foreach ($pageDirs as $pageDir) {
                    $subDirs = $storage->directories($pageDir);
                    foreach ($subDirs as $subDir) {
                        $baseName = basename($subDir);
                        if (str_starts_with($baseName, 'staging_')) {
                            $lastModified = $storage->lastModified($subDir);
                            $ageMinutes = (time() - $lastModified) / 60;

                            if ($ageMinutes >= $stagingTtlMinutes) {
                                if ($isDryRun) {
                                    $this->line(" [Dry-run] Would delete stale staging folder: {$subDir} (Age: ".round($ageMinutes).'m)');
                                    $staleStagingCount++;
                                } else {
                                    $deleted = $storage->deleteDirectory($subDir);
                                    if ($deleted) {
                                        $this->line(" Deleted stale staging folder: {$subDir}");
                                        $staleStagingCount++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. Mark abandoned pending candidate DB records as failed
        $abandonedThreshold = now()->subMinutes($stagingTtlMinutes);
        $abandonedReleases = PageRelease::where('status', PageReleaseStatus::PENDING)
            ->where('created_at', '<', $abandonedThreshold)
            ->get();

        foreach ($abandonedReleases as $abandoned) {
            if ($isDryRun) {
                $this->line(" [Dry-run] Would mark pending release {$abandoned->id} as failed");
                $failedPendingCount++;
            } else {
                $abandoned->update([
                    'status' => PageReleaseStatus::FAILED,
                    'error_message' => 'Abandoned uncommitted candidate release (reconciliation timeout).',
                ]);
                $failedPendingCount++;
            }
        }

        // 3. Verify integrity of all active releases
        $activeReleases = PageRelease::where('status', PageReleaseStatus::ACTIVE)->get();
        foreach ($activeReleases as $active) {
            $dir = $active->directory_path;
            if (! $dir || ! $storage->exists($dir)) {
                $activeCorruptCount++;
                Log::critical("Active release disk directory missing: Page {$active->page_id}, Release {$active->id}, Dir {$dir}");
                $this->error(" CRITICAL: Active release directory missing: {$dir} for page {$active->page_id}");

                continue;
            }

            $report = $manifestVerifier->verifyDirectoryWithReport($dir, $disk);
            if (! $report['valid']) {
                $activeCorruptCount++;
                Log::critical("Active release disk integrity violation: Page {$active->page_id}, Release {$active->id}, Errors: ".implode('; ', $report['errors']));
                $this->error(" CRITICAL: Integrity violation in active release {$dir}: ".implode('; ', $report['errors']));
            } else {
                $activeVerifiedCount++;
            }
        }

        $this->info('Reconciliation complete.');
        $this->line(" - Stale staging folders purged: {$staleStagingCount}");
        $this->line(" - Abandoned pending releases marked failed: {$failedPendingCount}");
        $this->line(" - Active releases verified valid: {$activeVerifiedCount}");
        if ($activeCorruptCount > 0) {
            $this->warn(" - Active releases with missing/corrupt files: {$activeCorruptCount}");
        }

        return 0;
    }
}
