<?php

namespace App\Domains\Websites\Services;

use App\Domains\Websites\Contracts\WebsiteProvisionerInterface;
use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class WebsiteProvisioner implements WebsiteProvisionerInterface
{
    public function __construct(
        protected StarterSetCatalog $starterCatalog
    ) {}

    /**
     * Atomically provision a website, owner membership, starter pages, and initial revisions.
     *
     * @param  array<string, mixed>  $websiteAttributes
     */
    public function provision(
        User $owner,
        array $websiteAttributes,
        string $starterSet = 'default',
    ): Website {
        return DB::transaction(function () use ($owner, $websiteAttributes, $starterSet) {
            $website = new Website;
            $website->owner_id = $owner->id;
            $website->name = $websiteAttributes['name'];
            $website->slug = $websiteAttributes['slug'];
            $website->status = $websiteAttributes['status'] ?? 'active';
            if (isset($websiteAttributes['settings_json'])) {
                $website->settings_json = $websiteAttributes['settings_json'];
            }
            $website->save();

            // Synchronize owner membership invariant
            $website->memberships()->create([
                'user_id' => $owner->id,
                'role' => WebsiteRole::OWNER,
            ]);

            // Provision starter set pages
            $starterPages = $this->starterCatalog->get($starterSet);

            foreach ($starterPages as $pageDef) {
                $page = $website->pages()->create([
                    'title' => $pageDef['title'],
                    'slug' => $pageDef['slug'],
                    'is_homepage' => $pageDef['is_homepage'],
                    'status' => PageStatus::DRAFT,
                    'lock_version' => 1,
                    'document_json' => $pageDef['document'],
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ]);

                PageRevision::create([
                    'page_id' => $page->id,
                    'revision_number' => 1,
                    'schema_version' => 1,
                    'document_json' => $pageDef['document'],
                    'document_hash' => PageRevision::canonicalizeDocument($pageDef['document']),
                    'change_summary' => $pageDef['summary'],
                    'is_autosave' => false,
                    'created_by' => $owner->id,
                    'created_at' => now(),
                ]);
            }

            return $website;
        });
    }
}
