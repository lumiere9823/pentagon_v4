<?php

namespace App\Domains\Websites\Services;

use App\Domains\DocumentEngine\DocumentLimits;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;

class StarterPageCreator
{
    /**
     * Create one canonical starter page and its initial revision.
     *
     * The caller owns the surrounding transaction.
     *
     * @param  array{
     *     title: string,
     *     slug: string,
     *     is_homepage: bool,
     *     document: array<string, mixed>,
     *     summary: string
     * }  $definition
     */
    public function create(Website $website, array $definition, User $actor): Page
    {
        $page = $website->pages()->create([
            'title' => $definition['title'],
            'slug' => $definition['slug'],
            'is_homepage' => $definition['is_homepage'],
            'status' => PageStatus::DRAFT,
            'lock_version' => 1,
            'document_json' => $definition['document'],
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        PageRevision::create([
            'page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => DocumentLimits::CURRENT_SCHEMA_VERSION,
            'document_json' => $definition['document'],
            'document_hash' => PageRevision::canonicalizeDocument($definition['document']),
            'change_summary' => $definition['summary'],
            'is_autosave' => false,
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);

        return $page;
    }
}
