<?php

namespace App\Domains\SEO\Services;

use App\Models\PageRedirect;
use App\Models\Website;

class RedirectManager
{
    public const MAX_CHAIN_DEPTH = 5;

    /**
     * Register a redirect from an old slug to a new slug.
     * Flattens redirect chains and avoids self-loops.
     */
    public function createRedirect(
        Website $website,
        string $oldSlug,
        string $newSlug,
        int $statusCode = 301
    ): ?PageRedirect {
        $oldSlug = trim($oldSlug);
        $newSlug = trim($newSlug);

        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
            return null;
        }

        // 1. Break direct loops: If a redirect from newSlug -> oldSlug exists, remove it
        PageRedirect::where('website_id', $website->id)
            ->where('source_slug', $newSlug)
            ->where('target_slug', $oldSlug)
            ->delete();

        // 2. Flatten existing chains: any redirect pointing to $oldSlug should now point to $newSlug
        PageRedirect::where('website_id', $website->id)
            ->where('target_slug', $oldSlug)
            ->where('source_slug', '!=', $newSlug)
            ->update(['target_slug' => $newSlug]);

        // 3. Upsert redirect
        /** @var PageRedirect $redirect */
        $redirect = PageRedirect::updateOrCreate(
            [
                'website_id' => $website->id,
                'source_slug' => $oldSlug,
            ],
            [
                'target_slug' => $newSlug,
                'status_code' => $statusCode,
                'is_active' => true,
            ]
        );

        return $redirect;
    }

    /**
     * Find active redirect for a given slug, resolving chains up to MAX_CHAIN_DEPTH.
     *
     * @return array{target_slug: string, status_code: int}|null
     */
    public function resolveRedirect(Website $website, string $slug): ?array
    {
        $currentSlug = $slug;
        $visited = [];
        $depth = 0;
        $finalStatusCode = 301;

        while ($depth < self::MAX_CHAIN_DEPTH) {
            if (isset($visited[$currentSlug])) {
                // Loop detected, abort chain
                break;
            }

            $visited[$currentSlug] = true;

            /** @var PageRedirect|null $redirect */
            $redirect = PageRedirect::where('website_id', $website->id)
                ->where('source_slug', $currentSlug)
                ->where('is_active', true)
                ->first();

            if (! $redirect) {
                break;
            }

            $currentSlug = $redirect->target_slug;
            $finalStatusCode = $redirect->status_code;
            $depth++;
        }

        if ($depth > 0 && $currentSlug !== $slug) {
            return [
                'target_slug' => $currentSlug,
                'status_code' => $finalStatusCode,
            ];
        }

        return null;
    }
}
