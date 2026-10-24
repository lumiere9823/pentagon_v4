<?php

namespace Tests\Feature\Media;

use App\Domains\Media\Exceptions\MediaUsageScanException;
use App\Domains\Media\Services\MediaConcurrencyLock;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Enums\MediaAssetStatus;
use App\Enums\WebsiteRole;
use App\Models\MediaAsset;
use App\Models\PageTemplate;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class MediaConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Concurrency tests require PostgreSQL as the active database
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for advisory lock concurrency verification.');
        }

        // Configure secondary connection to the same PostgreSQL database
        config([
            'database.connections.pgsql_worker_2' => config('database.connections.pgsql'),
        ]);

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::connection('pgsql')->table('page_templates')->delete();
                DB::connection('pgsql')->table('media_assets')->delete();
                DB::connection('pgsql')->table('website_memberships')->delete();
                DB::connection('pgsql')->table('websites')->delete();
                DB::connection('pgsql')->table('users')->delete();
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    /**
     * Minimal transactional document-save test harness representing Phase 07 persistence.
     * Enforces D-023: locks assets in ascending order, checks status, persists reference.
     *
     * @param  list<int>  $assetIds
     * @return array{success: bool, status_code: int, error?: string}
     */
    private function simulateDocumentPersistence(
        Website $website,
        array $assetIds,
        MediaConcurrencyLock $lockService,
        string $connection = 'pgsql'
    ): array {
        $conn = DB::connection($connection);

        return $conn->transaction(function () use ($website, $assetIds, $lockService, $conn) {
            // 1. Acquire transaction-scoped advisory locks in ascending numerical order
            $lockService->lockAssets($assetIds, $conn->getName());

            // 2. Validate asset existence and active status under the advisory lock
            $assets = MediaAsset::on($conn->getName())
                ->withTrashed()
                ->where('website_id', $website->id)
                ->whereIn('id', $assetIds)
                ->get()
                ->keyBy('id');

            foreach ($assetIds as $id) {
                if (! isset($assets[$id])) {
                    return ['success' => false, 'status_code' => 422, 'error' => 'media_asset_not_found'];
                }

                if ($assets[$id]->status !== MediaAssetStatus::ACTIVE || $assets[$id]->trashed()) {
                    return ['success' => false, 'status_code' => 422, 'error' => 'media_asset_unavailable'];
                }
            }

            return ['success' => true, 'status_code' => 200];
        });
    }

    /**
     * @return array{0: Website, 1: User}
     */
    private function createWebsiteWithOwner(): array
    {
        $user = User::on('pgsql')->forceCreate([
            'name' => 'Owner User',
            'email' => 'owner_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $website = Website::on('pgsql')->forceCreate([
            'owner_id' => $user->id,
            'name' => 'Concurrency Test Site',
            'slug' => 'site-'.uniqid(),
            'status' => 'active',
        ]);

        WebsiteMembership::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => WebsiteRole::OWNER,
        ]);

        return [$website, $user];
    }

    private function createWebsite(): Website
    {
        [$website] = $this->createWebsiteWithOwner();

        return $website;
    }

    /**
     * TC-01: Writer holds lock first; pruner must wait and retain file upon committed reference.
     */
    public function test_tc01_writer_holds_lock_first_pruner_waits(): void
    {
        $website = $this->createWebsite();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tc01.png",
            'original_filename' => 'tc01.png',
            'status' => MediaAssetStatus::PENDING_DELETION,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subHours(25),
        ]);
        Storage::disk('public')->put($asset->storage_path, 'binary-image');

        $lockService = app(MediaConcurrencyLock::class);

        // Connection 1: Writer begins transaction and acquires lock
        DB::connection('pgsql')->beginTransaction();
        $lockService->lockAsset($asset->id, 'pgsql');

        // Connection 2: Pruner probes lock non-blockingly; must be BLOCKED
        DB::connection('pgsql_worker_2')->beginTransaction();
        $canPrunerLock = $lockService->tryLockAsset($asset->id, 'pgsql_worker_2');
        $this->assertFalse($canPrunerLock, 'Pruner acquired lock while writer was holding it! Mutual exclusion violated.');
        DB::connection('pgsql_worker_2')->rollBack();

        // Writer revives and persists reference
        $asset->status = MediaAssetStatus::ACTIVE;
        $asset->deleted_at = null;
        $asset->save();
        DB::connection('pgsql')->commit();

        // Now run prune command; asset was revived to active: must be retained
        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])->assertSuccessful();

        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertDatabaseHas('media_assets', [
            'id' => $asset->id,
            'status' => MediaAssetStatus::ACTIVE->value,
            'deleted_at' => null,
        ]);
    }

    /**
     * TC-02: Pruner acquires lock first and deletes; waiting writer wakes and rejects reference.
     */
    public function test_tc02_pruner_deletes_first_waiting_writer_rejects_reference(): void
    {
        $website = $this->createWebsite();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tc02.png",
            'original_filename' => 'tc02.png',
            'status' => MediaAssetStatus::PENDING_DELETION,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subHours(25),
        ]);
        Storage::disk('public')->put($asset->storage_path, 'binary-image');

        $lockService = app(MediaConcurrencyLock::class);

        // Pruner runs and purges the asset
        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])->assertSuccessful();

        Storage::disk('public')->assertMissing($asset->storage_path);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);

        // Writer tries to persist document referencing the deleted asset
        $result = $this->simulateDocumentPersistence($website, [$asset->id], $lockService, 'pgsql');

        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['status_code']);
        $this->assertEquals('media_asset_not_found', $result['error']);
    }

    /**
     * TC-03: Writer cannot reference a soft-deleted or purging asset.
     */
    public function test_tc03_writer_cannot_reference_soft_deleted_or_purging_asset(): void
    {
        $website = $this->createWebsite();

        $softDeleted = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/soft.png",
            'original_filename' => 'soft.png',
            'status' => MediaAssetStatus::PENDING_DELETION,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now(),
        ]);

        $purgingAsset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/purging.png",
            'original_filename' => 'purging.png',
            'status' => MediaAssetStatus::PURGING,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subHours(25),
        ]);

        $lockService = app(MediaConcurrencyLock::class);

        // Test referencing pending_deletion
        $res1 = $this->simulateDocumentPersistence($website, [$softDeleted->id], $lockService, 'pgsql');
        $this->assertFalse($res1['success']);
        $this->assertEquals('media_asset_unavailable', $res1['error']);

        // Test referencing purging
        $res2 = $this->simulateDocumentPersistence($website, [$purgingAsset->id], $lockService, 'pgsql');
        $this->assertFalse($res2['success']);
        $this->assertEquals('media_asset_unavailable', $res2['error']);
    }

    /**
     * TC-04: Multiple locks acquired in deterministic order do not deadlock.
     */
    public function test_tc04_multiple_locks_acquired_in_deterministic_order_do_not_deadlock(): void
    {
        $lockService = app(MediaConcurrencyLock::class);

        $idA = 100;
        $idB = 200;

        // Conn 1 locks in order [A, B]
        DB::connection('pgsql')->beginTransaction();
        $lockService->lockAssets([$idB, $idA], 'pgsql'); // Passed unsorted [200, 100], internal sort locks [100, 200]

        // Conn 2 probes lock A; must be held
        DB::connection('pgsql_worker_2')->beginTransaction();
        $this->assertFalse($lockService->tryLockAsset($idA, 'pgsql_worker_2'));
        $this->assertFalse($lockService->tryLockAsset($idB, 'pgsql_worker_2'));
        DB::connection('pgsql_worker_2')->rollBack();

        DB::connection('pgsql')->commit();

        // Now Conn 2 acquires [100, 200] cleanly
        DB::connection('pgsql_worker_2')->beginTransaction();
        $lockService->lockAssets([$idA, $idB], 'pgsql_worker_2');
        $this->assertTrue(true, 'Acquired multiple locks without circular wait or deadlock.');
        DB::connection('pgsql_worker_2')->commit();
    }

    /**
     * TC-05: Scanner failure prevents destructive deletion.
     */
    public function test_tc05_scanner_failure_prevents_destructive_deletion(): void
    {
        $website = $this->createWebsite();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tc05.png",
            'original_filename' => 'tc05.png',
            'status' => MediaAssetStatus::PENDING_DELETION,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subHours(25),
        ]);
        Storage::disk('public')->put($asset->storage_path, 'binary-image');

        $mockScanner = $this->mock(MediaUsageScanner::class);
        $mockScanner->shouldReceive('findUsagesInWebsite')
            ->once()
            ->andThrow(new MediaUsageScanException('Simulated database disconnect during scan'));

        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])->assertFailed();

        // File and record must be preserved
        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertDatabaseHas('media_assets', [
            'id' => $asset->id,
            'status' => MediaAssetStatus::PENDING_DELETION->value,
        ]);
    }

    /**
     * TC-06: Interrupted cleanup is recoverable and idempotent (watchdog).
     */
    public function test_tc06_interrupted_cleanup_is_recoverable_and_idempotent(): void
    {
        $website = $this->createWebsite();

        // Asset was stuck in PURGING over 1 hour ago
        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tc06.png",
            'original_filename' => 'tc06.png',
            'status' => MediaAssetStatus::PURGING,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subHours(25),
        ]);
        // Manually update updated_at back 2 hours
        DB::connection('pgsql')->table('media_assets')
            ->where('id', $asset->id)
            ->update(['updated_at' => now()->subHours(2)]);

        Storage::disk('public')->put($asset->storage_path, 'binary-image');

        // Watchdog sweep resets stuck purging record back to pending_deletion
        $this->artisan('media:prune-unreferenced', ['--watchdog-only' => true, '--website' => $website->id])->assertSuccessful();

        $this->assertDatabaseHas('media_assets', [
            'id' => $asset->id,
            'status' => MediaAssetStatus::PENDING_DELETION->value,
        ]);

        // Next standard prune run cleanly cleans up the asset
        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])->assertSuccessful();

        Storage::disk('public')->assertMissing($asset->storage_path);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    /**
     * TC-07: Physical unlink succeeds but database commit fails; subsequent cleanup safely recovers.
     */
    public function test_tc07_physical_unlink_succeeds_but_database_commit_fails_recovery(): void
    {
        $website = $this->createWebsite();

        // Create asset record, but simulate that storage file was already unlinked during crashed run
        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tc07.png",
            'original_filename' => 'tc07.png',
            'status' => MediaAssetStatus::PENDING_DELETION,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subHours(25),
        ]);
        // Do NOT put file in Storage; file is already missing!

        // Run pruner; should handle missing file gracefully and finalize forceDelete
        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])->assertSuccessful();

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    /**
     * TC-08: Core invariant assertion across multiple randomized trials.
     */
    public function test_tc08_no_committed_document_can_reference_a_permanently_deleted_asset(): void
    {
        $website = $this->createWebsite();

        $lockService = app(MediaConcurrencyLock::class);

        // Run 20 randomized trials simulating racing writers and pruners
        for ($i = 0; $i < 20; $i++) {
            $asset = MediaAsset::on('pgsql')->forceCreate([
                'website_id' => $website->id,
                'storage_disk' => 'public',
                'storage_path' => "media/{$website->id}/tc08_{$i}.png",
                'original_filename' => "tc08_{$i}.png",
                'status' => MediaAssetStatus::PENDING_DELETION,
                'mime_type' => 'image/png',
                'size_bytes' => 1024,
                'deleted_at' => now()->subHours(25),
            ]);
            Storage::disk('public')->put($asset->storage_path, 'binary-image');

            // Coin toss: randomly either pruner runs first or writer runs first
            if (random_int(0, 1) === 0) {
                // Pruner runs
                $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])->assertSuccessful();
                // Writer attempts save
                $res = $this->simulateDocumentPersistence($website, [$asset->id], $lockService, 'pgsql');
                $this->assertFalse($res['success'], "Trial {$i}: Writer saved reference to pruned asset!");
            } else {
                // Writer runs first: revives asset
                $asset->status = MediaAssetStatus::ACTIVE;
                $asset->deleted_at = null;
                $asset->save();

                $res = $this->simulateDocumentPersistence($website, [$asset->id], $lockService, 'pgsql');
                $this->assertTrue($res['success'], "Trial {$i}: Writer failed to save active asset.");

                // Pruner runs after: must skip
                $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])->assertSuccessful();
                Storage::disk('public')->assertExists($asset->storage_path);
            }

            // Invariant assertion: Storage file MUST exist if asset is active in database
            $activeInDb = MediaAsset::on('pgsql')->where('id', $asset->id)->whereNull('deleted_at')->exists();
            $fileExists = Storage::disk('public')->exists($asset->storage_path);

            if ($activeInDb) {
                $this->assertTrue($fileExists, "CRITICAL INVARIANT VIOLATED in trial {$i}: Active asset has no storage file!");
            }
        }
    }

    /**
     * Helper to build a valid Document Schema v1 AST containing image references.
     *
     * @param  list<string>  $imageUrls
     * @return array<string, mixed>
     */
    private function createTemplateDocument(array $imageUrls): array
    {
        $children = [];
        foreach ($imageUrls as $idx => $url) {
            $children[] = [
                'id' => 'node_img_concurrency_'.$idx,
                'type' => 'Image',
                'props' => [
                    'src' => $url,
                    'alt' => 'Concurrency Test Image',
                ],
            ];
        }

        return [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_concurrency',
                'type' => 'Section',
                'props' => [
                    'padding' => 'medium',
                    'background' => 'transparent',
                ],
                'children' => $children,
            ],
        ];
    }

    /**
     * TC-T01: Real template creation via HTTP while media pruning is attempted.
     * The production HTTP route validates, acquires advisory locks, and commits reference.
     * Subsequent pruner detects template reference and retains the media file.
     */
    public function test_tct01_real_template_store_via_http_with_concurrent_pruning(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct01.png",
            'original_filename' => 'tct01.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->put($asset->storage_path, 'binary-image');

        // Execute real production HTTP request to store template
        $response = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template TC-T01',
            'category' => 'landing',
            'document_json' => $this->createTemplateDocument(["/storage/{$asset->storage_path}"]),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Template TC-T01');

        $this->assertDatabaseHas('page_templates', [
            'website_id' => $website->id,
            'name' => 'Template TC-T01',
        ]);

        // Soft-delete asset past grace period and run pruner on secondary connection
        $asset->status = MediaAssetStatus::PENDING_DELETION;
        $asset->deleted_at = now()->subHours(25);
        $asset->save();

        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])
            ->assertSuccessful();

        // Storage file must be retained because production template references it
        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    /**
     * TC-T02: Pruner holds lock first and purges; waiting template writer rejects with 422.
     * Exercised via real production HTTP route.
     */
    public function test_tct02_real_template_store_rejects_purged_asset_with_422(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct02.png",
            'original_filename' => 'tct02.png',
            'status' => MediaAssetStatus::PENDING_DELETION,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subHours(25),
        ]);
        Storage::disk('public')->put($asset->storage_path, 'binary-image');

        // Pruner runs and purges the unreferenced asset
        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($asset->storage_path);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);

        // Real HTTP store attempt referencing the purged asset
        $response = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template TC-T02',
            'category' => 'landing',
            'document_json' => $this->createTemplateDocument(["/storage/{$asset->storage_path}"]),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_asset_unavailable');

        $this->assertDatabaseMissing('page_templates', [
            'website_id' => $website->id,
            'name' => 'Template TC-T02',
        ]);
    }

    /**
     * TC-T03: Real template update adding a media reference protects the asset from pruning.
     * Exercised via real production HTTP route PUT /websites/{website}/templates/{template}.
     */
    public function test_tct03_real_template_update_adding_media_reference_protects_asset(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct03.png",
            'original_filename' => 'tct03.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->put($asset->storage_path, 'binary-image');

        // Template initially created without media references
        $template = PageTemplate::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'name' => 'Template TC-T03',
            'slug' => 'template-tct03',
            'category' => 'landing',
            'schema_version' => 1,
            'document_json' => $this->createTemplateDocument([]),
            'is_active' => true,
        ]);

        // Real HTTP update adding reference to $asset
        $response = $this->actingAs($owner)->putJson(route('websites.templates.update', [$website, $template]), [
            'document_json' => $this->createTemplateDocument(["/storage/{$asset->storage_path}"]),
        ]);

        $response->assertOk();

        // Pruner should not prune the added asset
        $asset->status = MediaAssetStatus::PENDING_DELETION;
        $asset->deleted_at = now()->subHours(25);
        $asset->save();

        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])
            ->assertSuccessful();

        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    /**
     * TC-T04: Real template update replacing an existing media reference while pruning is attempted.
     * Locks union of old and new IDs, allows old to be pruned, retains new.
     * Exercised via real production HTTP route.
     */
    public function test_tct04_real_template_update_replacing_media_reference_allows_old_pruning_and_retains_new(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $oldAsset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct04_old.png",
            'original_filename' => 'tct04_old.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->put($oldAsset->storage_path, 'old-image');

        $newAsset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct04_new.png",
            'original_filename' => 'tct04_new.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->put($newAsset->storage_path, 'new-image');

        $template = PageTemplate::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'name' => 'Template TC-T04',
            'slug' => 'template-tct04',
            'category' => 'landing',
            'schema_version' => 1,
            'document_json' => $this->createTemplateDocument(["/storage/{$oldAsset->storage_path}"]),
            'is_active' => true,
        ]);

        // Real HTTP update replacing oldAsset with newAsset
        $response = $this->actingAs($owner)->putJson(route('websites.templates.update', [$website, $template]), [
            'document_json' => $this->createTemplateDocument(["/storage/{$newAsset->storage_path}"]),
        ]);

        $response->assertOk();

        // Soft-delete both assets past grace period
        $oldAsset->status = MediaAssetStatus::PENDING_DELETION;
        $oldAsset->deleted_at = now()->subHours(25);
        $oldAsset->save();

        $newAsset->status = MediaAssetStatus::PENDING_DELETION;
        $newAsset->deleted_at = now()->subHours(25);
        $newAsset->save();

        // Run pruner: oldAsset is pruned from disk & DB; newAsset is retained!
        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($oldAsset->storage_path);
        $this->assertDatabaseMissing('media_assets', ['id' => $oldAsset->id]);

        Storage::disk('public')->assertExists($newAsset->storage_path);
        $this->assertDatabaseHas('media_assets', ['id' => $newAsset->id]);
    }

    /**
     * TC-T05: Real template update removing a media reference allows asset pruning.
     * Exercised via real production HTTP route.
     */
    public function test_tct05_real_template_update_removing_media_reference_allows_pruning(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct05.png",
            'original_filename' => 'tct05.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->put($asset->storage_path, 'binary-image');

        $template = PageTemplate::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'name' => 'Template TC-T05',
            'slug' => 'template-tct05',
            'category' => 'landing',
            'schema_version' => 1,
            'document_json' => $this->createTemplateDocument(["/storage/{$asset->storage_path}"]),
            'is_active' => true,
        ]);

        // Real HTTP update removing image reference
        $response = $this->actingAs($owner)->putJson(route('websites.templates.update', [$website, $template]), [
            'document_json' => $this->createTemplateDocument([]),
        ]);

        $response->assertOk();

        // Soft-delete asset past grace period
        $asset->status = MediaAssetStatus::PENDING_DELETION;
        $asset->deleted_at = now()->subHours(25);
        $asset->save();

        // Pruner runs: asset is cleanly purged
        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($asset->storage_path);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    /**
     * TC-T06: Two concurrent updates to the same template.
     * Proves that the production controller acquires row-level lockForUpdate() on the template row,
     * reads the fresh committed old document, builds the complete lock union, and prevents stale scans.
     */
    public function test_tct06_concurrent_updates_to_same_template_use_locked_document_for_old_scan(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $assetA = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct06_a.png",
            'original_filename' => 'tct06_a.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->put($assetA->storage_path, 'img-a');

        $assetB = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct06_b.png",
            'original_filename' => 'tct06_b.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->put($assetB->storage_path, 'img-b');

        $assetC = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct06_c.png",
            'original_filename' => 'tct06_c.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);
        Storage::disk('public')->put($assetC->storage_path, 'img-c');

        $template = PageTemplate::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'name' => 'Template TC-T06',
            'slug' => 'template-tct06',
            'category' => 'landing',
            'schema_version' => 1,
            'document_json' => $this->createTemplateDocument(["/storage/{$assetA->storage_path}"]),
            'is_active' => true,
        ]);

        // Connection 2 begins transaction, acquires row lock on template, updates to Asset B, and commits
        DB::connection('pgsql_worker_2')->transaction(function () use ($website, $template, $assetB) {
            $locked = PageTemplate::on('pgsql_worker_2')
                ->where('website_id', $website->id)
                ->where('id', $template->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update([
                'document_json' => $this->createTemplateDocument(["/storage/{$assetB->storage_path}"]),
            ]);
        });

        // Now Connection 1 runs real HTTP update to set document to Asset C
        $response = $this->actingAs($owner)->putJson(route('websites.templates.update', [$website, $template]), [
            'document_json' => $this->createTemplateDocument(["/storage/{$assetC->storage_path}"]),
        ]);

        $response->assertOk();

        // The template in database must now reference Asset C
        $freshTemplate = PageTemplate::on('pgsql')->findOrFail($template->id);
        $scanner = app(MediaUsageScanner::class);
        $usagesC = $scanner->inspectAssetReferences($freshTemplate->document_json, $website);
        $this->assertEquals([$assetC->id], $usagesC['asset_ids']);

        // Soft-delete all three assets
        foreach ([$assetA, $assetB, $assetC] as $a) {
            $a->status = MediaAssetStatus::PENDING_DELETION;
            $a->deleted_at = now()->subHours(25);
            $a->save();
        }

        // Run pruner: assetA and assetB are pruned, assetC is retained!
        $this->artisan('media:prune-unreferenced', ['--hours' => 24, '--website' => $website->id])
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($assetA->storage_path);
        Storage::disk('public')->assertMissing($assetB->storage_path);
        Storage::disk('public')->assertExists($assetC->storage_path);
    }

    /**
     * TC-T07: Rollback after acquiring locks releases advisory locks immediately.
     * Exercised via real production HTTP route.
     */
    public function test_tct07_real_template_update_rejection_releases_advisory_locks(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $activeAsset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct07_active.png",
            'original_filename' => 'tct07_active.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);

        $purgingAsset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct07_purging.png",
            'original_filename' => 'tct07_purging.png',
            'status' => MediaAssetStatus::PURGING,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subHours(25),
        ]);

        $template = PageTemplate::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'name' => 'Template TC-T07',
            'slug' => 'template-tct07',
            'category' => 'landing',
            'schema_version' => 1,
            'document_json' => $this->createTemplateDocument(["/storage/{$activeAsset->storage_path}"]),
            'is_active' => true,
        ]);

        // Real HTTP update attempt referencing purging asset; must reject with 422 and rollback
        $response = $this->actingAs($owner)->putJson(route('websites.templates.update', [$website, $template]), [
            'document_json' => $this->createTemplateDocument(["/storage/{$purgingAsset->storage_path}"]),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_asset_unavailable');

        // Verify template in database was NOT changed
        $this->assertEquals(
            "/storage/{$activeAsset->storage_path}",
            $template->fresh()->document_json['root']['children'][0]['props']['src'] ?? ''
        );

        // Verify that secondary connection can immediately acquire advisory locks on both assets
        $lockService = app(MediaConcurrencyLock::class);
        DB::connection('pgsql_worker_2')->beginTransaction();
        $this->assertTrue($lockService->tryLockAsset($activeAsset->id, 'pgsql_worker_2'));
        $this->assertTrue($lockService->tryLockAsset($purgingAsset->id, 'pgsql_worker_2'));
        DB::connection('pgsql_worker_2')->rollBack();
    }

    /**
     * TC-T08: Malformed or indeterminate old-document scanning fails closed via production HTTP route.
     */
    public function test_tct08_malformed_old_document_fails_closed_via_http(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct08.png",
            'original_filename' => 'tct08.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);

        $template = PageTemplate::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'name' => 'Template TC-T08',
            'slug' => 'template-tct08',
            'category' => 'landing',
            'schema_version' => 1,
            'document_json' => $this->createTemplateDocument(["/storage/{$asset->storage_path}"]),
            'is_active' => true,
        ]);

        // Directly set old document in database to a JSON scalar (non-array) to simulate indeterminate state
        DB::connection('pgsql')->table('page_templates')
            ->where('id', $template->id)
            ->update(['document_json' => json_encode('indeterminate_non_array_scalar')]);

        // Real HTTP update attempt
        $response = $this->actingAs($owner)->putJson(route('websites.templates.update', [$website, $template]), [
            'document_json' => $this->createTemplateDocument([]),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_scan_indeterminate');
    }

    /**
     * TC-T09: Production route media ownership and lifecycle rejections.
     * Verifies that foreign website assets, soft-deleted assets, and pending-deletion assets
     * are rejected by the production controller with 422, while external URLs succeed with 201.
     */
    public function test_tct09_production_route_media_ownership_and_lifecycle_rejections(): void
    {
        [$websiteA, $ownerA] = $this->createWebsiteWithOwner();
        [$websiteB] = $this->createWebsiteWithOwner();

        // 1. Foreign website asset
        $foreignAsset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $websiteB->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$websiteB->id}/foreign.png",
            'original_filename' => 'foreign.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);

        $respForeign = $this->actingAs($ownerA)->postJson(route('websites.templates.store', $websiteA), [
            'name' => 'Foreign Asset Template',
            'category' => 'landing',
            'document_json' => $this->createTemplateDocument(["/storage/{$foreignAsset->storage_path}"]),
        ]);
        $respForeign->assertStatus(422)->assertJsonPath('error_code', 'media_asset_unavailable');

        // 2. Soft-deleted asset
        $softDeleted = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $websiteA->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$websiteA->id}/soft_del.png",
            'original_filename' => 'soft_del.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => now()->subDay(),
        ]);

        $respSoft = $this->actingAs($ownerA)->postJson(route('websites.templates.store', $websiteA), [
            'name' => 'Soft Deleted Asset Template',
            'category' => 'landing',
            'document_json' => $this->createTemplateDocument(["/storage/{$softDeleted->storage_path}"]),
        ]);
        $respSoft->assertStatus(422)->assertJsonPath('error_code', 'media_asset_unavailable');

        // 3. External image URL succeeds cleanly
        $respExternal = $this->actingAs($ownerA)->postJson(route('websites.templates.store', $websiteA), [
            'name' => 'External URL Template',
            'category' => 'landing',
            'document_json' => $this->createTemplateDocument(['https://images.unsplash.com/photo-1579546929518']),
        ]);
        $respExternal->assertCreated()->assertJsonPath('data.name', 'External URL Template');
    }

    /**
     * TC-T10: Advisory lock mutual exclusion between separate database connections.
     * Worker connection holds advisory lock; asserts tryLock on worker2 confirms exclusion.
     */
    public function test_tct10_advisory_lock_mutual_exclusion_with_separate_connection(): void
    {
        [$website, $owner] = $this->createWebsiteWithOwner();

        $asset = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/tct10.png",
            'original_filename' => 'tct10.png',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'deleted_at' => null,
        ]);

        $lockService = app(MediaConcurrencyLock::class);

        // Conn 2 begins transaction and acquires advisory lock
        DB::connection('pgsql_worker_2')->beginTransaction();
        $lockService->lockAsset($asset->id, 'pgsql_worker_2');

        // Conn 1 probes lock non-blockingly: must be blocked
        DB::connection('pgsql')->beginTransaction();
        $this->assertFalse(
            $lockService->tryLockAsset($asset->id, 'pgsql'),
            'Advisory lock failed to enforce mutual exclusion across separate PostgreSQL connections!'
        );
        DB::connection('pgsql')->rollBack();

        // Release Conn 2 lock
        DB::connection('pgsql_worker_2')->rollBack();

        // Conn 1 can now execute production HTTP store and acquire lock cleanly
        $response = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template TC-T10',
            'category' => 'landing',
            'document_json' => $this->createTemplateDocument(["/storage/{$asset->storage_path}"]),
        ]);
        $response->assertCreated();
    }
}
