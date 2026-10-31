<?php

namespace Tests\Feature\Publishing;

use App\Domains\Publishing\Exceptions\MediaUnavailableException;
use App\Domains\Publishing\Exceptions\PublishFailedException;
use App\Domains\Publishing\Exceptions\PublishValidationException;
use App\Domains\Publishing\Services\PagePublishingService;
use App\Enums\MediaAssetStatus;
use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagePublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PagePublishingService $publishingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishingService = app(PagePublishingService::class);
        Storage::fake('local');
    }

    private function createValidDocument(string $headingText = 'Hello World'): array
    {
        return [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_section',
                'type' => 'Section',
                'props' => ['padding' => 'medium'],
                'children' => [
                    [
                        'id' => 'container_1',
                        'type' => 'Container',
                        'props' => ['maxWidth' => 'lg'],
                        'children' => [
                            [
                                'id' => 'heading_1',
                                'type' => 'Heading',
                                'props' => ['text' => $headingText, 'level' => 1],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Test Page',
                'description' => 'Test Description',
            ],
        ];
    }

    private function createPageWithDraft(?array $document = null): array
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);
        $doc = $document ?? $this->createValidDocument();

        $page = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'document_json' => $doc,
            'status' => 'draft',
        ]);

        return [$page, $user, $website];
    }

    public function test_publish_creates_immutable_release_and_activates_it(): void
    {
        [$page, $user, $website] = $this->createPageWithDraft();

        $release = $this->publishingService->publish($page, $user);

        $this->assertNotNull($release);
        $this->assertEquals(PageReleaseStatus::ACTIVE, $release->status);
        $this->assertEquals(1, $release->release_number);
        $this->assertNotNull($release->activated_at);
        $this->assertNotNull($release->directory_path);

        // Check storage contains index.html and manifest.json
        $storage = Storage::disk('local');
        $this->assertTrue($storage->exists($release->directory_path.'/index.html'));
        $this->assertTrue($storage->exists($release->directory_path.'/manifest.json'));

        // Check page pointers
        $page->refresh();
        $this->assertEquals(PageStatus::PUBLISHED, $page->status);
        $this->assertEquals($release->page_revision_id, $page->published_revision_id);
    }

    public function test_publish_transitions_previous_active_release_to_superseded(): void
    {
        [$page, $user, $website] = $this->createPageWithDraft();

        $release1 = $this->publishingService->publish($page, $user);
        $this->assertEquals(PageReleaseStatus::ACTIVE, $release1->status);

        // Update draft and publish second release
        $page->update([
            'document_json' => $this->createValidDocument('Second Release'),
        ]);

        $release2 = $this->publishingService->publish($page, $user);

        $release1->refresh();
        $release2->refresh();

        $this->assertEquals(PageReleaseStatus::SUPERSEDED, $release1->status);
        $this->assertEquals(PageReleaseStatus::ACTIVE, $release2->status);
        $this->assertEquals(2, $release2->release_number);

        // Verify only 1 release is active for this page
        $activeCount = PageRelease::where('page_id', $page->id)->where('status', PageReleaseStatus::ACTIVE)->count();
        $this->assertEquals(1, $activeCount);
    }

    public function test_publish_reuses_matching_revision_without_creating_duplicate(): void
    {
        [$page, $user, $website] = $this->createPageWithDraft();

        $doc = $page->document_json;
        $hash = PageRevision::canonicalizeDocument($doc);

        $existingRev = PageRevision::create([
            'page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => 1,
            'document_json' => $doc,
            'document_hash' => $hash,
            'change_summary' => 'Initial',
            'is_autosave' => false,
            'created_by' => $user->id,
        ]);

        $initialRevCount = PageRevision::where('page_id', $page->id)->count();

        $release = $this->publishingService->publish($page, $user);

        $finalRevCount = PageRevision::where('page_id', $page->id)->count();
        $this->assertEquals($initialRevCount, $finalRevCount);
        $this->assertEquals($existingRev->id, $release->page_revision_id);
    }

    public function test_publish_mints_new_revision_when_draft_has_uncaptured_changes(): void
    {
        [$page, $user, $website] = $this->createPageWithDraft($this->createValidDocument('Version 1'));

        PageRevision::create([
            'page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => 1,
            'document_json' => $page->document_json,
            'document_hash' => PageRevision::canonicalizeDocument($page->document_json),
            'change_summary' => 'Initial',
            'is_autosave' => false,
            'created_by' => $user->id,
        ]);

        // Change draft
        $newDoc = $this->createValidDocument('Version 2 with uncaptured edits');
        $page->update(['document_json' => $newDoc]);

        $release = $this->publishingService->publish($page, $user);

        $this->assertEquals(2, PageRevision::where('page_id', $page->id)->count());
        $latestRev = PageRevision::where('page_id', $page->id)->orderBy('revision_number', 'desc')->first();
        $this->assertEquals(2, $latestRev->revision_number);
        $this->assertEquals($latestRev->id, $release->page_revision_id);
    }

    public function test_publish_uses_canonical_snapshot_and_does_not_modify_existing_sealed_release_files(): void
    {
        [$page, $user] = $this->createPageWithDraft($this->createValidDocument('First release'));
        $firstRelease = $this->publishingService->publish($page, $user);
        $storage = Storage::disk('local');
        $firstHtmlPath = $firstRelease->directory_path.'/index.html';
        $firstManifestPath = $firstRelease->directory_path.'/manifest.json';
        $firstHtml = $storage->get($firstHtmlPath);
        $firstManifest = $storage->get($firstManifestPath);
        $aliasDocument = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'publish_alias_root',
                'type' => 'Section',
                'props' => [],
                'children' => [[
                    'id' => 'publish_alias_text',
                    'type' => 'Rich Text',
                    'props' => ['content' => '<p onclick="alert(1)">Canonical release</p><script>alert(2)</script>'],
                    'children' => [],
                ]],
            ],
        ];
        $page->update(['document_json' => $aliasDocument]);

        $secondRelease = $this->publishingService->publish($page->fresh(), $user);

        $publishedRevision = PageRevision::findOrFail($secondRelease->page_revision_id);
        $this->assertSame('RichText', $publishedRevision->document_json['root']['children'][0]['type']);
        $this->assertStringContainsString('Canonical release', $publishedRevision->document_json['root']['children'][0]['props']['content']);
        $this->assertStringNotContainsString('onclick', $publishedRevision->document_json['root']['children'][0]['props']['content']);
        $this->assertStringNotContainsString('<script', $publishedRevision->document_json['root']['children'][0]['props']['content']);
        $this->assertSame(PageRevision::canonicalizeDocument($publishedRevision->document_json), $publishedRevision->document_hash);

        $secondHtml = $storage->get($secondRelease->directory_path.'/index.html');
        $this->assertStringContainsString('Canonical release', $secondHtml);
        $this->assertStringNotContainsString('onclick', $secondHtml);
        $this->assertStringNotContainsString('alert(2)', $secondHtml);
        $this->assertSame($firstHtml, $storage->get($firstHtmlPath));
        $this->assertSame($firstManifest, $storage->get($firstManifestPath));
        $this->assertSame('Rich Text', $page->fresh()->document_json['root']['children'][0]['type']);
    }

    public function test_publish_fails_closed_when_document_is_null_or_invalid(): void
    {
        [$page, $user, $website] = $this->createPageWithDraft();
        $page->update(['document_json' => null]);

        $this->expectException(PublishFailedException::class);
        $this->publishingService->publish($page, $user);
    }

    public function test_publish_rejects_invalid_non_null_document_without_creating_release(): void
    {
        [$page, $user] = $this->createPageWithDraft();
        $page->update([
            'document_json' => [
                'schemaVersion' => 1,
                'root' => [
                    'id' => 'invalid_publish_root',
                    'type' => 'Unknown Widget',
                    'props' => [],
                    'children' => [],
                ],
            ],
        ]);

        try {
            $this->publishingService->publish($page->fresh(), $user);
            $this->fail('Publishing an unknown component type should fail closed.');
        } catch (PublishValidationException) {
            $this->assertDatabaseMissing('page_releases', ['page_id' => $page->id]);
            $this->assertDatabaseMissing('page_revisions', ['page_id' => $page->id]);
        }
    }

    public function test_publish_fails_closed_when_referenced_media_asset_is_unavailable(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        $media = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'status' => MediaAssetStatus::PENDING_DELETION,
            'storage_path' => "media/{$website->id}/test-image.jpg",
        ]);

        $docWithMedia = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_section',
                'type' => 'Section',
                'props' => ['padding' => 'medium'],
                'children' => [
                    [
                        'id' => 'img_1',
                        'type' => 'Image',
                        'props' => [
                            'src' => "storage/media/{$website->id}/test-image.jpg",
                            'alt' => 'Unavailable Image',
                        ],
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Page with Unavailable Media',
                'description' => 'Test Desc',
            ],
        ];

        $page = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'document_json' => $docWithMedia,
            'status' => 'draft',
        ]);

        $this->expectException(MediaUnavailableException::class);
        $this->publishingService->publish($page, $user);
    }

    public function test_rollback_case_a_instant_pointer_switch_when_files_verified(): void
    {
        [$page, $user, $website] = $this->createPageWithDraft($this->createValidDocument('Release 1'));
        $release1 = $this->publishingService->publish($page, $user);

        // Publish release 2
        $page->update(['document_json' => $this->createValidDocument('Release 2')]);
        $release2 = $this->publishingService->publish($page, $user);

        $this->assertEquals(PageReleaseStatus::ACTIVE, $release2->fresh()->status);
        $this->assertEquals(PageReleaseStatus::SUPERSEDED, $release1->fresh()->status);

        // Editor alters working draft
        $page->update(['document_json' => $this->createValidDocument('Work in Progress Draft')]);
        $draftBeforeRollback = $page->fresh()->document_json;

        // Rollback to Release 1
        $result = $this->publishingService->rollbackToRelease($page, $release1, $user);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['instant']);
        $this->assertFalse($result['recompiled']);

        // Check active release switched to release 1
        $this->assertEquals(PageReleaseStatus::ACTIVE, $release1->fresh()->status);
        $this->assertEquals(PageReleaseStatus::SUPERSEDED, $release2->fresh()->status);
        $this->assertEquals($release1->page_revision_id, $page->fresh()->published_revision_id);

        // INVARIANT D-033: Draft content in pages.document_json was NOT overwritten!
        $this->assertEquals($draftBeforeRollback, $page->fresh()->document_json);
    }

    public function test_rollback_case_b_recompiles_when_disk_files_are_pruned(): void
    {
        [$page, $user, $website] = $this->createPageWithDraft($this->createValidDocument('Release 1 Content'));
        $release1 = $this->publishingService->publish($page, $user);

        // Publish release 2
        $page->update(['document_json' => $this->createValidDocument('Release 2 Content')]);
        $release2 = $this->publishingService->publish($page, $user);

        // Simulate pruning release 1 directory from disk
        $storage = Storage::disk('local');
        $storage->deleteDirectory($release1->directory_path);
        $this->assertFalse($storage->exists($release1->directory_path.'/index.html'));

        // Rollback to pruned Release 1
        $result = $this->publishingService->rollbackToRelease($page, $release1, $user);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['instant']);
        $this->assertTrue($result['recompiled']);

        $newActiveRelease = $result['release'];
        $this->assertEquals(PageReleaseStatus::ACTIVE, $newActiveRelease->status);
        $this->assertEquals($release1->page_revision_id, $newActiveRelease->page_revision_id);
        $this->assertTrue($storage->exists($newActiveRelease->directory_path.'/index.html'));

        // Page published_revision_id points to release 1's revision
        $this->assertEquals($release1->page_revision_id, $page->fresh()->published_revision_id);
    }
}
