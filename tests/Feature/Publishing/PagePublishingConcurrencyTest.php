<?php

namespace Tests\Feature\Publishing;

use App\Domains\Media\Services\MediaUsageScanner;
use App\Domains\Publishing\Exceptions\MediaUnavailableException;
use App\Domains\Publishing\Exceptions\PublishConcurrencyException;
use App\Domains\Publishing\Services\PagePublishingLock;
use App\Domains\Publishing\Services\PagePublishingService;
use App\Enums\MediaAssetStatus;
use App\Enums\PageReleaseStatus;
use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class PagePublishingConcurrencyTest extends TestCase
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

        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::connection('pgsql')->table('page_releases')->delete();
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
                'id' => 'root_sec_concurrency',
                'type' => 'Section',
                'props' => ['padding' => 'medium'],
                'children' => [
                    [
                        'id' => 'img_concurrency_1',
                        'type' => 'Image',
                        'props' => [
                            'src' => "storage/{$media->storage_path}",
                            'alt' => 'Concurrency Asset',
                        ],
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Concurrency Page',
                'description' => 'Test Desc',
            ],
        ];
    }

    private function validDocument(string $heading = 'Concurrent Heading'): array
    {
        return [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_concurrency',
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
                                'props' => ['text' => $heading, 'level' => 1],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Concurrency Test Page',
                'description' => 'Test Desc',
            ],
        ];
    }

    private function createFixtures(?array $customDoc = null): array
    {
        $user = User::on('pgsql')->forceCreate([
            'name' => 'Publish Concurrency Tester',
            'email' => 'concurrency_pub_'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $website = Website::on('pgsql')->forceCreate([
            'name' => 'Concurrency Pub Site',
            'slug' => 'concurrency-pub-'.uniqid(),
            'owner_id' => $user->id,
            'status' => 'active',
        ]);

        WebsiteMembership::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $doc = $customDoc ?? $this->validDocument();

        $page = Page::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'title' => 'Concurrency Publish Page',
            'slug' => 'concurrency-page-'.uniqid(),
            'status' => PageStatus::DRAFT,
            'document_json' => $doc,
            'lock_version' => 1,
            'created_by' => $user->id,
        ]);

        return [$website, $page, $user];
    }

    /**
     * Scenario 1: The page operation lock spans the entire callback, including
     * render/filesystem work performed outside database transactions.
     */
    public function test_public_state_operation_lock_spans_callback_and_releases_after_completion(): void
    {
        [$website, $page, $user] = $this->createFixtures();
        $lockService = app(PagePublishingLock::class);

        $result = $lockService->runExclusive($page->id, function () use ($lockService, $page): string {
            try {
                $lockService->runExclusive($page->id, fn (): string => 'unexpected', 'pgsql_worker_2');
                $this->fail('A concurrent operation should be rejected while the callback is running.');
            } catch (PublishConcurrencyException) {
                $this->assertTrue(true);
            }

            return 'completed';
        }, 'pgsql');

        $this->assertSame('completed', $result);
        $this->assertSame(
            'next operation',
            $lockService->runExclusive($page->id, fn (): string => 'next operation', 'pgsql_worker_2'),
            'The session lock must be released when the callback completes.',
        );
    }

    public function test_public_state_operation_lock_releases_after_callback_failure(): void
    {
        [$website, $page, $user] = $this->createFixtures();
        $lockService = app(PagePublishingLock::class);

        try {
            $lockService->runExclusive($page->id, function (): never {
                throw new \RuntimeException('simulated operation failure');
            }, 'pgsql');
            $this->fail('The simulated failure should escape the callback.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated operation failure', $e->getMessage());
        }

        $this->assertSame(
            'recovered',
            $lockService->runExclusive($page->id, fn (): string => 'recovered', 'pgsql_worker_2'),
        );
    }

    /**
     * Scenario 2: Single active release invariant enforced at PostgreSQL engine level.
     */
    public function test_postgresql_partial_unique_index_strictly_prevents_multiple_active_releases(): void
    {
        [$website, $page, $user] = $this->createFixtures();

        $rev1 = PageRevision::on('pgsql')->forceCreate([
            'page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => 1,
            'document_json' => $page->document_json,
            'document_hash' => PageRevision::canonicalizeDocument($page->document_json),
            'change_summary' => 'Initial',
            'created_by' => $user->id,
        ]);

        // Insert first active release
        PageRelease::on('pgsql')->forceCreate([
            'id' => (string) Str::uuid(),
            'page_id' => $page->id,
            'page_revision_id' => $rev1->id,
            'release_number' => 1,
            'status' => PageReleaseStatus::ACTIVE,
            'schema_version' => 1,
            'renderer_version' => 1,
        ]);

        // Attempt to insert second active release for same page directly into PostgreSQL
        $this->expectException(QueryException::class);

        PageRelease::on('pgsql')->forceCreate([
            'id' => (string) Str::uuid(),
            'page_id' => $page->id,
            'page_revision_id' => $rev1->id,
            'release_number' => 2,
            'status' => PageReleaseStatus::ACTIVE, // Conflict with partial unique index!
            'schema_version' => 1,
            'renderer_version' => 1,
        ]);
    }

    /**
     * Scenario 3: Continuous media protection (D-030/D-034): Target revision protects media during publish.
     */
    public function test_target_revision_protects_media_from_concurrent_scanner(): void
    {
        $user = User::on('pgsql')->forceCreate([
            'name' => 'Media Concurrency Tester',
            'email' => 'med_concur_'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $website = Website::on('pgsql')->forceCreate([
            'name' => 'Media Site',
            'slug' => 'media-site-'.uniqid(),
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
            'storage_path' => "media/{$website->id}/photo.jpg",
            'original_filename' => 'photo.jpg',
            'status' => MediaAssetStatus::ACTIVE,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'width' => 800,
            'height' => 600,
            'uploaded_by' => $user->id,
        ]);

        $docWithMedia = $this->validDocumentWithMedia($media);

        $page = Page::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'title' => 'Page With Media',
            'slug' => 'page-with-media-'.uniqid(),
            'status' => PageStatus::DRAFT,
            'document_json' => $docWithMedia,
            'lock_version' => 1,
            'created_by' => $user->id,
        ]);

        // Publish service creates target revision R_target
        $publishingService = app(PagePublishingService::class);
        $release = $publishingService->publish($page, $user);

        $this->assertEquals(PageReleaseStatus::ACTIVE, $release->status);

        // While release is active, run MediaUsageScanner
        $scanner = app(MediaUsageScanner::class);
        $usages = $scanner->findUsagesInWebsite($website, $media);

        $this->assertNotEmpty($usages, 'Media should be detected in use via page_revisions');
        $this->assertEquals($page->id, $usages[0]['id']);
    }

    /**
     * Scenario 4: Publish pre-flight aborts if media is purging.
     */
    public function test_publish_aborts_when_referenced_media_is_purging(): void
    {
        $user = User::on('pgsql')->forceCreate([
            'name' => 'Purging Tester',
            'email' => 'purging_'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $website = Website::on('pgsql')->forceCreate([
            'name' => 'Purging Site',
            'slug' => 'purging-site-'.uniqid(),
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
            'storage_path' => "media/{$website->id}/purging.jpg",
            'original_filename' => 'purging.jpg',
            'status' => MediaAssetStatus::PURGING, // Undergoing purge
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'width' => 800,
            'height' => 600,
            'uploaded_by' => $user->id,
        ]);

        $page = Page::on('pgsql')->forceCreate([
            'website_id' => $website->id,
            'title' => 'Purging Test Page',
            'slug' => 'purging-page-'.uniqid(),
            'status' => PageStatus::DRAFT,
            'document_json' => $this->validDocumentWithMedia($media),
            'lock_version' => 1,
            'created_by' => $user->id,
        ]);

        $publishingService = app(PagePublishingService::class);

        $this->expectException(MediaUnavailableException::class);
        $publishingService->publish($page, $user);
    }
}
