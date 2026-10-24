<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Exceptions\MediaUsageScanException;
use App\Models\MediaAsset;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MediaUsageScanner
{
    /**
     * Scan all persisted document sources in the given website to find references to the media asset.
     *
     * @return list<array{id: int, title: string, slug: string, reference_type: string}>
     *
     * @throws MediaUsageScanException
     */
    public function findUsagesInWebsite(Website $website, MediaAsset $asset): array
    {
        $referencingPages = [];
        $seenPageIds = [];

        try {
            // 1. Scan `pages` table if `document_json` column exists
            if (Schema::hasColumn('pages', 'document_json')) {
                $pagesQuery = DB::table('pages')
                    ->where('website_id', $website->id)
                    ->whereNotNull('document_json');

                if (Schema::hasColumn('pages', 'deleted_at')) {
                    $pagesQuery->whereNull('deleted_at');
                }

                $pages = $pagesQuery->select(['id', 'title', 'slug', 'document_json'])->get();

                foreach ($pages as $page) {
                    $inspection = $this->inspectDocumentReference($page->document_json, $asset);
                    if ($inspection['referenced']) {
                        if (! isset($seenPageIds[$page->id])) {
                            $seenPageIds[$page->id] = true;
                            $referencingPages[] = [
                                'id' => (int) $page->id,
                                'title' => (string) $page->title,
                                'slug' => (string) $page->slug,
                                'reference_type' => $inspection['indeterminate'] ? 'indeterminate' : 'confirmed',
                            ];
                        }
                    }
                }
            }

            // 2. Scan `page_revisions` table if it exists
            if (Schema::hasTable('page_revisions')) {
                $revisions = DB::table('page_revisions')
                    ->join('pages', 'page_revisions.page_id', '=', 'pages.id')
                    ->where('pages.website_id', $website->id)
                    ->whereNotNull('page_revisions.document_json')
                    ->select([
                        'pages.id as page_id',
                        'pages.title as page_title',
                        'pages.slug as page_slug',
                        'page_revisions.document_json',
                    ])
                    ->get();

                foreach ($revisions as $rev) {
                    $inspection = $this->inspectDocumentReference($rev->document_json, $asset);
                    if ($inspection['referenced']) {
                        if (! isset($seenPageIds[$rev->page_id])) {
                            $seenPageIds[$rev->page_id] = true;
                            $referencingPages[] = [
                                'id' => (int) $rev->page_id,
                                'title' => (string) $rev->page_title,
                                'slug' => (string) $rev->page_slug,
                                'reference_type' => $inspection['indeterminate'] ? 'indeterminate' : 'confirmed',
                            ];
                        }
                    }
                }
            }

            // 3. Scan `page_templates` table defensively if it exists
            if (Schema::hasTable('page_templates')) {
                $templates = DB::table('page_templates')
                    ->where(function ($query) use ($website) {
                        $query->where('website_id', $website->id)
                            ->orWhereNull('website_id');
                    })
                    ->whereNotNull('document_json')
                    ->select(['id', 'name as title', 'slug', 'document_json'])
                    ->get();

                foreach ($templates as $tmpl) {
                    $inspection = $this->inspectDocumentReference($tmpl->document_json, $asset);
                    if ($inspection['referenced']) {
                        $templateKey = 'template_'.$tmpl->id;
                        if (! isset($seenPageIds[$templateKey])) {
                            $seenPageIds[$templateKey] = true;
                            $referencingPages[] = [
                                'id' => (int) $tmpl->id,
                                'title' => 'Template: '.(string) $tmpl->title,
                                'slug' => (string) $tmpl->slug,
                                'reference_type' => $inspection['indeterminate'] ? 'indeterminate' : 'confirmed',
                            ];
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            throw new MediaUsageScanException("Failed to scan media usage across website {$website->id}: {$e->getMessage()}", 0, $e);
        }

        return $referencingPages;
    }

    /**
     * Inspect whether an asset is referenced in a document structure or raw JSON string.
     * Implements fail-closed behavior for malformed or unparseable JSON documents.
     *
     * @return array{referenced: bool, indeterminate: bool}
     */
    public function inspectDocumentReference(mixed $document, MediaAsset $asset): array
    {
        if (is_string($document)) {
            $filename = basename($asset->storage_path);

            // Fast reject: if the unique storage filename is not present in the string,
            // the document cannot possibly reference this asset.
            if (! str_contains($document, $filename)) {
                return ['referenced' => false, 'indeterminate' => false];
            }

            // Document string contains the unique filename. Attempt JSON decode.
            $decoded = json_decode($document, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                // FAIL-CLOSED: The document contains the unique asset filename, but cannot
                // be parsed as valid JSON (syntax error, truncated JSON, or invalid UTF-8).
                // We MUST NOT assume it is unused. Block deletion safely as indeterminate.
                return ['referenced' => true, 'indeterminate' => true];
            }

            $document = $decoded;
        }

        if (! is_array($document)) {
            return ['referenced' => false, 'indeterminate' => false];
        }

        $root = $document['root'] ?? $document;
        $isReferenced = $this->traverseNodesForAsset($root, $asset);

        return ['referenced' => $isReferenced, 'indeterminate' => false];
    }

    /**
     * Determine whether an asset is referenced in a document structure or raw JSON.
     * Fails closed: returns true if referenced or if JSON is indeterminate/malformed.
     */
    public function isAssetReferencedInDocument(mixed $document, MediaAsset $asset): bool
    {
        $inspection = $this->inspectDocumentReference($document, $asset);

        return $inspection['referenced'];
    }

    /**
     * Recursively inspect nodes for Image component props or style overrides referencing the asset.
     */
    private function traverseNodesForAsset(mixed $node, MediaAsset $asset): bool
    {
        if (! is_array($node)) {
            return false;
        }

        // Check props (especially `src` for Image component)
        if (isset($node['props']) && is_array($node['props'])) {
            foreach ($node['props'] as $propValue) {
                if (is_string($propValue) && $this->isAssetReferencedInUrl($propValue, $asset)) {
                    return true;
                }
            }
        }

        // Check responsive styles if overrides contain URLs
        if (isset($node['styles']) && is_array($node['styles'])) {
            foreach ($node['styles'] as $viewportOverrides) {
                if (is_array($viewportOverrides)) {
                    foreach ($viewportOverrides as $styleValue) {
                        if (is_string($styleValue) && $this->isAssetReferencedInUrl($styleValue, $asset)) {
                            return true;
                        }
                    }
                }
            }
        }

        // Recursively inspect children
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if ($this->traverseNodesForAsset($child, $asset)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a URL strictly and accurately points to the target media asset.
     * Prevents false positives from substring or prefix collisions.
     */
    public function isAssetReferencedInUrl(string $url, MediaAsset $asset): bool
    {
        $decodedUrl = rawurldecode($url);
        $parsedPath = parse_url($decodedUrl, PHP_URL_PATH);
        if ($parsedPath === null || $parsedPath === false) {
            $parsedPath = $decodedUrl;
        }

        $normalizedParsed = ltrim($parsedPath, '/');
        $normalizedStorage = ltrim($asset->storage_path, '/');
        $normalizedWithStoragePrefix = 'storage/'.$normalizedStorage;

        return $normalizedParsed === $normalizedStorage || $normalizedParsed === $normalizedWithStoragePrefix;
    }

    /**
     * Inspect all media asset references within a document tree or raw JSON string.
     * Accurately distinguishes between confirmed empty references and indeterminate scans.
     * Also detects if any references point to foreign websites or unresolved local media.
     *
     * @return array{
     *     asset_ids: list<int>,
     *     indeterminate: bool,
     *     has_foreign_media: bool,
     *     foreign_asset_ids: list<int>,
     *     unresolved_media_paths: list<string>
     * }
     */
    public function inspectAssetReferences(mixed $document, ?Website $website = null): array
    {
        if (is_string($document)) {
            $decoded = json_decode($document, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                return [
                    'asset_ids' => [],
                    'indeterminate' => true,
                    'has_foreign_media' => false,
                    'foreign_asset_ids' => [],
                    'unresolved_media_paths' => [],
                ];
            }
            $document = $decoded;
        }

        if (! is_array($document)) {
            return [
                'asset_ids' => [],
                'indeterminate' => true,
                'has_foreign_media' => false,
                'foreign_asset_ids' => [],
                'unresolved_media_paths' => [],
            ];
        }

        $urls = [];
        $this->collectUrlsFromDocument($document['root'] ?? $document, $urls);

        if (empty($urls)) {
            return [
                'asset_ids' => [],
                'indeterminate' => false,
                'has_foreign_media' => false,
                'foreign_asset_ids' => [],
                'unresolved_media_paths' => [],
            ];
        }

        $normalizedPaths = [];
        foreach (array_keys($urls) as $url) {
            $decodedUrl = rawurldecode($url);
            $parsedPath = parse_url($decodedUrl, PHP_URL_PATH);
            if ($parsedPath !== null && $parsedPath !== false) {
                $trimmed = ltrim($parsedPath, '/');
                if (str_starts_with($trimmed, 'storage/')) {
                    $trimmed = substr($trimmed, 8);
                }
                if (str_starts_with($trimmed, 'media/')) {
                    $normalizedPaths[$trimmed] = true;
                }
            }
        }

        if (empty($normalizedPaths)) {
            return [
                'asset_ids' => [],
                'indeterminate' => false,
                'has_foreign_media' => false,
                'foreign_asset_ids' => [],
                'unresolved_media_paths' => [],
            ];
        }

        $matchingPaths = array_keys($normalizedPaths);

        $matchedAssets = MediaAsset::withTrashed()
            ->whereIn('storage_path', $matchingPaths)
            ->get(['id', 'website_id', 'storage_path']);

        $matchedByPath = [];
        $assetIds = [];
        $foreignAssetIds = [];
        $hasForeign = false;

        foreach ($matchedAssets as $asset) {
            $matchedByPath[$asset->storage_path] = $asset;

            if ($website === null) {
                $hasForeign = true;
                $foreignAssetIds[] = (int) $asset->id;
            } elseif ($asset->website_id !== $website->id) {
                $hasForeign = true;
                $foreignAssetIds[] = (int) $asset->id;
            } else {
                $assetIds[] = (int) $asset->id;
            }
        }

        $unresolved = [];
        foreach ($matchingPaths as $path) {
            if (! isset($matchedByPath[$path])) {
                $unresolved[] = $path;
                if ($website === null) {
                    $hasForeign = true;
                }
            }
        }

        return [
            'asset_ids' => array_values(array_unique($assetIds)),
            'indeterminate' => false,
            'has_foreign_media' => $hasForeign,
            'foreign_asset_ids' => array_values(array_unique($foreignAssetIds)),
            'unresolved_media_paths' => $unresolved,
        ];
    }

    /**
     * Extract all media asset IDs belonging to the website that are referenced in a document structure.
     * Throws MediaUsageScanException if the document is indeterminate or contains foreign media references.
     *
     * @return list<int>
     *
     * @throws MediaUsageScanException
     */
    public function extractReferencedAssetIds(Website $website, mixed $document): array
    {
        $inspection = $this->inspectAssetReferences($document, $website);

        if ($inspection['indeterminate']) {
            throw new MediaUsageScanException('Document JSON cannot be safely parsed to extract media references.');
        }

        return $inspection['asset_ids'];
    }

    /**
     * Collect all string URLs from node props and styles.
     *
     * @param  array<string, true>  $urls
     */
    private function collectUrlsFromDocument(mixed $node, array &$urls): void
    {
        if (! is_array($node)) {
            return;
        }

        if (isset($node['props']) && is_array($node['props'])) {
            foreach ($node['props'] as $propValue) {
                if (is_string($propValue) && str_contains($propValue, 'media/')) {
                    $urls[$propValue] = true;
                }
            }
        }

        if (isset($node['styles']) && is_array($node['styles'])) {
            foreach ($node['styles'] as $viewportOverrides) {
                if (is_array($viewportOverrides)) {
                    foreach ($viewportOverrides as $styleValue) {
                        if (is_string($styleValue) && str_contains($styleValue, 'media/')) {
                            $urls[$styleValue] = true;
                        }
                    }
                }
            }
        }

        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $this->collectUrlsFromDocument($child, $urls);
            }
        }
    }
}
