<?php

namespace App\Domains\Pages\Services;

use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Domains\Media\Services\MediaConcurrencyLock;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Domains\SEO\Services\PageSeoManager;
use App\Enums\MediaAssetStatus;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class PagePersistenceService
{
    /**
     * Threshold in seconds for autosave revision milestones (5 minutes).
     */
    public const AUTOSAVE_MILESTONE_SECONDS = 300;

    public function __construct(
        protected DocumentValidator $validator,
        protected MediaUsageScanner $scanner,
        protected MediaConcurrencyLock $lockService,
    ) {}

    /**
     * Persist page document content with optimistic concurrency control and D-023 advisory locking.
     *
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>|null  $seoMetadata
     * @return array{
     *     success: bool,
     *     status: int,
     *     error?: string,
     *     error_code?: string,
     *     message?: string,
     *     details?: mixed,
     *     lock_version?: int,
     *     updated_at?: string|null,
     *     revision_id?: int|null,
     *     revision_number?: int|null,
     *     current_lock_version?: int
     * }
     */
    public function savePageContent(
        Page $page,
        Website $website,
        array $document,
        int $submittedLockVersion,
        User $user,
        ?string $changeSummary = null,
        bool $isAutosave = false,
        ?array $seoMetadata = null,
    ): array {
        // 1. Authoritative Document Schema v1 validation
        $validationResult = $this->validator->validate($document);
        if (! $validationResult->isValid()) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'validation_failed',
                'message' => 'The document failed Schema v1 validation.',
                'details' => array_map(fn ($e) => $e->toArray(), $validationResult->getErrors()),
            ];
        }

        // 2. Pre-transaction scan of incoming document media references
        $inspection = $this->scanner->inspectAssetReferences($document, $website);
        if ($inspection['indeterminate']) {
            return [
                'success' => false,
                'status' => 422,
                'error_code' => 'media_scan_indeterminate',
                'message' => 'Document media references cannot be scanned reliably.',
            ];
        }

        if ($inspection['has_foreign_media'] || ! empty($inspection['unresolved_media_paths'])) {
            return [
                'success' => false,
                'status' => 422,
                'error_code' => 'media_asset_unavailable',
                'message' => 'One or more media assets referenced in this document are unavailable or invalid.',
            ];
        }

        $newAssetIds = $inspection['asset_ids'];

        // 3. Database transaction wrapping row locking, advisory locking, and persistence
        return DB::transaction(function () use (
            $page,
            $website,
            $document,
            $submittedLockVersion,
            $user,
            $changeSummary,
            $isAutosave,
            $newAssetIds,
            $seoMetadata
        ) {
            // 4. Reload page under exclusive row lock FOR UPDATE
            /** @var Page|null $lockedPage */
            $lockedPage = Page::where('website_id', $website->id)
                ->where('id', $page->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPage) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Page not found.',
                ];
            }

            // 5. Optimistic Concurrency Check (D-026)
            if ($lockedPage->lock_version !== $submittedLockVersion) {
                return [
                    'success' => false,
                    'status' => 409,
                    'error_code' => 'document_conflict',
                    'message' => 'This page has been modified in another session or tab.',
                    'current_lock_version' => $lockedPage->lock_version,
                    'updated_at' => $lockedPage->updated_at?->toIso8601String(),
                ];
            }

            // 6. Scan old document media references from the freshly locked record
            $oldAssetIds = [];
            if ($lockedPage->document_json !== null) {
                $oldInspection = $this->scanner->inspectAssetReferences($lockedPage->document_json, $website);
                if ($oldInspection['indeterminate']) {
                    return [
                        'success' => false,
                        'status' => 422,
                        'error_code' => 'media_scan_indeterminate',
                        'message' => 'Existing document media references cannot be scanned reliably.',
                    ];
                }
                $oldAssetIds = $oldInspection['asset_ids'];
            }

            // 7. Compute lock union and acquire D-023 advisory locks in ascending numerical order
            $allLockIds = array_values(array_unique(array_merge($newAssetIds, $oldAssetIds)));
            if (! empty($allLockIds)) {
                $this->lockService->lockAssets($allLockIds);

                // 8. Revalidate incoming assets under locks: must be active, untrashed, and owned by this website
                if (! empty($newAssetIds)) {
                    $activeAssets = MediaAsset::where('website_id', $website->id)
                        ->whereIn('id', $newAssetIds)
                        ->where('status', MediaAssetStatus::ACTIVE)
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->pluck('id')
                        ->all();

                    if (count($activeAssets) !== count($newAssetIds)) {
                        return [
                            'success' => false,
                            'status' => 422,
                            'error_code' => 'media_asset_unavailable',
                            'message' => 'One or more media assets referenced in this document are unavailable or invalid.',
                        ];
                    }
                }
            }

            // 9. Update page document and increment lock_version
            $lockedPage->document_json = $document;
            if ($seoMetadata !== null) {
                $lockedPage->seo_metadata = (new PageSeoManager)->normalizeSeoMetadata($seoMetadata);
            }
            $lockedPage->lock_version = $lockedPage->lock_version + 1;
            $lockedPage->updated_by = $user->id;
            $lockedPage->save();

            // 10. Revision Creation Policy (D-028)
            $createdRevision = $this->evaluateAndCreateRevision(
                $lockedPage,
                $document,
                $user,
                $changeSummary,
                $isAutosave
            );

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Page content saved successfully.',
                'lock_version' => $lockedPage->lock_version,
                'updated_at' => $lockedPage->updated_at?->toIso8601String(),
                'revision_id' => $createdRevision?->id,
                'revision_number' => $createdRevision?->revision_number,
            ];
        });
    }

    /**
     * Rollback page content to an immutable historical revision (D-029).
     * Strictly creates a new sequential revision (N+1); never mutates or deletes historical records.
     *
     * @return array{
     *     success: bool,
     *     status: int,
     *     error?: string,
     *     error_code?: string,
     *     message?: string,
     *     lock_version?: int,
     *     updated_at?: string|null,
     *     document_json?: array<string, mixed>,
     *     revision_id?: int,
     *     revision_number?: int
     * }
     */
    public function rollbackToRevision(
        Page $page,
        Website $website,
        PageRevision $targetRevision,
        User $user
    ): array {
        // Enforce website & page scope
        if ($targetRevision->page_id !== $page->id || $page->website_id !== $website->id) {
            return [
                'success' => false,
                'status' => 404,
                'message' => 'Revision not found for this page.',
            ];
        }

        $restoredDoc = $targetRevision->document_json;

        // 1. Authoritative Document Schema v1 validation of the restored document
        $validationResult = $this->validator->validate($restoredDoc);
        if (! $validationResult->isValid()) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'validation_failed',
                'message' => 'Historical revision document failed Schema v1 validation.',
            ];
        }

        // 2. Scan media references in restored document
        $inspection = $this->scanner->inspectAssetReferences($restoredDoc, $website);
        if ($inspection['indeterminate']) {
            return [
                'success' => false,
                'status' => 422,
                'error_code' => 'media_scan_indeterminate',
                'message' => 'Restored revision media references cannot be scanned reliably.',
            ];
        }

        if ($inspection['has_foreign_media'] || ! empty($inspection['unresolved_media_paths'])) {
            return [
                'success' => false,
                'status' => 422,
                'error_code' => 'media_asset_unavailable',
                'message' => 'One or more media assets referenced in this revision are unavailable or invalid.',
            ];
        }

        $newAssetIds = $inspection['asset_ids'];

        // 3. Database transaction wrapping row locking, advisory locking, page update, and revision insertion
        return DB::transaction(function () use (
            $page,
            $website,
            $targetRevision,
            $restoredDoc,
            $newAssetIds,
            $user
        ) {
            /** @var Page|null $lockedPage */
            $lockedPage = Page::where('website_id', $website->id)
                ->where('id', $page->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPage) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Page not found.',
                ];
            }

            // Scan old document from locked page
            $oldAssetIds = [];
            if ($lockedPage->document_json !== null) {
                $oldInspection = $this->scanner->inspectAssetReferences($lockedPage->document_json, $website);
                if ($oldInspection['indeterminate']) {
                    return [
                        'success' => false,
                        'status' => 422,
                        'error_code' => 'media_scan_indeterminate',
                        'message' => 'Current document media references cannot be scanned reliably.',
                    ];
                }
                $oldAssetIds = $oldInspection['asset_ids'];
            }

            // Lock union under D-023
            $allLockIds = array_values(array_unique(array_merge($newAssetIds, $oldAssetIds)));
            if (! empty($allLockIds)) {
                $this->lockService->lockAssets($allLockIds);

                // Revalidate new assets under locks
                if (! empty($newAssetIds)) {
                    $activeAssets = MediaAsset::where('website_id', $website->id)
                        ->whereIn('id', $newAssetIds)
                        ->where('status', MediaAssetStatus::ACTIVE)
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->pluck('id')
                        ->all();

                    if (count($activeAssets) !== count($newAssetIds)) {
                        return [
                            'success' => false,
                            'status' => 422,
                            'error_code' => 'media_asset_unavailable',
                            'message' => 'One or more media assets referenced in this revision are unavailable or invalid.',
                        ];
                    }
                }
            }

            // Determine next sequential revision number
            $maxRevNumber = (int) (PageRevision::where('page_id', $lockedPage->id)->lockForUpdate()->max('revision_number') ?? 0);
            $nextRevisionNumber = $maxRevNumber + 1;

            // Update page working draft and increment lock_version
            $lockedPage->document_json = $restoredDoc;
            $lockedPage->lock_version = $lockedPage->lock_version + 1;
            $lockedPage->updated_by = $user->id;
            $lockedPage->save();

            // Insert new sequential revision record (D-029: strictly non-destructive)
            $newRevision = PageRevision::create([
                'page_id' => $lockedPage->id,
                'revision_number' => $nextRevisionNumber,
                'schema_version' => 1,
                'document_json' => $restoredDoc,
                'document_hash' => PageRevision::canonicalizeDocument($restoredDoc),
                'change_summary' => "Restored from Revision #{$targetRevision->revision_number}",
                'is_autosave' => false,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            return [
                'success' => true,
                'status' => 200,
                'message' => "Successfully rolled back to Revision #{$targetRevision->revision_number}.",
                'lock_version' => $lockedPage->lock_version,
                'updated_at' => $lockedPage->updated_at?->toIso8601String(),
                'document_json' => $restoredDoc,
                'revision_id' => $newRevision->id,
                'revision_number' => $newRevision->revision_number,
            ];
        });
    }

    /**
     * Evaluate whether a revision record should be created according to D-028 anti-bloat policy.
     *
     * @param  array<string, mixed>  $document
     */
    protected function evaluateAndCreateRevision(
        Page $page,
        array $document,
        User $user,
        ?string $changeSummary,
        bool $isAutosave
    ): ?PageRevision {
        $newHash = PageRevision::canonicalizeDocument($document);

        /** @var PageRevision|null $latestRevision */
        $latestRevision = PageRevision::where('page_id', $page->id)
            ->orderBy('revision_number', 'desc')
            ->lockForUpdate()
            ->first();

        // 1. Content deduplication: If identical to latest revision, skip revision insertion
        if ($latestRevision !== null && $latestRevision->document_hash === $newHash) {
            return null;
        }

        // 2. Initial revision for this page
        if ($latestRevision === null) {
            return PageRevision::create([
                'page_id' => $page->id,
                'revision_number' => 1,
                'schema_version' => 1,
                'document_json' => $document,
                'document_hash' => $newHash,
                'change_summary' => $changeSummary ?? ($isAutosave ? 'Initial autosave' : 'Initial version'),
                'is_autosave' => $isAutosave,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }

        // 3. Manual save always creates a new revision
        if (! $isAutosave) {
            return PageRevision::create([
                'page_id' => $page->id,
                'revision_number' => $latestRevision->revision_number + 1,
                'schema_version' => 1,
                'document_json' => $document,
                'document_hash' => $newHash,
                'change_summary' => $changeSummary ?? 'Manual save',
                'is_autosave' => false,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }

        // 4. Autosave milestone check: creates revision only if elapsed time >= 5 minutes
        $createdAt = $latestRevision->created_at ?? now();

        if ($createdAt->diffInSeconds(now()) >= self::AUTOSAVE_MILESTONE_SECONDS) {
            return PageRevision::create([
                'page_id' => $page->id,
                'revision_number' => $latestRevision->revision_number + 1,
                'schema_version' => 1,
                'document_json' => $document,
                'document_hash' => $newHash,
                'change_summary' => $changeSummary ?? 'Autosave checkpoint',
                'is_autosave' => true,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        }

        // Within 5-minute milestone window: content updated in pages.document_json, no revision record created
        return null;
    }
}
