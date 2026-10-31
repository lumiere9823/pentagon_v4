<?php

namespace App\Domains\Websites\Services;

use App\Domains\Websites\Contracts\WebsiteProvisionerInterface;
use App\Enums\WebsiteRole;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class WebsiteProvisioner implements WebsiteProvisionerInterface
{
    public function __construct(
        protected StarterSetCatalog $starterCatalog,
        protected StarterPageCreator $starterPageCreator,
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
                $this->starterPageCreator->create($website, $pageDef, $owner);
            }

            return $website;
        });
    }
}
