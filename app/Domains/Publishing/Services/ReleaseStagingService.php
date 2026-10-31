<?php

namespace App\Domains\Publishing\Services;

use App\Domains\Media\Services\MediaUsageScanner;
use App\Domains\Publishing\Exceptions\PublishRenderingException;
use App\Domains\Publishing\Exceptions\ReleaseStagingException;
use App\Domains\SEO\Services\PageSeoManager;
use App\Enums\PageReleaseStatus;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReleaseStagingService
{
    public const RENDERER_VERSION = 2;

    public function __construct(
        protected DocumentHtmlRenderer $htmlRenderer,
        protected ReleaseManifestVerifier $manifestVerifier,
        protected MediaUsageScanner $mediaUsageScanner,
        protected PageSeoManager $seoManager,
    ) {}

    /**
     * Create an unsealed candidate release attached to an immutable PageRevision.
     */
    public function createCandidate(PageRevision $revision, ?User $creator = null): PageRelease
    {
        return DB::transaction(function () use ($revision, $creator) {
            $page = Page::lockForUpdate()->find($revision->page_id) ?? Page::findOrFail($revision->page_id);
            $nextNumber = ((int) PageRelease::where('page_id', $page->id)->max('release_number')) + 1;

            return PageRelease::create([
                'page_id' => $page->id,
                'page_revision_id' => $revision->id,
                'release_number' => $nextNumber,
                'status' => PageReleaseStatus::PENDING,
                'schema_version' => $revision->schema_version,
                'renderer_version' => self::RENDERER_VERSION,
                'created_by' => $creator?->id,
            ]);
        });
    }

    /**
     * Render, stage, verify, and seal a candidate PageRelease on disk.
     *
     * Fails closed: On any render, I/O, integrity, or collision error,
     * deletes temporary staging directory and marks release as FAILED.
     *
     * INVARIANT: Never activates release or touches pages.published_revision_id in Checkpoint B3.
     *
     * @throws PublishRenderingException|ReleaseStagingException|Throwable
     */
    public function stageAndSeal(PageRelease $release, ?string $disk = 'local'): PageRelease
    {
        $release->loadMissing(['page.website', 'revision']);

        if ($release->status === PageReleaseStatus::ACTIVE) {
            throw new ReleaseStagingException('Cannot stage an already active release.');
        }

        $page = $release->page;
        $revision = $release->revision;

        if (! $page || ! $revision) {
            throw new ReleaseStagingException('Candidate release must have an associated Page and PageRevision.');
        }

        $websiteId = $this->sanitizePathSegment($page->website_id);
        $pageId = $this->sanitizePathSegment($page->id);
        $releaseNumber = $this->sanitizePathSegment($release->release_number);

        $rawDoc = (string) (json_encode($revision->document_json) ?: '');
        $docHash = $revision->document_hash ?: hash('sha256', $rawDoc);
        $hash8 = substr($docHash, 0, 8);

        $baseDir = "releases/{$websiteId}/{$pageId}";
        $stagingDir = "{$baseDir}/staging_{$release->id}";
        $sealedDir = "{$baseDir}/r_{$releaseNumber}_{$hash8}";

        $storage = Storage::disk($disk ?? 'local');
        $sealedAbs = $storage->path($sealedDir);
        $stagingAbs = $storage->path($stagingDir);

        // Guard against destination collision: fail closed without overwriting
        if (file_exists($sealedAbs)) {
            $release->update([
                'status' => PageReleaseStatus::FAILED,
                'error_message' => "Destination release directory already exists on disk: {$sealedDir}",
            ]);

            throw new ReleaseStagingException("Destination release directory already exists on disk: {$sealedDir}");
        }

        // Clean up any stale staging folder from a prior attempt
        if (is_dir($stagingAbs)) {
            $storage->deleteDirectory($stagingDir);
        }

        // Create staging directory
        if (! is_dir($stagingAbs)) {
            $created = @mkdir($stagingAbs, 0755, true);
            if (! $created && ! is_dir($stagingAbs)) {
                $release->update([
                    'status' => PageReleaseStatus::FAILED,
                    'error_message' => "Failed to create staging directory on disk: {$stagingDir}",
                ]);

                throw new ReleaseStagingException("Failed to create staging directory on disk: {$stagingDir}");
            }
        }

        try {
            // Step 1: Render static HTML via DocumentHtmlRenderer (fail-closed)
            $seoPayload = $page->website
                ? $this->seoManager->buildSeoPayload($page, $page->website, $page->seo_metadata)
                : [];
            $html = $this->htmlRenderer->renderDocument($revision->document_json, $seoPayload, false);

            $indexPath = $stagingAbs.DIRECTORY_SEPARATOR.'index.html';
            $writeIndex = @file_put_contents($indexPath, $html);
            if ($writeIndex === false) {
                throw new ReleaseStagingException('Failed to write index.html to staging directory.');
            }

            $fileBytes = filesize($indexPath);
            if ($fileBytes === false || $fileBytes === 0) {
                throw new ReleaseStagingException('Staged index.html is empty or unreadable.');
            }

            $fileHash = hash_file('sha256', $indexPath);
            if ($fileHash === false) {
                throw new ReleaseStagingException('Failed to compute SHA-256 hash for index.html.');
            }

            // Step 2: Extract media asset references from revision AST
            $mediaAssetIds = $this->extractMediaAssetIds($revision->document_json, $page->website ?? Website::find($page->website_id));

            // Step 3: Construct canonical manifest
            $manifest = [
                'release_uuid' => (string) $release->id,
                'page_id' => (int) $page->id,
                'page_revision_id' => (int) $revision->id,
                'release_number' => (int) $release->release_number,
                'schema_version' => $revision->schema_version,
                'renderer_version' => self::RENDERER_VERSION,
                'document_hash' => $docHash,
                'html_bytes' => $fileBytes,
                'html_hash' => $fileHash,
                'artifacts' => [
                    [
                        'path' => 'index.html',
                        'bytes' => $fileBytes,
                        'sha256' => $fileHash,
                    ],
                ],
                'media_asset_ids' => $mediaAssetIds,
                'created_at' => now()->toIso8601String(),
            ];

            $manifestPath = $stagingAbs.DIRECTORY_SEPARATOR.'manifest.json';
            $writeManifest = @file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if ($writeManifest === false) {
                throw new ReleaseStagingException('Failed to write manifest.json to staging directory.');
            }

            // Step 4: Pre-sealing Disk Integrity Verification
            $report = $this->manifestVerifier->verifyDirectoryWithReport($stagingDir, $disk);
            if (! $report['valid']) {
                throw new ReleaseStagingException(
                    'Pre-sealing integrity verification failed: '.implode('; ', $report['errors']),
                    $report['errors']
                );
            }

            // Step 5: Atomic filesystem sealing rename
            $renameSuccess = @rename($stagingAbs, $sealedAbs);
            if (! $renameSuccess) {
                throw new ReleaseStagingException("Failed to atomically rename candidate directory from {$stagingDir} to {$sealedDir}");
            }

            // Step 6: Update database PageRelease record
            $normalizedSealedDir = str_replace('\\', '/', $sealedDir);
            $release->update([
                'directory_path' => $normalizedSealedDir,
                'manifest_json' => $manifest,
                'error_message' => null,
                'status' => PageReleaseStatus::PENDING, // Strictly non-active in Checkpoint B3
            ]);

            return $release->fresh();
        } catch (Throwable $e) {
            // Fail-closed cleanup: unlink staging directory
            if (is_dir($stagingAbs)) {
                $storage->deleteDirectory($stagingDir);
            }

            $sanitizedError = substr($e->getMessage(), 0, 1000);
            $release->update([
                'status' => PageReleaseStatus::FAILED,
                'error_message' => $sanitizedError,
            ]);

            throw $e;
        }
    }

    /**
     * Helper to create candidate and stage/seal in one call.
     */
    public function stageRevision(PageRevision $revision, ?User $creator = null, ?string $disk = 'local'): PageRelease
    {
        $candidate = $this->createCandidate($revision, $creator);

        return $this->stageAndSeal($candidate, $disk);
    }

    /**
     * Safely extract unique media asset IDs referenced in the document.
     *
     * @return list<int>
     */
    protected function extractMediaAssetIds(mixed $documentJson, ?Website $website = null): array
    {
        try {
            $inspection = $this->mediaUsageScanner->inspectAssetReferences($documentJson, $website);
            $allIds = array_merge($inspection['asset_ids'], $inspection['foreign_asset_ids']);

            return array_values(array_unique(array_map('intval', $allIds)));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Reject path traversal characters in path identifiers.
     *
     * @throws ReleaseStagingException
     */
    protected function sanitizePathSegment(mixed $segment): string
    {
        $str = (string) $segment;
        if (! preg_match('/^[a-zA-Z0-9_\-]+$/', $str)) {
            throw new ReleaseStagingException("Invalid path identifier segment: {$str}");
        }

        return $str;
    }
}
