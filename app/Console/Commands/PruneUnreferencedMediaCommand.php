<?php

namespace App\Console\Commands;

use App\Domains\Media\Services\MediaConcurrencyLock;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Enums\MediaAssetStatus;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
                            {--website= : Restrict cleanup to a specific website ID}
                            {--watchdog-only : Only run the orphan purging watchdog sweep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune soft-deleted media assets that have passed the grace period and have no active page references.';

    /**
     * Execute the console command.
     */
    public function handle(MediaUsageScanner $scanner, MediaConcurrencyLock $lockService): int
    {
        // 1. Run watchdog to safely recover or finalize abandoned 'purging' records
        $this->recoverOrphanedPurgingAssets($lockService);

        if ($this->option('watchdog-only')) {
            $this->info('Watchdog sweep completed.');

            return self::SUCCESS;
        }

        $graceHours = max((int) $this->option('hours'), 0);
        $isForced = (bool) $this->option('force');
        $cutoff = $isForced ? now() : now()->subHours($graceHours);

        $query = MediaAsset::onlyTrashed()
            ->with('website')
            ->where('deleted_at', '<=', $cutoff)
            ->where(function ($q) {
                $q->whereIn('status', [MediaAssetStatus::PENDING_DELETION, MediaAssetStatus::PURGING])
                    ->orWhere('status', MediaAssetStatus::ACTIVE);
            });

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
            DB::transaction(function () use (
                $asset,
                $scanner,
                $lockService,
                $cutoff,
                &$prunedCount,
                &$skippedCount,
                &$errorCount
            ) {
                // 1. Acquire transaction-scoped advisory lock for the asset
                $lockService->lockAsset($asset->id);

                // 2. Re-read fresh record with row locking under transaction
                $lockedAsset = MediaAsset::onlyTrashed()
                    ->with('website')
                    ->where('id', $asset->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedAsset) {
                    // Already force-deleted by another worker
                    return;
                }

                if (! $lockedAsset->trashed() || ! in_array($lockedAsset->status, [MediaAssetStatus::PENDING_DELETION, MediaAssetStatus::PURGING, MediaAssetStatus::ACTIVE], true)) {
                    // Asset status was modified or revived
                    return;
                }

                if ($lockedAsset->deleted_at > $cutoff) {
                    // Grace period cutoff condition changed
                    return;
                }

                $website = $lockedAsset->website;

                // 3. Storage path containment verification
                if ($website) {
                    $normalizedPath = ltrim(str_replace('\\', '/', $lockedAsset->storage_path), '/');
                    $expectedPrefix = "media/{$website->id}/";

                    if (! str_starts_with($normalizedPath, $expectedPrefix) || str_contains($normalizedPath, '..')) {
                        Log::error("Security violation: media asset {$lockedAsset->id} storage path '{$lockedAsset->storage_path}' is outside website directory '{$expectedPrefix}'. Pruning aborted for this asset.");
                        $this->error("Security violation: asset {$lockedAsset->id} path outside managed directory.");
                        $errorCount++;

                        return;
                    }

                    // 4. Transition status to purging under lock
                    $lockedAsset->update(['status' => MediaAssetStatus::PURGING]);

                    // 5. Authoritative usage scan under lock
                    try {
                        $referencingPages = $scanner->findUsagesInWebsite($website, $lockedAsset);
                    } catch (Throwable $e) {
                        Log::warning("Skipping cleanup of media asset {$lockedAsset->id}: scan failed with {$e->getMessage()}");
                        $this->warn("Skipping asset {$lockedAsset->id}: scan error ({$e->getMessage()})");
                        $lockedAsset->update(['status' => MediaAssetStatus::PENDING_DELETION]);
                        $errorCount++;

                        return;
                    }

                    if (! empty($referencingPages)) {
                        Log::info("Skipping cleanup of media asset {$lockedAsset->id}: still referenced in website {$website->id}.");
                        $this->line("Retaining asset {$lockedAsset->id}: referenced on ".count($referencingPages).' page(s).');
                        $lockedAsset->update(['status' => MediaAssetStatus::PENDING_DELETION]);
                        $skippedCount++;

                        return;
                    }
                }

                // 6. Confirmed unreferenced. Perform physical disk unlink
                $disk = $lockedAsset->storage_disk;
                $path = $lockedAsset->storage_path;

                try {
                    if (Storage::disk($disk)->exists($path)) {
                        $deleted = Storage::disk($disk)->delete($path);
                        if (! $deleted) {
                            Log::error("Failed to delete file from disk for asset {$lockedAsset->id} at path {$path}.");
                            $this->error("Failed to delete file from disk for asset {$lockedAsset->id}.");
                            $lockedAsset->update(['status' => MediaAssetStatus::PENDING_DELETION]);
                            $errorCount++;

                            return;
                        }
                    } else {
                        // Recoverable idempotency: file was already absent from disk (e.g. earlier crash after unlink before commit)
                        Log::info("Storage file already absent from disk for asset {$lockedAsset->id}. Finalizing metadata cleanup.");
                    }
                } catch (Throwable $e) {
                    Log::error("Exception deleting file for asset {$lockedAsset->id}: {$e->getMessage()}");
                    $this->error("Exception deleting file for asset {$lockedAsset->id}: {$e->getMessage()}");
                    $lockedAsset->update(['status' => MediaAssetStatus::PENDING_DELETION]);
                    $errorCount++;

                    return;
                }

                // 7. Once physical file is confirmed absent or unlinked, permanently remove DB record
                $lockedAsset->forceDelete();
                $prunedCount++;
                $this->info("Pruned asset {$lockedAsset->id} ({$lockedAsset->original_filename}).");
            });
        }

        $this->info("Pruning complete. Pruned: {$prunedCount}, Skipped (in use): {$skippedCount}, Errors: {$errorCount}.");

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Recover abandoned or interrupted 'purging' records using non-blocking advisory lock probes.
     * Guarantees zero interference with live running prune operations.
     */
    private function recoverOrphanedPurgingAssets(MediaConcurrencyLock $lockService): void
    {
        $query = MediaAsset::onlyTrashed()
            ->where('status', MediaAssetStatus::PURGING)
            ->where('updated_at', '<=', now()->subHour());

        if ($websiteId = $this->option('website')) {
            $query->where('website_id', (int) $websiteId);
        }

        $stuckAssets = $query->get();

        foreach ($stuckAssets as $asset) {
            DB::transaction(function () use ($asset, $lockService) {
                // Non-blocking try-lock: if another worker holds the lock, do NOT touch it!
                if (! $lockService->tryLockAsset($asset->id)) {
                    return;
                }

                $locked = MediaAsset::onlyTrashed()
                    ->where('id', $asset->id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked || $locked->status !== MediaAssetStatus::PURGING) {
                    return;
                }

                $disk = $locked->storage_disk;
                $path = $locked->storage_path;

                if (Storage::disk($disk)->exists($path)) {
                    // Physical file still exists; revert to pending_deletion for normal re-evaluation
                    $locked->update(['status' => MediaAssetStatus::PENDING_DELETION]);
                    Log::info("Watchdog reset abandoned purging asset {$locked->id} back to pending_deletion.");
                } else {
                    // Physical file was already unlinked before worker death; finalize database purge
                    $locked->forceDelete();
                    Log::info("Watchdog finalized forceDelete for already-unlinked asset {$locked->id}.");
                }
            });
        }
    }
}
