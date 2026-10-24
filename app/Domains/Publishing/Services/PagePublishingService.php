<?php

namespace App\Domains\Publishing\Services;

use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Domains\Media\Services\MediaConcurrencyLock;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Domains\Publishing\Exceptions\MediaUnavailableException;
use App\Domains\Publishing\Exceptions\PublishConcurrencyException;
use App\Domains\Publishing\Exceptions\PublishFailedException;
use App\Enums\MediaAssetStatus;
use App\Enums\PageReleaseStatus;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PagePublishingService
{
    public function __construct(
        protected PagePublishingLock $publishingLock,
        protected ReleaseStagingService $stagingService,
        protected ReleaseManifestVerifier $manifestVerifier,
        protected MediaUsageScanner $mediaScanner,
        protected MediaConcurrencyLock $mediaLock,
        protected DocumentValidator $validator,
    ) {}

    /**
     * Publish the given page to an immutable static release (Two-Phase Activation).
     *
     * Invariants:
     * 1. Per-page serialization via PagePublishingLock (pg_try_advisory_xact_lock).
     * 2. Short-lived advisory locks (<10ms) during pre-flight, never held during HTML render/disk I/O.
     * 3. Current active release is never mutated or lost if compile/activation fails.
     * 4. Single active release enforced by DB partial unique index.
     *
     * @throws PublishConcurrencyException|MediaUnavailableException|PublishFailedException|Throwable
     */
    public function publish(Page $page, ?User $user = null, ?string $disk = 'local'): PageRelease
    {
        // Phase 1: Pre-flight snapshot & media verification (Short DB Transaction <10ms)
        /** @var array{release: PageRelease, target_revision: PageRevision} $preflight */
        $preflight = DB::transaction(function () use ($page, $user) {
            $locked = $this->publishingLock->tryLockPage($page->id);
            if (! $locked) {
                throw new PublishConcurrencyException('Publishing is already in progress for this page.');
            }

            /** @var Page $lockedPage */
            $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();

            if ($lockedPage->document_json === null) {
                throw new PublishFailedException('Cannot publish a page without document content.');
            }

            $validation = $this->validator->validate($lockedPage->document_json);
            if (! $validation->isValid()) {
                throw new PublishFailedException('Document failed Schema v1 validation: '.implode('; ', array_map(fn ($e) => $e->getMessage(), $validation->getErrors())));
            }

            $docHash = PageRevision::canonicalizeDocument($lockedPage->document_json);

            /** @var PageRevision|null $latestRev */
            $latestRev = PageRevision::where('page_id', $lockedPage->id)
                ->orderBy('revision_number', 'desc')
                ->lockForUpdate()
                ->first();

            if ($latestRev !== null && $latestRev->document_hash === $docHash) {
                $targetRev = $latestRev;
            } else {
                $nextRevNum = ($latestRev !== null ? $latestRev->revision_number : 0) + 1;
                $targetRev = PageRevision::create([
                    'page_id' => $lockedPage->id,
                    'revision_number' => $nextRevNum,
                    'schema_version' => 1,
                    'document_json' => $lockedPage->document_json,
                    'document_hash' => $docHash,
                    'change_summary' => 'Published release',
                    'is_autosave' => false,
                    'created_by' => $user?->id,
                ]);
            }

            // D-023 Advisory Lock scoping for referenced media
            $website = $lockedPage->website ?? Website::findOrFail($lockedPage->website_id);
            $inspection = $this->mediaScanner->inspectAssetReferences($targetRev->document_json, $website);

            if ($inspection['indeterminate'] || $inspection['has_foreign_media'] || ! empty($inspection['unresolved_media_paths'])) {
                throw new MediaUnavailableException('One or more media assets referenced in this document are unavailable or foreign.');
            }

            $assetIds = array_values(array_unique(array_map('intval', $inspection['asset_ids'])));
            if (! empty($assetIds)) {
                sort($assetIds, SORT_NUMERIC);
                $this->mediaLock->lockAssets($assetIds);

                $activeAssets = MediaAsset::where('website_id', $website->id)
                    ->whereIn('id', $assetIds)
                    ->where('status', MediaAssetStatus::ACTIVE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                if (count($activeAssets) !== count($assetIds)) {
                    throw new MediaUnavailableException('One or more referenced media assets are unavailable or deleted.');
                }
            }

            // Create unsealed candidate release in PENDING state
            $candidate = $this->stagingService->createCandidate($targetRev, $user);

            return [
                'release' => $candidate,
                'target_revision' => $targetRev,
            ];
        });

        $candidateRelease = $preflight['release'];
        $targetRevision = $preflight['target_revision'];

        // Phase 2: In-Memory Render & Disk Sealing (Zero DB Transactions held)
        try {
            $sealedRelease = $this->stagingService->stageAndSeal($candidateRelease, $disk);
        } catch (Throwable $e) {
            // Staging service already unlinks staging and marks candidate as FAILED
            throw $e;
        }

        // Phase 3: Database Atomic Activation (Short TX <5ms)
        DB::transaction(function () use ($page, $sealedRelease, $targetRevision) {
            /** @var Page $lockedPage */
            $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();

            // Transition previous active release to superseded
            PageRelease::where('page_id', $lockedPage->id)
                ->where('status', PageReleaseStatus::ACTIVE)
                ->update(['status' => PageReleaseStatus::SUPERSEDED]);

            // Activate sealed release
            $sealedRelease->update([
                'status' => PageReleaseStatus::ACTIVE,
                'activated_at' => now(),
            ]);

            // Update page pointers
            $lockedPage->update([
                'published_revision_id' => $targetRevision->id,
                'status' => 'published',
            ]);
        });

        return $sealedRelease->fresh();
    }

    /**
     * Rollback the public live release to a target release (D-033).
     *
     * Invariants:
     * 1. Public release rollback NEVER modifies pages.document_json or author working draft.
     * 2. Pre-rollback verifies disk integrity. If verified -> instant DB pointer switch (<50ms).
     * 3. If files missing or corrupt -> safe re-render fallback from immutable PageRevision.
     *
     * @return array{
     *     success: bool,
     *     instant: bool,
     *     recompiled: bool,
     *     release: PageRelease
     * }
     */
    public function rollbackToRelease(
        Page $page,
        PageRelease $targetRelease,
        ?User $user = null,
        ?string $disk = 'local'
    ): array {
        if ((int) $targetRelease->page_id !== (int) $page->id) {
            throw new PublishFailedException('Target release does not belong to this page.');
        }

        $storage = Storage::disk($disk ?? 'local');
        $dirPath = $targetRelease->directory_path;

        $isVerified = false;
        if ($dirPath && $storage->exists($dirPath.'/index.html') && $storage->exists($dirPath.'/manifest.json')) {
            $report = $this->manifestVerifier->verifyDirectoryWithReport($dirPath, $disk);
            $isVerified = $report['valid'];
        }

        if ($isVerified) {
            // Case A: Instant pointer switch (<50ms)
            DB::transaction(function () use ($page, $targetRelease) {
                /** @var Page $lockedPage */
                $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();

                PageRelease::where('page_id', $lockedPage->id)
                    ->where('status', PageReleaseStatus::ACTIVE)
                    ->update(['status' => PageReleaseStatus::SUPERSEDED]);

                PageRelease::where('id', $targetRelease->id)->update([
                    'status' => PageReleaseStatus::ACTIVE,
                    'activated_at' => now(),
                ]);
                $targetRelease->refresh();

                $lockedPage->update([
                    'published_revision_id' => $targetRelease->page_revision_id,
                    'status' => 'published',
                ]);
            });

            return [
                'success' => true,
                'instant' => true,
                'recompiled' => false,
                'release' => $targetRelease->fresh(),
            ];
        }

        // Case B: Re-render fallback from immutable PageRevision
        /** @var PageRevision $revision */
        $revision = $targetRelease->revision ?? PageRevision::findOrFail($targetRelease->page_revision_id);
        $newRelease = $this->stagingService->stageRevision($revision, $user, $disk);

        DB::transaction(function () use ($page, $newRelease, $revision) {
            /** @var Page $lockedPage */
            $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();

            PageRelease::where('page_id', $lockedPage->id)
                ->where('status', PageReleaseStatus::ACTIVE)
                ->update(['status' => PageReleaseStatus::SUPERSEDED]);

            $newRelease->update([
                'status' => PageReleaseStatus::ACTIVE,
                'activated_at' => now(),
            ]);

            $lockedPage->update([
                'published_revision_id' => $revision->id,
                'status' => 'published',
            ]);
        });

        return [
            'success' => true,
            'instant' => false,
            'recompiled' => true,
            'release' => $newRelease->fresh(),
        ];
    }
}
