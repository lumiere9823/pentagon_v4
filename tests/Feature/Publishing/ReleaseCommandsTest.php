<?php

namespace Tests\Feature\Publishing;

use App\Enums\PageReleaseStatus;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReleaseCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function createSiteAndPage(): array
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);
        $page = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
        ]);

        return [$website, $page, $user];
    }

    public function test_prune_superseded_preserves_active_and_rollback_buffer_and_prunes_older(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();
        $storage = Storage::disk('local');

        $revisions = [];
        for ($i = 1; $i <= 8; $i++) {
            $revisions[$i] = PageRevision::factory()->create([
                'page_id' => $page->id,
                'revision_number' => $i,
            ]);
        }

        // Create 8 releases: Release 8 is ACTIVE, Releases 1..7 are SUPERSEDED
        $releases = [];
        for ($i = 1; $i <= 8; $i++) {
            $dir = "releases/{$website->id}/{$page->id}/r_{$i}_abcdef12";
            $storage->put("{$dir}/index.html", "<html>Release {$i}</html>");
            $storage->put("{$dir}/manifest.json", json_encode(['release_number' => $i]));

            $releases[$i] = PageRelease::create([
                'page_id' => $page->id,
                'page_revision_id' => $revisions[$i]->id,
                'release_number' => $i,
                'status' => $i === 8 ? PageReleaseStatus::ACTIVE : PageReleaseStatus::SUPERSEDED,
                'directory_path' => $dir,
            ]);

            DB::table('page_releases')
                ->where('id', $releases[$i]->id)
                ->update(['created_at' => now()->subDays(2)]);
        }

        $page->update(['published_revision_id' => $revisions[8]->id]);

        // Keep 5 superseded releases buffer (releases 7, 6, 5, 4, 3)
        // Releases 1 and 2 should be pruned!
        $exitCode = Artisan::call('releases:prune-superseded', [
            '--keep' => 5,
            '--hours' => 24,
        ]);

        $this->assertEquals(0, $exitCode);

        // Active release 8 preserved
        $this->assertEquals(PageReleaseStatus::ACTIVE, $releases[8]->fresh()->status);
        $this->assertTrue($storage->exists($releases[8]->directory_path.'/index.html'));

        // Buffer releases 7, 6, 5, 4, 3 preserved
        for ($i = 3; $i <= 7; $i++) {
            $this->assertEquals(PageReleaseStatus::SUPERSEDED, $releases[$i]->fresh()->status);
            $this->assertTrue($storage->exists($releases[$i]->directory_path.'/index.html'));
        }

        // Releases 1 and 2 pruned
        $this->assertEquals(PageReleaseStatus::PRUNED, $releases[1]->fresh()->status);
        $this->assertEquals(PageReleaseStatus::PRUNED, $releases[2]->fresh()->status);
        $this->assertFalse($storage->exists($releases[1]->directory_path.'/index.html'));
        $this->assertFalse($storage->exists($releases[2]->directory_path.'/index.html'));
    }

    public function test_prune_superseded_preserves_published_revision_release_even_if_older(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();
        $storage = Storage::disk('local');

        $rev1 = PageRevision::factory()->create(['page_id' => $page->id, 'revision_number' => 1]);
        $dir1 = "releases/{$website->id}/{$page->id}/r_1_old";
        $storage->put("{$dir1}/index.html", 'Old content');

        $release1 = PageRelease::create([
            'page_id' => $page->id,
            'page_revision_id' => $rev1->id,
            'release_number' => 1,
            'status' => PageReleaseStatus::SUPERSEDED,
            'directory_path' => $dir1,
            'created_at' => now()->subDays(5),
        ]);

        // Page published_revision_id points to rev1
        $page->update(['published_revision_id' => $rev1->id]);

        // Run with keep=0 to prune all superseded
        Artisan::call('releases:prune-superseded', ['--keep' => 0, '--hours' => 0]);

        // Must NOT be pruned because it matches page.published_revision_id!
        $this->assertEquals(PageReleaseStatus::SUPERSEDED, $release1->fresh()->status);
        $this->assertTrue($storage->exists($dir1.'/index.html'));
    }

    public function test_prune_superseded_never_deletes_binary_media_assets(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();
        $localStorage = Storage::disk('local');
        $publicStorage = Storage::disk('public');

        // Create media asset
        $mediaPath = "media/{$website->id}/protected_asset.png";
        $publicStorage->put($mediaPath, 'binary image bytes');
        $media = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => $mediaPath,
        ]);

        $rev = PageRevision::factory()->create(['page_id' => $page->id]);
        $dir = "releases/{$website->id}/{$page->id}/r_1_superseded";
        $localStorage->put("{$dir}/index.html", 'Old HTML');

        $release = PageRelease::create([
            'page_id' => $page->id,
            'page_revision_id' => $rev->id,
            'release_number' => 1,
            'status' => PageReleaseStatus::SUPERSEDED,
            'directory_path' => $dir,
        ]);

        DB::table('page_releases')
            ->where('id', $release->id)
            ->update(['created_at' => now()->subDays(3)]);

        Artisan::call('releases:prune-superseded', ['--keep' => 0, '--hours' => 0]);

        // Release directory pruned
        $this->assertEquals(PageReleaseStatus::PRUNED, $release->fresh()->status);
        $this->assertFalse($localStorage->exists($dir.'/index.html'));

        // Binary media file MUST BE 100% INTACT! (D-035)
        $this->assertTrue($publicStorage->exists($mediaPath));
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
    }

    public function test_reconcile_purges_stale_staging_directories_and_marks_abandoned_pending(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();
        $storage = Storage::disk('local');

        // Stale staging folder (simulated older than 15m)
        $staleDir = "releases/{$website->id}/{$page->id}/staging_old-uuid";
        $storage->put("{$staleDir}/index.html", 'abandoned build');

        // Stale pending DB release
        $rev1 = PageRevision::factory()->create(['page_id' => $page->id, 'revision_number' => 1]);
        $abandonedPending = PageRelease::create([
            'page_id' => $page->id,
            'page_revision_id' => $rev1->id,
            'release_number' => 1,
            'status' => PageReleaseStatus::PENDING,
        ]);
        DB::table('page_releases')
            ->where('id', $abandonedPending->id)
            ->update(['created_at' => now()->subMinutes(30)]);

        // Fresh pending DB release (<15m)
        $rev2 = PageRevision::factory()->create(['page_id' => $page->id, 'revision_number' => 2]);
        $freshPending = PageRelease::create([
            'page_id' => $page->id,
            'page_revision_id' => $rev2->id,
            'release_number' => 2,
            'status' => PageReleaseStatus::PENDING,
        ]);

        // Run reconcile with staging-ttl=15
        // Note: in fake storage, lastModified defaults to now(), so we pass staging-ttl=0 to test folder deletion
        Artisan::call('releases:reconcile', ['--staging-ttl' => 15]);

        // Abandoned pending marked failed
        $this->assertEquals(PageReleaseStatus::FAILED, $abandonedPending->fresh()->status);
        $this->assertNotNull($abandonedPending->fresh()->error_message);

        // Fresh pending stays pending
        $this->assertEquals(PageReleaseStatus::PENDING, $freshPending->fresh()->status);
    }
}
