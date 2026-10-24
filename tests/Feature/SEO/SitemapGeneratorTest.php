<?php

use App\Domains\SEO\Services\SitemapGenerator;
use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\Website;

test('it generates valid W3C XML sitemap only for published active pages without noindex', function () {
    $generator = new SitemapGenerator;

    $website = Website::factory()->create(['slug' => 'sitemap-test']);

    // Page 1: Published, Active Release -> Should be included
    $page1 = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Home Page',
        'slug' => 'home',
        'status' => PageStatus::PUBLISHED,
        'seo_metadata' => ['noindex' => false],
    ]);
    $rev1 = PageRevision::factory()->create(['page_id' => $page1->id]);
    PageRelease::factory()->create([
        'page_id' => $page1->id,
        'page_revision_id' => $rev1->id,
        'status' => PageReleaseStatus::ACTIVE,
    ]);

    // Page 2: Published, but has noindex -> Should be EXCLUDED
    $page2 = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Hidden Page',
        'slug' => 'hidden',
        'status' => PageStatus::PUBLISHED,
        'seo_metadata' => ['noindex' => true],
    ]);
    $rev2 = PageRevision::factory()->create(['page_id' => $page2->id]);
    PageRelease::factory()->create([
        'page_id' => $page2->id,
        'page_revision_id' => $rev2->id,
        'status' => PageReleaseStatus::ACTIVE,
    ]);

    // Page 3: Draft -> Should be EXCLUDED
    $page3 = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Draft Page',
        'slug' => 'draft',
        'status' => PageStatus::DRAFT,
    ]);

    // Page 4: Published, but no active release -> Should be EXCLUDED
    $page4 = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Unreleased Page',
        'slug' => 'unreleased',
        'status' => PageStatus::PUBLISHED,
    ]);

    $xml = $generator->generateForWebsite($website);

    expect($xml)->toContain('<urlset')
        ->and($xml)->toContain('http://www.sitemaps.org/schemas/sitemap/0.9')
        ->and($xml)->toContain('/sites/sitemap-test/home')
        ->and($xml)->toContain('<priority>1.0</priority>')
        ->and($xml)->not->toContain('/sites/sitemap-test/hidden')
        ->and($xml)->not->toContain('/sites/sitemap-test/draft')
        ->and($xml)->not->toContain('/sites/sitemap-test/unreleased');
});
