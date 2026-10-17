<?php

namespace App\Domains\Media\Services;

use App\Models\MediaAsset;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MediaUsageScanner
{
    /**
     * Scan all persisted document sources in the given website to find references to the media asset.
     *
     * @return list<array{id: int, title: string, slug: string}>
     */
    public function findUsagesInWebsite(Website $website, MediaAsset $asset): array
    {
        $referencingPages = [];
        $seenPageIds = [];

        // 1. Scan `pages` table if `document_json` column exists
        if (Schema::hasColumn('pages', 'document_json')) {
            $pages = DB::table('pages')
                ->where('website_id', $website->id)
                ->whereNull('deleted_at')
                ->whereNotNull('document_json')
                ->select(['id', 'title', 'slug', 'document_json'])
                ->get();

            foreach ($pages as $page) {
                if ($this->isAssetReferencedInDocument($page->document_json, $asset)) {
                    if (! isset($seenPageIds[$page->id])) {
                        $seenPageIds[$page->id] = true;
                        $referencingPages[] = [
                            'id' => (int) $page->id,
                            'title' => (string) $page->title,
                            'slug' => (string) $page->slug,
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
                if ($this->isAssetReferencedInDocument($rev->document_json, $asset)) {
                    if (! isset($seenPageIds[$rev->page_id])) {
                        $seenPageIds[$rev->page_id] = true;
                        $referencingPages[] = [
                            'id' => (int) $rev->page_id,
                            'title' => (string) $rev->page_title,
                            'slug' => (string) $rev->page_slug,
                        ];
                    }
                }
            }
        }

        return $referencingPages;
    }

    /**
     * Determine whether an asset is referenced in a document structure or raw JSON.
     */
    public function isAssetReferencedInDocument(mixed $document, MediaAsset $asset): bool
    {
        if (is_string($document)) {
            // Fast reject: if the unique storage filename is not present in the string, skip JSON decode
            $filename = basename($asset->storage_path);
            if (! str_contains($document, $filename)) {
                return false;
            }

            $decoded = json_decode($document, true);
            if (! is_array($decoded)) {
                return false;
            }
            $document = $decoded;
        }

        if (! is_array($document)) {
            return false;
        }

        $root = $document['root'] ?? $document;

        return $this->traverseNodesForAsset($root, $asset);
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
