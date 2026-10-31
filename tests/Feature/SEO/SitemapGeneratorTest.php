<?php

use App\Domains\SEO\Services\SitemapGenerator;
use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;

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

test('unpublishing a published page immediately excludes it from sitemap while retaining other published pages (MQA-03)', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->create(['slug' => 'dynamic-sitemap-site']);
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $owner->id,
        'role' => WebsiteRole::OWNER,
    ]);

    // Page A (Published with Active Release)
    $pageA = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Alpha Page',
        'slug' => 'alpha',
        'status' => PageStatus::PUBLISHED,
    ]);
    $revA = PageRevision::factory()->create(['page_id' => $pageA->id]);
    PageRelease::factory()->create([
        'page_id' => $pageA->id,
        'page_revision_id' => $revA->id,
        'status' => PageReleaseStatus::ACTIVE,
    ]);

    // Page B (Published with Active Release)
    $pageB = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Beta Page',
        'slug' => 'beta',
        'status' => PageStatus::PUBLISHED,
    ]);
    $revB = PageRevision::factory()->create(['page_id' => $pageB->id]);
    PageRelease::factory()->create([
        'page_id' => $pageB->id,
        'page_revision_id' => $revB->id,
        'status' => PageReleaseStatus::ACTIVE,
    ]);

    // Initial sitemap contains both pages
    $initialXml = $this->get("/sites/{$website->slug}/sitemap.xml")
        ->assertOk()
        ->getContent();

    expect($initialXml)->toContain('/sites/dynamic-sitemap-site/alpha')
        ->and($initialXml)->toContain('/sites/dynamic-sitemap-site/beta');

    // Unpublish Page A
    $this->actingAs($owner)
        ->postJson(route('websites.pages.unpublish', ['website' => $website->id, 'page' => $pageA->id]))
        ->assertOk()
        ->assertJson(['success' => true, 'status' => 'draft']);

    // Sitemap after unpublish: Alpha excluded, Beta retained
    $updatedResponse = $this->get("/sites/{$website->slug}/sitemap.xml");
    $updatedResponse->assertOk();
    $updatedXml = $updatedResponse->getContent();

    expect($updatedXml)->not->toContain('/sites/dynamic-sitemap-site/alpha')
        ->and($updatedXml)->toContain('/sites/dynamic-sitemap-site/beta');
});
