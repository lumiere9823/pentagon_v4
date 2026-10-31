<?php

namespace App\Domains\Publishing\Services;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Domains\Media\Services\MediaConcurrencyLock;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Domains\Publishing\Exceptions\MediaUnavailableException;
use App\Domains\Publishing\Exceptions\PublishConcurrencyException;
use App\Domains\Publishing\Exceptions\PublishFailedException;
use App\Domains\Publishing\Exceptions\PublishValidationException;
use App\Enums\MediaAssetStatus;
use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
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
        return $this->publishingLock->runExclusive(
            (int) $page->id,
            fn (): PageRelease => $this->performPublish($page, $user, $disk),
        );
    }

    private function performPublish(Page $page, ?User $user, ?string $disk): PageRelease
    {
        // Phase 1: Pre-flight snapshot & media verification (Short DB Transaction <10ms)
        /** @var array{release: PageRelease, target_revision: PageRevision} $preflight */
        $preflight = DB::transaction(function () use ($page, $user) {
            /** @var Page $lockedPage */
            $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();

            if ($lockedPage->document_json === null) {
                throw new PublishFailedException('Cannot publish a page without document content.');
            }

            $validation = $this->validator->validateForPublishing($lockedPage->document_json);
            if (! $validation->isValid()) {
                throw new PublishValidationException(
                    'Document failed publish validation. Fix the highlighted component fields and try again.',
                    array_map(fn ($error) => $error->toArray(), $validation->getErrors())
                );
            }

            $canonicalDocument = $validation->getSanitizedDocument();
            if ($canonicalDocument === null) {
                throw new PublishValidationException(
                    'Document failed canonicalization and cannot be published.',
                    []
                );
            }

            $docHash = PageRevision::canonicalizeDocument($canonicalDocument);

            /** @var PageRevision|null $latestRev */
            $latestRev = PageRevision::where('page_id', $lockedPage->id)
                ->orderBy('revision_number', 'desc')
                ->lockForUpdate()
                ->first();

            $latestCanonicalHash = null;
            if ($latestRev !== null) {
                $latestValidation = $this->validator->validateForPublishing($latestRev->document_json);
                $latestCanonicalDocument = $latestValidation->isValid()
                    ? $latestValidation->getSanitizedDocument()
                    : null;
                if ($latestCanonicalDocument !== null) {
                    $latestCanonicalHash = PageRevision::canonicalizeDocument($latestCanonicalDocument);
                }
            }

            if ($latestRev !== null && $latestCanonicalHash === $docHash) {
                $targetRev = $latestRev;
            } else {
                $nextRevNum = ($latestRev !== null ? $latestRev->revision_number : 0) + 1;
                $targetRev = PageRevision::create([
                    'page_id' => $lockedPage->id,
                    'revision_number' => $nextRevNum,
                    'schema_version' => DocumentLimits::CURRENT_SCHEMA_VERSION,
                    'document_json' => $canonicalDocument,
                    'document_hash' => $docHash,
                    'change_summary' => 'Published release',
                    'is_autosave' => false,
                    'created_by' => $user?->id,
                ]);
            }

            $website = $lockedPage->website ?? Website::findOrFail($lockedPage->website_id);
            $this->assertDocumentMediaAvailable($canonicalDocument, $website);

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
        $this->activateRelease($page, $sealedRelease, $targetRevision);

        return $sealedRelease->fresh();
    }

    /**
     * Take a page offline without racing an in-flight publish or rollback.
     */
    public function unpublish(Page $page, ?User $user = null): Page
    {
        return $this->publishingLock->runExclusive(
            (int) $page->id,
            function () use ($page, $user): Page {
                return DB::transaction(function () use ($page, $user): Page {
                    /** @var Page $lockedPage */
                    $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();
                    $lockedPage->update([
                        'status' => PageStatus::DRAFT,
                        'updated_by' => $user?->id,
                    ]);

                    return $lockedPage->fresh();
                });
            },
        );
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
        return $this->publishingLock->runExclusive(
            (int) $page->id,
            fn (): array => $this->performRollback($page, $targetRelease, $user, $disk),
        );
    }

    /**
     * @return array{
     *     success: bool,
     *     instant: bool,
     *     recompiled: bool,
     *     release: PageRelease
     * }
     */
    private function performRollback(
        Page $page,
        PageRelease $targetRelease,
        ?User $user,
        ?string $disk
    ): array {
        if ((int) $targetRelease->page_id !== (int) $page->id) {
            throw new PublishFailedException('Target release does not belong to this page.');
        }

        /** @var PageRevision $revision */
        $revision = $targetRelease->revision ?? PageRevision::findOrFail($targetRelease->page_revision_id);

        $storage = Storage::disk($disk ?? 'local');
        $dirPath = $targetRelease->directory_path;

        $isVerified = false;
        if ($dirPath && $storage->exists($dirPath.'/index.html') && $storage->exists($dirPath.'/manifest.json')) {
            $report = $this->manifestVerifier->verifyDirectoryWithReport($dirPath, $disk);
            $isVerified = $report['valid'];
        }

        if ($isVerified) {
            // Case A: Instant pointer switch (<50ms)
            $this->activateRelease($page, $targetRelease, $revision);

            return [
                'success' => true,
                'instant' => true,
                'recompiled' => false,
                'release' => $targetRelease->fresh(),
            ];
        }

        // Case B: Re-render fallback from immutable PageRevision
        $newRelease = $this->stagingService->stageRevision($revision, $user, $disk);

        try {
            $this->activateRelease($page, $newRelease, $revision);
        } catch (Throwable $e) {
            $newRelease->update([
                'status' => PageReleaseStatus::FAILED,
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }

        return [
            'success' => true,
            'instant' => false,
            'recompiled' => true,
            'release' => $newRelease->fresh(),
        ];
    }

    /**
     * Validate and lock every local media reference immediately before a
     * persistence or activation decision. Must run inside a DB transaction.
     *
     * @param  array<string, mixed>  $document
     */
    private function assertDocumentMediaAvailable(array $document, Website $website): void
    {
        $inspection = $this->mediaScanner->inspectAssetReferences($document, $website);

        if ($inspection['indeterminate'] || $inspection['has_foreign_media'] || ! empty($inspection['unresolved_media_paths'])) {
            throw new MediaUnavailableException('One or more media assets referenced in this document are unavailable or foreign.');
        }

        $assetIds = array_values(array_unique(array_map('intval', $inspection['asset_ids'])));
        if (empty($assetIds)) {
            return;
        }

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

    /**
     * Atomically revalidate the immutable revision and switch the public pointer.
     * This is the single implementation for publish and both rollback paths.
     */
    private function activateRelease(Page $page, PageRelease $release, PageRevision $revision): void
    {
        DB::transaction(function () use ($page, $release, $revision): void {
            /** @var Page $lockedPage */
            $lockedPage = Page::where('id', $page->id)->lockForUpdate()->firstOrFail();
            $website = $lockedPage->website ?? Website::findOrFail($lockedPage->website_id);

            $this->assertDocumentMediaAvailable($revision->document_json, $website);

            PageRelease::where('page_id', $lockedPage->id)
                ->where('status', PageReleaseStatus::ACTIVE)
                ->where('id', '!=', $release->id)
                ->update(['status' => PageReleaseStatus::SUPERSEDED]);

            PageRelease::where('id', $release->id)
                ->where('page_id', $lockedPage->id)
                ->update([
                    'status' => PageReleaseStatus::ACTIVE,
                    'activated_at' => now(),
                ]);

            $lockedPage->update([
                'published_revision_id' => $revision->id,
                'status' => PageStatus::PUBLISHED,
            ]);
        });
    }
}
