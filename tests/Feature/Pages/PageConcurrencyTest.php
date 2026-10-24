<?php

namespace Tests\Feature\Pages;

use App\Domains\Media\Services\MediaConcurrencyLock;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Domains\Pages\Services\PagePersistenceService;
use App\Enums\MediaAssetStatus;
use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class PageConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for advisory lock and concurrency verification.');
        }

        config([
            'database.connections.pgsql_worker_2' => config('database.connections.pgsql'),
        ]);

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::connection('pgsql')->table('page_revisions')->delete();
                DB::connection('pgsql')->table('pages')->delete();
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

    private function validDocumentWithMedia(MediaAsset $media): array
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
                        'id' => 'node_image_1',
                        'type' => 'Image',
                        'props' => [
                            'src' => "/storage/{$media->storage_path}",
                            'alt' => 'Concurrency Image',
                        ],
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Concurrency Page',
                'description' => '',
            ],
        ];
    }

    private function validDocumentWithoutMedia(string $text = 'Plain Content'): array
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
                'title' => 'Plain Page',
                'description' => '',
            ],
        ];
    }

    /**
     * @return array{Website, Page, User, MediaAsset}
     */
    private function createFixtures(): array
    {
        $user = User::on('pgsql')->forceCreate([
            'name' => 'Concurrency Tester',
            'email' => 'concurrency_'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $website = Website::on('pgsql')->forceCreate([
            'name' => 'Concurrency Site',
            'slug' => 'concurrency-'.uniqid(),
            'owner_id' => $user->id,
            'status' => 'active',
        ]);

        WebsiteMembership::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $media = MediaAsset::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'storage_disk' => 'public',
            'storage_path' => "media/{$website->id}/hero.jpg",
            'original_filename' => 'hero.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'status' => MediaAssetStatus::ACTIVE,
            'uploaded_by' => $user->id,
        ]);

        $page = Page::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'title' => 'Page 1',
            'slug' => 'page-'.uniqid(),
            'status' => PageStatus::DRAFT,
            'lock_version' => 1,
            'document_json' => $this->validDocumentWithoutMedia(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [$website, $page, $user, $media];
    }

    public function test_concurrent_saves_with_same_lock_version_exactly_one_succeeds_and_one_conflicts(): void
    {
        [$website, $page, $user] = $this->createFixtures();

        $persistenceService = app(PagePersistenceService::class);

        $docA = $this->validDocumentWithoutMedia('Author A Edits');
        $docB = $this->validDocumentWithoutMedia('Author B Edits');

        // Worker 1 saves first
        $result1 = $persistenceService->savePageContent(
            page: $page,
            website: $website,
            document: $docA,
            submittedLockVersion: 1,
            user: $user,
            changeSummary: 'Worker 1 save'
        );

        $this->assertTrue($result1['success']);
        $this->assertSame(200, $result1['status']);
        $this->assertSame(2, $result1['lock_version']);

        // Worker 2 attempts save with stale lock_version = 1
        $result2 = $persistenceService->savePageContent(
            page: $page,
            website: $website,
            document: $docB,
            submittedLockVersion: 1,
            user: $user,
            changeSummary: 'Worker 2 save'
        );

        $this->assertFalse($result2['success']);
        $this->assertSame(409, $result2['status']);
        $this->assertSame('document_conflict', $result2['error_code']);
        $this->assertSame(2, $result2['current_lock_version']);

        // Confirm database still contains Worker 1's content and lock_version = 2
        $page->refresh();
        $this->assertSame(2, $page->lock_version);
        $this->assertSame('Author A Edits', $page->document_json['root']['children'][0]['props']['text']);
    }

    public function test_media_referenced_in_historical_revision_is_protected_by_scanner(): void
    {
        [$website, $page, $user, $media] = $this->createFixtures();

        $persistenceService = app(PagePersistenceService::class);
        $scanner = app(MediaUsageScanner::class);

        // 1. Save document that references the media asset -> creates Revision #1
        $docWithMedia = $this->validDocumentWithMedia($media);
        $res1 = $persistenceService->savePageContent(
            page: $page,
            website: $website,
            document: $docWithMedia,
            submittedLockVersion: 1,
            user: $user,
            changeSummary: 'Added hero image',
            isAutosave: false
        );

        $this->assertTrue($res1['success']);
        $this->assertDatabaseCount('page_revisions', 1);

        // Scanner detects reference in both page and page_revisions
        $usages1 = $scanner->findUsagesInWebsite($website, $media);
        $this->assertNotEmpty($usages1);

        // 2. Overwrite current page document to REMOVE the media reference -> creates Revision #2
        $docWithoutMedia = $this->validDocumentWithoutMedia('Text only, no image');
        $res2 = $persistenceService->savePageContent(
            page: $page,
            website: $website,
            document: $docWithoutMedia,
            submittedLockVersion: 2,
            user: $user,
            changeSummary: 'Removed hero image from current canvas',
            isAutosave: false
        );

        $this->assertTrue($res2['success']);
        $this->assertDatabaseCount('page_revisions', 2);

        // Verify current page document does NOT have the media
        $page->refresh();
        $this->assertArrayNotHasKey('src', $page->document_json['root']['children'][0]['props']);

        // 3. INVARIANT CHECK (D-023 & Phase 07 M15):
        // Scanner MUST still report the asset as IN USE because Revision #1 retains it
        $usages2 = $scanner->findUsagesInWebsite($website, $media);
        $this->assertNotEmpty($usages2, 'MediaAsset referenced in historical revision must remain in-use and protected from pruning.');
        $this->assertSame($page->id, $usages2[0]['id']);
    }

    public function test_advisory_locking_serializes_page_save_and_media_status_check(): void
    {
        [$website, $page, $user, $media] = $this->createFixtures();

        $lockService = app(MediaConcurrencyLock::class);

        // Worker 2 takes advisory lock on the media asset in an uncommitted transaction
        $connWorker2 = DB::connection('pgsql_worker_2');
        $connWorker2->beginTransaction();

        $lockService->lockAsset($media->id, 'pgsql_worker_2');

        // Worker 2 marks media as PENDING_DELETION within its transaction
        $connWorker2->table('media_assets')
            ->where('id', $media->id)
            ->update([
                'status' => MediaAssetStatus::PENDING_DELETION->value,
                'deleted_at' => now(),
            ]);

        // Commit Worker 2's transaction which releases the advisory lock
        $connWorker2->commit();

        // Worker 1 now attempts to save a page document referencing the deleted asset
        $persistenceService = app(PagePersistenceService::class);
        $docWithMedia = $this->validDocumentWithMedia($media);

        $res = $persistenceService->savePageContent(
            page: $page,
            website: $website,
            document: $docWithMedia,
            submittedLockVersion: 1,
            user: $user,
            changeSummary: 'Attempted to link deleted asset'
        );

        // Must fail with 422 because asset is no longer active
        $this->assertFalse($res['success']);
        $this->assertSame(422, $res['status']);
        $this->assertSame('media_asset_unavailable', $res['error_code']);
    }

    public function test_transaction_rolls_back_and_releases_advisory_locks_when_revision_insert_fails(): void
    {
        [$website, $page, $user, $media] = $this->createFixtures();

        $persistenceService = app(PagePersistenceService::class);
        $lockService = app(MediaConcurrencyLock::class);

        $originalDoc = $page->document_json;
        $originalLockVersion = $page->lock_version;

        $newDoc = $this->validDocumentWithMedia($media);

        // Arrange for revision insertion to fail after the draft update has been executed in the transaction
        PageRevision::creating(function () {
            throw new \RuntimeException('Simulated catastrophic database failure during revision insert.');
        });

        $caughtException = null;
        try {
            $persistenceService->savePageContent(
                page: $page,
                website: $website,
                document: $newDoc,
                submittedLockVersion: $originalLockVersion,
                user: $user,
                changeSummary: 'Failing revision save',
                isAutosave: false
            );
        } catch (Throwable $e) {
            $caughtException = $e;
        } finally {
            // Clean up event listener
            PageRevision::flushEventListeners();
        }

        // 1. Verify exception was caught
        $this->assertNotNull($caughtException, 'Expected transaction exception was not thrown.');
        $this->assertStringContainsString('Simulated catastrophic database failure', $caughtException->getMessage());

        // 2. Verify Page draft update was rolled back
        $page->refresh();
        $this->assertSame($originalLockVersion, $page->lock_version, 'Page lock_version must not increment on rollback.');
        $this->assertEquals($originalDoc, $page->document_json, 'Page document_json must remain unchanged on rollback.');

        // 3. Verify no partial revision record was persisted
        $this->assertSame(0, PageRevision::where('page_id', $page->id)->count(), 'No revision must be persisted.');

        // 4. Verify transaction-scoped advisory locks on media assets were fully released by PostgreSQL
        $connWorker2 = DB::connection('pgsql_worker_2');
        $connWorker2->beginTransaction();
        $canWorker2AcquireLock = $lockService->tryLockAsset($media->id, 'pgsql_worker_2');
        $this->assertTrue($canWorker2AcquireLock, 'PostgreSQL transaction-scoped advisory lock must be released on rollback.');
        $connWorker2->rollBack();
    }
}
