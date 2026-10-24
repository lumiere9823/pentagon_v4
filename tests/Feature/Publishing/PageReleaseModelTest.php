<?php

namespace Tests\Feature\Publishing;

use App\Enums\PageReleaseStatus;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageReleaseModelTest extends TestCase
{
    use RefreshDatabase;

    private function createPageWithRevision(): array
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);
        $page = Page::factory()->create(['website_id' => $website->id, 'created_by' => $user->id]);
        $revision = PageRevision::factory()->create(['page_id' => $page->id, 'created_by' => $user->id]);

        return [$page, $revision, $user];
    }

    /**
     * 1. Test creation with valid UUID, correct default attributes and values.
     */
    public function test_page_release_can_be_created_with_defaults_and_uuid_primary_key(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $release = PageRelease::create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 1,
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($release->id);
        $this->assertTrue(Str::isUuid($release->id));
        $this->assertEquals(PageReleaseStatus::PENDING, $release->status);
        $this->assertEquals(1, $release->schema_version);
        $this->assertEquals(1, $release->renderer_version);
        $this->assertNull($release->manifest_json);
        $this->assertNull($release->directory_path);
        $this->assertNull($release->error_message);
        $this->assertNull($release->activated_at);
        $this->assertNotNull($release->created_at);
        $this->assertNotNull($release->updated_at);
    }

    /**
     * 2. Test Page::releases() relationship orders by release_number desc.
     */
    public function test_page_releases_relationship_orders_by_release_number_desc(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $rel1 = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 1,
            'created_by' => $user->id,
        ]);

        $rel3 = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 3,
            'created_by' => $user->id,
        ]);

        $rel2 = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 2,
            'created_by' => $user->id,
        ]);

        $releases = $page->fresh()->releases;

        $this->assertCount(3, $releases);
        $this->assertEquals([3, 2, 1], $releases->pluck('release_number')->all());
        $this->assertEquals([$rel3->id, $rel2->id, $rel1->id], $releases->pluck('id')->all());
    }

    /**
     * 3. Test Page::activeRelease() returns the active release or null.
     */
    public function test_page_active_release_relationship_returns_active_release_or_null(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $this->assertNull($page->fresh()->activeRelease);

        $pendingRelease = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 1,
            'status' => PageReleaseStatus::PENDING,
        ]);

        $this->assertNull($page->fresh()->activeRelease);

        $activeRelease = PageRelease::factory()->active()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 2,
        ]);

        $loaded = $page->fresh()->activeRelease;
        $this->assertNotNull($loaded);
        $this->assertEquals($activeRelease->id, $loaded->id);
        $this->assertEquals(PageReleaseStatus::ACTIVE, $loaded->status);
    }

    /**
     * 4. Test PageRelease relationships to Page, PageRevision, and User creator.
     */
    public function test_page_release_relationships_to_page_revision_and_creator(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $release = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(Page::class, $release->page);
        $this->assertEquals($page->id, $release->page->id);

        $this->assertInstanceOf(PageRevision::class, $release->revision);
        $this->assertEquals($revision->id, $release->revision->id);

        $this->assertInstanceOf(User::class, $release->creator);
        $this->assertEquals($user->id, $release->creator->id);
    }

    /**
     * 5. Test enum and attribute casts.
     */
    public function test_page_release_enum_and_attribute_casts(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $manifestData = [
            'release_number' => 1,
            'schema_version' => 1,
            'html_hash' => 'hash123',
            'html_bytes' => 456,
            'media_asset_ids' => [1, 2],
        ];

        $now = now()->floorSeconds();

        $release = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'status' => PageReleaseStatus::ACTIVE,
            'release_number' => '5',
            'schema_version' => '1',
            'renderer_version' => '2',
            'manifest_json' => $manifestData,
            'activated_at' => $now,
        ]);

        $fresh = $release->fresh();

        $this->assertInstanceOf(PageReleaseStatus::class, $fresh->status);
        $this->assertSame(PageReleaseStatus::ACTIVE, $fresh->status);
        $this->assertIsInt($fresh->release_number);
        $this->assertSame(5, $fresh->release_number);
        $this->assertIsInt($fresh->schema_version);
        $this->assertSame(1, $fresh->schema_version);
        $this->assertIsInt($fresh->renderer_version);
        $this->assertSame(2, $fresh->renderer_version);
        $this->assertIsArray($fresh->manifest_json);
        $this->assertEquals($manifestData, $fresh->manifest_json);
        $this->assertInstanceOf(CarbonInterface::class, $fresh->activated_at);
        $this->assertEquals($now->timestamp, $fresh->activated_at->timestamp);
    }

    /**
     * 6. Test cascade delete on Page and PageRevision, and nullOnDelete on User creator.
     */
    public function test_cascade_delete_on_page_and_page_revision_and_null_on_delete_user(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        [$page, $revision, $owner] = $this->createPageWithRevision();
        $author = User::factory()->create();

        $release = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'created_by' => $author->id,
        ]);

        // Null on delete for user (author deleted, not owner of website)
        $author->delete();
        $this->assertNull($release->fresh()->created_by);

        // Cascade delete on page revision
        $revision->delete();
        $this->assertDatabaseMissing('page_releases', ['id' => $release->id]);

        // Recreate and test cascade delete on page
        $rev2 = PageRevision::factory()->create(['page_id' => $page->id]);
        $release2 = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $rev2->id,
        ]);

        $page->delete();
        $this->assertDatabaseMissing('page_releases', ['id' => $release2->id]);
    }

    /**
     * 7. Test partial unique index prevents two active releases on the same page.
     */
    public function test_partial_unique_index_prevents_multiple_active_releases_on_same_page(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        // First active release succeeds
        PageRelease::factory()->active()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 1,
            'created_by' => $user->id,
        ]);

        // Second active release on the exact same page MUST fail at database index level
        $this->expectException(QueryException::class);

        $driver = DB::connection()->getDriverName();
        try {
            PageRelease::factory()->active()->create([
                'page_id' => $page->id,
                'page_revision_id' => $revision->id,
                'release_number' => 2,
                'created_by' => $user->id,
            ]);
        } catch (QueryException $e) {
            if ($driver === 'pgsql') {
                // Assert PostgreSQL unique violation error code 23505
                $this->assertStringContainsString('23505', (string) $e->getCode());
            }

            throw $e;
        }
    }

    /**
     * 8. Test partial unique index allows different pages to each have their own active release.
     */
    public function test_partial_unique_index_allows_different_pages_to_have_independent_active_releases(): void
    {
        [$pageA, $revisionA, $userA] = $this->createPageWithRevision();
        [$pageB, $revisionB, $userB] = $this->createPageWithRevision();

        $releaseA = PageRelease::factory()->active()->create([
            'page_id' => $pageA->id,
            'page_revision_id' => $revisionA->id,
            'release_number' => 1,
            'created_by' => $userA->id,
        ]);

        $releaseB = PageRelease::factory()->active()->create([
            'page_id' => $pageB->id,
            'page_revision_id' => $revisionB->id,
            'release_number' => 1,
            'created_by' => $userB->id,
        ]);

        $this->assertEquals(PageReleaseStatus::ACTIVE, $releaseA->fresh()->status);
        $this->assertEquals(PageReleaseStatus::ACTIVE, $releaseB->fresh()->status);
        $this->assertNotEquals($releaseA->id, $releaseB->id);
    }

    /**
     * 9. Test multiple releases in non-active states (superseded, failed, pruned, pending) on the same page.
     */
    public function test_multiple_non_active_releases_allowed_on_same_page(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        $pending = PageRelease::factory()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 1,
            'status' => PageReleaseStatus::PENDING,
        ]);

        $superseded1 = PageRelease::factory()->superseded()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 2,
        ]);

        $superseded2 = PageRelease::factory()->superseded()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 3,
        ]);

        $failed = PageRelease::factory()->failed('Disk write error')->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 4,
        ]);

        $pruned = PageRelease::factory()->pruned()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 5,
        ]);

        $active = PageRelease::factory()->active()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
            'release_number' => 6,
        ]);

        $this->assertCount(6, $page->fresh()->releases);
        $this->assertEquals(PageReleaseStatus::FAILED, $failed->fresh()->status);
        $this->assertEquals('Disk write error', $failed->fresh()->error_message);
        $this->assertEquals(PageReleaseStatus::ACTIVE, $active->fresh()->status);
    }

    /**
     * 10. Test migration rollback and re-run cleanly without conflicting artifacts.
     */
    public function test_migration_rollback_and_re_run_cleanly(): void
    {
        [$page, $revision, $user] = $this->createPageWithRevision();

        PageRelease::factory()->active()->create([
            'page_id' => $page->id,
            'page_revision_id' => $revision->id,
        ]);

        // Roll back the page_releases migration
        $status = Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertEquals(0, $status);

        // Re-run migration
        $status = Artisan::call('migrate');
        $this->assertEquals(0, $status);

        // Verify clean recreation
        [$newPage, $newRevision, $newUser] = $this->createPageWithRevision();
        $newRelease = PageRelease::factory()->active()->create([
            'page_id' => $newPage->id,
            'page_revision_id' => $newRevision->id,
        ]);

        $this->assertNotNull($newRelease->id);
        $this->assertEquals(PageReleaseStatus::ACTIVE, $newRelease->fresh()->status);
    }
}
