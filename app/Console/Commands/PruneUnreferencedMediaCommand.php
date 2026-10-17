<?php

namespace App\Console\Commands;

use App\Domains\Media\Services\MediaUsageScanner;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneUnreferencedMediaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:prune-unreferenced
                            {--hours=24 : Minimum grace period in hours since soft deletion}
                            {--force : Bypass grace period and check immediately}
                            {--website= : Restrict cleanup to a specific website ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune soft-deleted media assets that have passed the grace period and have no active page references.';

    /**
     * Execute the console command.
     */
    public function handle(MediaUsageScanner $scanner): int
    {
        $graceHours = max((int) $this->option('hours'), 0);
        $isForced = (bool) $this->option('force');
        $cutoff = $isForced ? now() : now()->subHours($graceHours);

        $query = MediaAsset::onlyTrashed()
            ->with('website')
            ->where('deleted_at', '<=', $cutoff);

        if ($websiteId = $this->option('website')) {
            $query->where('website_id', (int) $websiteId);
        }

        $assets = $query->get();

        if ($assets->isEmpty()) {
            $this->info('No soft-deleted media assets qualify for pruning.');

            return self::SUCCESS;
        }

        $prunedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($assets as $asset) {
            $website = $asset->website;

            // If website still exists, verify that storage path is safely scoped to it
            if ($website) {
                $normalizedPath = ltrim(str_replace('\\', '/', $asset->storage_path), '/');
                $expectedPrefix = "media/{$website->id}/";

                if (! str_starts_with($normalizedPath, $expectedPrefix) || str_contains($normalizedPath, '..')) {
                    Log::error("Security violation: media asset {$asset->id} storage path '{$asset->storage_path}' is outside website directory '{$expectedPrefix}'. Pruning aborted for this asset.");
                    $this->error("Security violation: asset {$asset->id} path outside managed directory.");
                    $errorCount++;

                    continue;
                }

                // Authoritative usage scan immediately before physical unlink
                try {
                    $referencingPages = $scanner->findUsagesInWebsite($website, $asset);
                } catch (Throwable $e) {
                    Log::warning("Skipping cleanup of media asset {$asset->id}: scan failed with {$e->getMessage()}");
                    $this->warn("Skipping asset {$asset->id}: scan error ({$e->getMessage()})");
                    $errorCount++;

                    continue;
                }

                if (! empty($referencingPages)) {
                    Log::info("Skipping cleanup of media asset {$asset->id}: still referenced in website {$website->id}.");
                    $this->line("Retaining asset {$asset->id}: referenced on ".count($referencingPages).' page(s).');
                    $skippedCount++;

                    continue;
                }
            }

            // Asset is confirmed unreferenced. Proceed with physical unlink.
            $disk = $asset->storage_disk;
            $path = $asset->storage_path;

            try {
                if (Storage::disk($disk)->exists($path)) {
                    $deleted = Storage::disk($disk)->delete($path);
                    if (! $deleted) {
                        Log::error("Failed to delete file from disk for asset {$asset->id} at path {$path}.");
                        $this->error("Failed to delete file from disk for asset {$asset->id}.");
                        $errorCount++;

                        continue;
                    }
                }
            } catch (Throwable $e) {
                Log::error("Exception deleting file for asset {$asset->id}: {$e->getMessage()}");
                $this->error("Exception deleting file for asset {$asset->id}: {$e->getMessage()}");
                $errorCount++;

                continue;
            }

            // Once physical file is confirmed unlinked, permanently remove the database record
            $asset->forceDelete();
            $prunedCount++;
            $this->info("Pruned asset {$asset->id} ({$asset->original_filename}).");
        }

        $this->info("Pruning complete. Pruned: {$prunedCount}, Skipped (in use): {$skippedCount}, Errors: {$errorCount}.");

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
