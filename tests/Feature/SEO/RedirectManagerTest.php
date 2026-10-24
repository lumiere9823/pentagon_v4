<?php

use App\Domains\SEO\Services\RedirectManager;
use App\Models\Website;

test('it creates and resolves 301 redirects', function () {
    $manager = new RedirectManager;
    $website = Website::factory()->create();

    $redirect = $manager->createRedirect($website, 'old-about', 'about-us');

    expect($redirect)->not->toBeNull()
        ->and($redirect->source_slug)->toBe('old-about')
        ->and($redirect->target_slug)->toBe('about-us')
        ->and($redirect->status_code)->toBe(301);

    $resolved = $manager->resolveRedirect($website, 'old-about');
    expect($resolved)->not->toBeNull()
        ->and($resolved['target_slug'])->toBe('about-us')
        ->and($resolved['status_code'])->toBe(301);
});

test('it flattens multi-hop redirect chains', function () {
    $manager = new RedirectManager;
    $website = Website::factory()->create();

    // Step 1: A -> B
    $manager->createRedirect($website, 'step-a', 'step-b');

    // Step 2: B -> C (should update A -> C as well)
    $manager->createRedirect($website, 'step-b', 'step-c');

    $resolvedA = $manager->resolveRedirect($website, 'step-a');
    $resolvedB = $manager->resolveRedirect($website, 'step-b');

    expect($resolvedA['target_slug'])->toBe('step-c')
        ->and($resolvedB['target_slug'])->toBe('step-c');
});

test('it eliminates direct self loops', function () {
    $manager = new RedirectManager;
    $website = Website::factory()->create();

    // A -> B
    $manager->createRedirect($website, 'page-a', 'page-b');

    // Now user tries B -> A (would cause circular loop)
    $manager->createRedirect($website, 'page-b', 'page-a');

    // B -> A should exist, and previous A -> B must have been broken
    $resolvedB = $manager->resolveRedirect($website, 'page-b');
    expect($resolvedB['target_slug'])->toBe('page-a');

    // A should not loop back to B
    $resolvedA = $manager->resolveRedirect($website, 'page-a');
    expect($resolvedA)->toBeNull();
});
