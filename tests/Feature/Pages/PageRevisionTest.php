<?php

namespace Tests\Feature\Pages;

use App\Domains\Pages\Services\PagePersistenceService;
use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PageRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function validDocumentWithHeading(string $text = 'Hello World'): array
    {
        return [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_section_default',
                'type' => 'Section',
                'props' => [
                    'padding' => 'medium',
                    'background' => 'transparent',
                ],
                'children' => [
                    [
                        'id' => 'node_heading_1',
                        'type' => 'Heading',
                        'props' => [
                            'text' => $text,
                            'level' => 1,
                        ],
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'New Page',
                'description' => '',
            ],
        ];
    }

    private function createWebsiteWithPage(User $owner, ?array $document = null, int $lockVersion = 1): array
    {
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $page = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => PageStatus::DRAFT,
            'lock_version' => $lockVersion,
            'document_json' => $document ?? $this->validDocumentWithHeading('Initial Doc'),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        return [$website, $page];
    }

    public function test_manual_save_creates_revisions(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        // 1. First manual save
        $doc1 = $this->validDocumentWithHeading('Version 1');
        $res1 = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $doc1,
            'lock_version' => 1,
            'change_summary' => 'First save',
            'is_autosave' => false,
        ]);

        $res1->assertOk()
            ->assertJson([
                'success' => true,
                'lock_version' => 2,
                'revision_number' => 1,
            ]);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'revision_number' => 1,
            'change_summary' => 'First save',
            'is_autosave' => false,
        ]);

        // 2. Second manual save with different content
        $doc2 = $this->validDocumentWithHeading('Version 2');
        $res2 = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $doc2,
            'lock_version' => 2,
            'change_summary' => 'Second save',
            'is_autosave' => false,
        ]);

        $res2->assertOk()
            ->assertJson([
                'success' => true,
                'lock_version' => 3,
                'revision_number' => 2,
            ]);

        $this->assertDatabaseCount('page_revisions', 2);
    }

    public function test_identical_content_hash_deduplicates_and_does_not_create_new_revision(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $doc = $this->validDocumentWithHeading('Identical Content');

        // First save creates revision #1
        $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $doc,
            'lock_version' => 1,
            'is_autosave' => false,
        ]);

        $this->assertDatabaseCount('page_revisions', 1);

        // Second save with identical document
        $res2 = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $doc,
            'lock_version' => 2,
            'is_autosave' => false,
        ]);

        $res2->assertOk()
            ->assertJson([
                'success' => true,
                'lock_version' => 3,
            ]);

        // Revision count remains 1 due to hash deduplication (D-028)
        $this->assertDatabaseCount('page_revisions', 1);
    }

    public function test_autosave_within_milestone_interval_does_not_create_redundant_revision(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        Carbon::setTestNow('2026-10-18 10:00:00');

        // Create baseline revision #1
        $doc1 = $this->validDocumentWithHeading('Initial Content');
        $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $doc1,
            'lock_version' => 1,
            'is_autosave' => false,
        ]);

        $this->assertDatabaseCount('page_revisions', 1);

        // Autosave 2 minutes later with different content (under 5 minute milestone threshold)
        Carbon::setTestNow('2026-10-18 10:02:00');
        $doc2 = $this->validDocumentWithHeading('Draft autosave 2min later');
        $resAutosave = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $doc2,
            'lock_version' => 2,
            'is_autosave' => true,
        ]);

        $resAutosave->assertOk()
            ->assertJson([
                'success' => true,
                'lock_version' => 3,
            ]);

        // Page document_json is updated in real-time, but no new revision is created yet
        $page->refresh();
        $this->assertSame('Draft autosave 2min later', $page->document_json['root']['children'][0]['props']['text']);
        $this->assertDatabaseCount('page_revisions', 1);

        // Autosave 6 minutes later (exceeds 5 minute threshold) -> milestone revision created
        Carbon::setTestNow('2026-10-18 10:06:01');
        $doc3 = $this->validDocumentWithHeading('Draft milestone 6min later');
        $resMilestone = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $doc3,
            'lock_version' => 3,
            'is_autosave' => true,
        ]);

        $resMilestone->assertOk()
            ->assertJson([
                'success' => true,
                'lock_version' => 4,
                'revision_number' => 2,
            ]);

        $this->assertDatabaseCount('page_revisions', 2);
    }

    public function test_can_list_and_view_revisions(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $viewer->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        $doc1 = $this->validDocumentWithHeading('Revision 1');
        $doc2 = $this->validDocumentWithHeading('Revision 2');

        $rev1 = PageRevision::create([
            'page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => 1,
            'document_json' => $doc1,
            'document_hash' => PageRevision::canonicalizeDocument($doc1),
            'change_summary' => 'Initial Revision',
            'is_autosave' => false,
            'created_by' => $owner->id,
            'created_at' => now()->subMinutes(10),
        ]);

        $rev2 = PageRevision::create([
            'page_id' => $page->id,
            'revision_number' => 2,
            'schema_version' => 1,
            'document_json' => $doc2,
            'document_hash' => PageRevision::canonicalizeDocument($doc2),
            'change_summary' => 'Second Revision',
            'is_autosave' => false,
            'created_by' => $owner->id,
            'created_at' => now()->subMinutes(5),
        ]);

        // List revisions (Viewer permitted)
        $listResponse = $this->actingAs($viewer)->getJson(route('websites.pages.revisions.index', [$website, $page]));
        $listResponse->assertOk()
            ->assertJsonCount(2, 'revisions')
            ->assertJsonPath('revisions.0.revision_number', 2)
            ->assertJsonPath('revisions.1.revision_number', 1);

        // View single revision
        $showResponse = $this->actingAs($viewer)->getJson(route('websites.pages.revisions.show', [$website, $page, $rev1]));
        $showResponse->assertOk()
            ->assertJson([
                'revision' => [
                    'id' => $rev1->id,
                    'revision_number' => 1,
                    'document_json' => $doc1,
                ],
            ]);
    }

    public function test_rollback_creates_sequential_n_plus_one_revision_and_updates_page(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner, null, 3);

        $doc1 = $this->validDocumentWithHeading('Original Heading');
        $doc2 = $this->validDocumentWithHeading('Overwritten Heading');

        $rev1 = PageRevision::create([
            'page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => 1,
            'document_json' => $doc1,
            'document_hash' => PageRevision::canonicalizeDocument($doc1),
            'change_summary' => 'Revision 1',
            'is_autosave' => false,
            'created_by' => $owner->id,
            'created_at' => now()->subMinutes(15),
        ]);

        $rev2 = PageRevision::create([
            'page_id' => $page->id,
            'revision_number' => 2,
            'schema_version' => 1,
            'document_json' => $doc2,
            'document_hash' => PageRevision::canonicalizeDocument($doc2),
            'change_summary' => 'Revision 2',
            'is_autosave' => false,
            'created_by' => $owner->id,
            'created_at' => now()->subMinutes(5),
        ]);

        // Rollback to Revision #1
        $response = $this->actingAs($owner)->postJson(route('websites.pages.revisions.rollback', [$website, $page, $rev1]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'lock_version' => 4,
                'revision_number' => 3,
            ]);

        // Page document_json is now reverted to Revision #1
        $page->refresh();
        $this->assertSame(4, $page->lock_version);
        $this->assertSame('Original Heading', $page->document_json['root']['children'][0]['props']['text']);

        // Historical revisions 1 and 2 remain immutable and present
        $this->assertDatabaseCount('page_revisions', 3);
        $this->assertDatabaseHas('page_revisions', [
            'id' => $rev1->id,
            'revision_number' => 1,
        ]);
        $this->assertDatabaseHas('page_revisions', [
            'id' => $rev2->id,
            'revision_number' => 2,
        ]);
        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'revision_number' => 3,
            'change_summary' => 'Restored from Revision #1',
        ]);
    }

    public function test_viewer_cannot_rollback_revision(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $viewer->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        $doc1 = $this->validDocumentWithHeading('Rev 1');
        $rev1 = PageRevision::create([
            'page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => 1,
            'document_json' => $doc1,
            'document_hash' => PageRevision::canonicalizeDocument($doc1),
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($viewer)->postJson(route('websites.pages.revisions.rollback', [$website, $page, $rev1]));
        $response->assertForbidden();
    }

    public function test_page_draft_rolls_back_when_revision_insert_fails(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $persistenceService = app(PagePersistenceService::class);

        $originalDoc = $page->document_json;
        $originalVersion = $page->lock_version;

        $newDoc = $this->validDocumentWithHeading('Updated but failing');

        PageRevision::creating(function () {
            throw new \RuntimeException('Simulated revision creation failure');
        });

        $caught = false;
        try {
            $persistenceService->savePageContent(
                page: $page,
                website: $website,
                document: $newDoc,
                submittedLockVersion: $originalVersion,
                user: $owner
            );
        } catch (\Throwable $e) {
            $caught = true;
        } finally {
            PageRevision::flushEventListeners();
        }

        $this->assertTrue($caught);

        $page->refresh();
        $this->assertSame($originalVersion, $page->lock_version, 'Draft lock_version must not increment when revision insert fails.');
        $this->assertSame($originalDoc, $page->document_json, 'Draft document_json must be restored on rollback.');
        $this->assertSame(0, PageRevision::where('page_id', $page->id)->count(), 'No partial revision should be saved.');
    }
}
