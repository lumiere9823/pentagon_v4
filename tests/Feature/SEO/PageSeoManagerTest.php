<?php

use App\Domains\SEO\Services\PageSeoManager;
use App\Models\Page;
use App\Models\Website;

test('it normalizes SEO metadata with bounded lengths and defaults', function () {
    $manager = new PageSeoManager;

    $normalized = $manager->normalizeSeoMetadata([
        'meta_title' => '<b>Title</b>'.str_repeat('a', 200),
        'meta_description' => '<script>alert(1)</script>Description'.str_repeat('b', 400),
        'canonical_url' => 'https://example.com/canonical',
        'og_title' => 'OG Title',
        'og_description' => 'OG Desc',
        'og_image_url' => '/storage/media/1/uuid.jpg',
        'twitter_card' => 'summary_large_image',
        'noindex' => true,
        'nofollow' => false,
        'schema_type' => 'Article',
    ]);

    expect($normalized['meta_title'])->toHaveLength(120)
        ->and($normalized['meta_title'])->not->toContain('<b>')
        ->and($normalized['meta_description'])->toHaveLength(320)
        ->and($normalized['meta_description'])->not->toContain('<script>')
        ->and($normalized['canonical_url'])->toBe('https://example.com/canonical')
        ->and($normalized['noindex'])->toBeTrue()
        ->and($normalized['nofollow'])->toBeFalse()
        ->and($normalized['schema_type'])->toBe('Article');
});

test('it computes fallback cascade correctly when custom SEO fields are empty', function () {
    $manager = new PageSeoManager;

    $website = Website::factory()->create(['slug' => 'acme-corp']);
    $page = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'About Our Company',
        'slug' => 'about',
        'seo_metadata' => [],
    ]);

    $payload = $manager->buildSeoPayload($page, $website);

    expect($payload['title'])->toBe('About Our Company')
        ->and($payload['description'])->toBe('')
        ->and($payload['canonical_url'])->toContain('/sites/acme-corp/about');

    $metaString = implode("\n", $payload['meta_tags']);
    expect($metaString)->toContain('<meta name="robots" content="index, follow">')
        ->and($metaString)->toContain('<meta property="og:title" content="About Our Company">')
        ->and($metaString)->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->and($metaString)->toContain('<script type="application/ld+json">');
});

test('it applies custom SEO overrides and noindex directives', function () {
    $manager = new PageSeoManager;

    $website = Website::factory()->create(['slug' => 'acme-corp']);
    $page = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Default Title',
        'slug' => 'secret-page',
        'seo_metadata' => [
            'meta_title' => 'Secret SEO Title',
            'meta_description' => 'Top secret project details.',
            'canonical_url' => 'https://example.com/canonical-override',
            'og_image_url' => 'https://example.com/banner.png',
            'noindex' => true,
            'nofollow' => true,
        ],
    ]);

    $payload = $manager->buildSeoPayload($page, $website);

    expect($payload['title'])->toBe('Secret SEO Title')
        ->and($payload['description'])->toBe('Top secret project details.')
        ->and($payload['canonical_url'])->toBe('https://example.com/canonical-override');

    $metaString = implode("\n", $payload['meta_tags']);
    expect($metaString)->toContain('<meta name="robots" content="noindex, nofollow">')
        ->and($metaString)->toContain('<meta property="og:image" content="https://example.com/banner.png">')
        ->and($metaString)->toContain('<meta name="twitter:image" content="https://example.com/banner.png">');
});

test('it formats custom structured data JSON-LD and securely escapes closing script tags (MQA-04)', function () {
    $manager = new PageSeoManager;

    $website = Website::factory()->create(['slug' => 'acme-corp']);
    $page = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Product Details',
        'slug' => 'product-1',
        'seo_metadata' => [
            'schema_type' => 'Organization',
            'structured_data_json' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Acme </script><script>alert(1)</script>',
                'url' => 'https://example.com',
            ],
        ],
    ]);

    $payload = $manager->buildSeoPayload($page, $website);
    $metaString = implode("\n", $payload['meta_tags']);

    expect($metaString)->toContain('<script type="application/ld+json">')
        ->and($metaString)->not->toContain('</script><script>alert(1)</script>')
        ->and($metaString)->toContain('\u003C/script\u003E');
});

test('it handles standalone nofollow directive and summary twitter card', function () {
    $manager = new PageSeoManager;

    $website = Website::factory()->create(['slug' => 'acme-corp']);
    $page = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Summary Page',
        'slug' => 'summary',
        'seo_metadata' => [
            'noindex' => false,
            'nofollow' => true,
            'twitter_card' => 'summary',
        ],
    ]);

    $payload = $manager->buildSeoPayload($page, $website);
    $metaString = implode("\n", $payload['meta_tags']);

    expect($metaString)->toContain('<meta name="robots" content="index, nofollow">')
        ->and($metaString)->toContain('<meta name="twitter:card" content="summary">');
});
