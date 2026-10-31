<?php

use App\Domains\Publishing\Services\PagePublishingService;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('public sitemap endpoint delivers compliant XML with cache headers', function () {
    $website = Website::factory()->create(['slug' => 'sitemap-agency']);
    $user = User::factory()->create();
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    $page = Page::factory()->homepage()->create([
        'website_id' => $website->id,
        'title' => 'Home Page',
        'slug' => 'home',
        'status' => PageStatus::DRAFT,
        'seo_metadata' => [
            'meta_title' => 'Official Home',
            'noindex' => false,
        ],
    ]);

    // Publish the page to activate a release
    $doc = [
        'schemaVersion' => 1,
        'root' => [
            'id' => 'root',
            'type' => 'Container',
            'props' => ['maxWidth' => 'lg', 'centered' => true],
            'children' => [
                [
                    'id' => 'heading-1',
                    'type' => 'Heading',
                    'props' => ['level' => 1, 'text' => 'Welcome Home'],
                    'children' => [],
                ],
            ],
        ],
    ];
    $page->update(['document_json' => $doc]);

    $service = app(PagePublishingService::class);
    $service->publish($page, $user);

    $response = $this->get("/sites/{$website->slug}/sitemap.xml");

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/sites/sitemap-agency</loc>', false)
        ->assertDontSee('/sites/sitemap-agency/home', false)
        ->assertSee('<priority>1.0</priority>', false);

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=3600');
});

test('public robots.txt endpoint delivers standard directives pointing to sitemap', function () {
    $website = Website::factory()->create(['slug' => 'robots-test']);

    $response = $this->get("/sites/{$website->slug}/robots.txt");

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee("Allow: /sites/{$website->slug}/")
        ->assertSee('Disallow: /preview/')
        ->assertSee('Sitemap: '.url("/sites/{$website->slug}/sitemap.xml"));

    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=3600');
});

test('updating a published page slug automatically creates a 301 redirect and resolves it', function () {
    $user = User::factory()->create();
    $website = Website::factory()->create(['slug' => 'redirect-site']);
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    $page = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'About Us',
        'slug' => 'about-original',
        'status' => PageStatus::DRAFT,
    ]);

    $doc = [
        'schemaVersion' => 1,
        'root' => [
            'id' => 'root',
            'type' => 'Container',
            'props' => ['maxWidth' => 'lg', 'centered' => true],
            'children' => [
                [
                    'id' => 'heading-about',
                    'type' => 'Heading',
                    'props' => ['level' => 1, 'text' => 'About Us'],
                    'children' => [],
                ],
            ],
        ],
    ];
    $page->update(['document_json' => $doc]);

    // Publish page
    app(PagePublishingService::class)->publish($page, $user);

    // Update slug via PageController
    $this->actingAs($user)->put("/websites/{$website->id}/pages/{$page->id}", [
        'title' => 'About Us',
        'slug' => 'about-new',
        'status' => 'published',
    ])->assertRedirect();

    $this->assertDatabaseHas('page_redirects', [
        'website_id' => $website->id,
        'source_slug' => 'about-original',
        'target_slug' => 'about-new',
        'status_code' => 301,
        'is_active' => true,
    ]);

    // Re-publish under new slug so public files exist for the new slug
    $page->refresh();
    app(PagePublishingService::class)->publish($page, $user);

    // Requesting the old slug returns HTTP 301 redirect to the new slug!
    $redirectResponse = $this->get("/sites/{$website->slug}/about-original");
    $redirectResponse->assertStatus(301)
        ->assertRedirect("/sites/{$website->slug}/about-new");
});

test('publishing compiles custom SEO metadata and JSON-LD structured data into index.html', function () {
    $user = User::factory()->create();
    $website = Website::factory()->create(['slug' => 'seo-compiled']);
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    $page = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Original Page Title',
        'slug' => 'featured',
        'status' => PageStatus::DRAFT,
        'seo_metadata' => [
            'meta_title' => 'High Ranking SEO Title',
            'meta_description' => 'Targeted keywords and description.',
            'canonical_url' => 'https://example.com/canonical-source',
            'og_image_url' => 'https://example.com/featured.jpg',
            'noindex' => false,
            'schema_type' => 'WebPage',
        ],
    ]);

    $doc = [
        'schemaVersion' => 1,
        'root' => [
            'id' => 'root',
            'type' => 'Container',
            'props' => ['maxWidth' => 'lg', 'centered' => true],
            'children' => [
                [
                    'id' => 'heading',
                    'type' => 'Heading',
                    'props' => ['level' => 1, 'text' => 'Hello SEO World'],
                    'children' => [],
                ],
            ],
        ],
    ];
    $page->update(['document_json' => $doc]);

    $service = app(PagePublishingService::class);
    $release = $service->publish($page, $user);

    $response = $this->get("/sites/{$website->slug}/featured");

    $response->assertStatus(200)
        ->assertSee('<title>High Ranking SEO Title</title>', false)
        ->assertSee('<meta name="description" content="Targeted keywords and description.">', false)
        ->assertSee('<link rel="canonical" href="https://example.com/canonical-source">', false)
        ->assertSee('<meta property="og:title" content="High Ranking SEO Title">', false)
        ->assertSee('<meta property="og:image" content="https://example.com/featured.jpg">', false)
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('"@type":"WebPage"', false);
});
