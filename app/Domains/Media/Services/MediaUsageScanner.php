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
                $pages = DB::table('pages')
                    ->where('website_id', $website->id)
                    ->whereNull('deleted_at')
                    ->whereNotNull('document_json')
                    ->select(['id', 'title', 'slug', 'document_json'])
                    ->get();

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
        $parsedPath = parse_url($url, PHP_URL_PATH);
        if ($parsedPath === null || $parsedPath === false) {
            $parsedPath = $url;
        }

        $normalizedParsed = ltrim($parsedPath, '/');
        $normalizedStorage = ltrim($asset->storage_path, '/');
        $normalizedWithStoragePrefix = 'storage/'.$normalizedStorage;

        return $normalizedParsed === $normalizedStorage || $normalizedParsed === $normalizedWithStoragePrefix;
    }
}
