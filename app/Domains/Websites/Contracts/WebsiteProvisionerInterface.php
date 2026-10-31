<?php

namespace App\Domains\Websites\Contracts;

use App\Models\User;
use App\Models\Website;

interface WebsiteProvisionerInterface
{
    /**
     * Atomically provision a website, owner membership, starter pages, and initial revisions.
     *
     * @param  array<string, mixed>  $websiteAttributes
     */
    public function provision(
        User $owner,
        array $websiteAttributes,
        string $starterSet = 'default',
    ): Website;
}
