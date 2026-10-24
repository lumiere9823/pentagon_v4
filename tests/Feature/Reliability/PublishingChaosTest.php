<?php

use App\Domains\Publishing\Exceptions\PublishFailedException;
use App\Domains\Publishing\Exceptions\PublishRenderingException;
use App\Domains\Publishing\Services\PagePublishingService;
use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('active release is strictly preserved when subsequent publish fails validation (Active Release Invariant)', function () {
    $user = User::factory()->create();
    $website = Website::factory()->create(['slug' => 'chaos-corp']);
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    // 1. Initial valid release
    $page = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Stable Page',
        'slug' => 'stable',
        'status' => PageStatus::DRAFT,
    ]);

    $validDoc = [
        'schemaVersion' => 1,
        'root' => [
            'id' => 'root',
            'type' => 'Container',
            'props' => ['maxWidth' => 'lg', 'centered' => true],
            'children' => [
                [
                    'id' => 'heading-1',
                    'type' => 'Heading',
                    'props' => ['level' => 1, 'text' => 'Stable Initial Content'],
                    'children' => [],
                ],
            ],
        ],
    ];
    $page->update(['document_json' => $validDoc]);

    $publishingService = app(PagePublishingService::class);
    $initialRelease = $publishingService->publish($page, $user);

    expect($initialRelease->status)->toBe(PageReleaseStatus::ACTIVE);

    // Verify public route serves initial content
    $this->get("/sites/{$website->slug}/stable")
        ->assertStatus(200)
        ->assertSee('Stable Initial Content');

    // 2. Corrupt document update in draft
    $corruptDoc = [
        'schemaVersion' => 1,
        'root' => [
            'id' => 'root',
            'type' => 'Container',
            'props' => [],
            'children' => [
                [
                    'id' => 'invalid-node',
                    'type' => 'UnknownMaliciousComponent',
                    'props' => [],
                    'children' => [],
                ],
            ],
        ],
    ];
    $page->update(['document_json' => $corruptDoc]);

    // Attempting to publish must throw exception and fail closed
    try {
        $publishingService->publish($page, $user);
        $this->fail('Expected PublishFailedException was not thrown.');
    } catch (PublishFailedException|PublishRenderingException $e) {
        // Expected failure closed
    }

    // 3. Assert Active Release Preservation Invariant
    $initialRelease->refresh();
    expect($initialRelease->status)->toBe(PageReleaseStatus::ACTIVE);

    // Public delivery must continue serving the initial stable release without interruption
    $this->get("/sites/{$website->slug}/stable")
        ->assertStatus(200)
        ->assertSee('Stable Initial Content');
});

test('rollback to a pruned release with missing physical files re-renders on demand from immutable AST (D-033)', function () {
    $user = User::factory()->create();
    $website = Website::factory()->create(['slug' => 'pruned-site']);
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    $page = Page::factory()->create([
        'website_id' => $website->id,
        'title' => 'Pruned Test',
        'slug' => 'pruned-test',
        'status' => PageStatus::DRAFT,
    ]);

    $publishingService = app(PagePublishingService::class);

    // 1. Publish Release 1
    $doc1 = [
        'schemaVersion' => 1,
        'root' => [
            'id' => 'root',
            'type' => 'Container',
            'props' => ['maxWidth' => 'lg', 'centered' => true],
            'children' => [
                ['id' => 'h1-rev1', 'type' => 'Heading', 'props' => ['level' => 1, 'text' => 'Release One Live Content'], 'children' => []],
            ],
        ],
    ];
    $page->update(['document_json' => $doc1]);
    $release1 = $publishingService->publish($page, $user);

    // 2. Publish Release 2 (supersedes Release 1)
    $doc2 = [
        'schemaVersion' => 1,
        'root' => [
            'id' => 'root',
            'type' => 'Container',
            'props' => ['maxWidth' => 'lg', 'centered' => true],
            'children' => [
                ['id' => 'h1-rev2', 'type' => 'Heading', 'props' => ['level' => 1, 'text' => 'Release Two Superseded Content'], 'children' => []],
            ],
        ],
    ];
    $page->update(['document_json' => $doc2]);
    $release2 = $publishingService->publish($page, $user);

    // 3. Simulate physical disk deletion / pruning of Release 1
    $storage = Storage::disk('local');
    $storage->deleteDirectory($release1->directory_path);
    expect($storage->exists($release1->directory_path.'/index.html'))->toBeFalse();

    // 4. Trigger rollback to Release 1
    $response = $this->actingAs($user)->postJson("/websites/{$website->id}/pages/{$page->id}/releases/{$release1->id}/rollback");
    $response->assertStatus(200);

    // 5. Verify that a re-rendered release is now ACTIVE for the page
    $activeRelease = PageRelease::where('page_id', $page->id)->where('status', PageReleaseStatus::ACTIVE)->first();
    expect($activeRelease)->not->toBeNull()
        ->and($activeRelease->page_revision_id)->toBe($release1->page_revision_id)
        ->and($storage->exists($activeRelease->directory_path.'/index.html'))->toBeTrue();

    // Public delivery serves the re-rendered Release 1 content
    $this->get("/sites/{$website->slug}/pruned-test")
        ->assertStatus(200)
        ->assertSee('Release One Live Content');
});

test('releases:reconcile command purges orphaned unactivated candidate directories', function () {
    $storage = Storage::disk('local');

    // Create an orphan staging folder on disk that has no active database pointer
    $orphanDir = 'releases/999/999/staging_fake_uuid';
    $storage->put($orphanDir.'/index.html', '<!DOCTYPE html><html><body>Orphan</body></html>');

    // Simulate age > 15 minutes
    $absPath = $storage->path($orphanDir.'/index.html');
    if (file_exists($absPath)) {
        touch($absPath, time() - 1800);
    }
    $absDir = $storage->path($orphanDir);
    if (file_exists($absDir)) {
        touch($absDir, time() - 1800);
    }

    $this->artisan('releases:reconcile --staging-ttl=1')
        ->assertSuccessful();

    // The orphan directory should be pruned
    expect($storage->exists($orphanDir.'/index.html'))->toBeFalse();
});
