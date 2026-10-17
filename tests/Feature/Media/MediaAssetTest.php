<?php

namespace Tests\Feature\Media;

use App\Domains\Media\Exceptions\MediaUsageScanException;
use App\Domains\Media\Services\MediaStorageService;
use App\Domains\Media\Services\MediaUsageScanner;
use App\Enums\WebsiteRole;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAssetTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsiteWithMembers(): array
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();

        $website = Website::factory()->create(['owner_id' => $owner->id]);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $editor->id,
            'role' => WebsiteRole::EDITOR,
        ]);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $viewer->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        return [$website, $owner, $editor, $viewer];
    }

    public function test_guests_cannot_access_media_endpoints(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        $asset = MediaAsset::factory()->create(['website_id' => $website->id]);

        $this->getJson(route('websites.media.index', $website))->assertUnauthorized();
        $this->postJson(route('websites.media.store', $website))->assertUnauthorized();
        $this->putJson(route('websites.media.update', [$website, $asset]))->assertUnauthorized();
        $this->deleteJson(route('websites.media.destroy', [$website, $asset]))->assertUnauthorized();
    }

    public function test_owner_and_editor_can_upload_valid_raster_images(): void
    {
        Storage::fake('public');
        [$website, $owner, $editor] = $this->createWebsiteWithMembers();

        // 1. Owner uploads PNG
        $pngFile = UploadedFile::fake()->image('hero.png', 400, 300);
        $response = $this->actingAs($owner)->postJson(route('websites.media.store', $website), [
            'file' => $pngFile,
            'alt_text' => 'Hero Banner',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.original_filename', 'hero.png')
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.width', 400)
            ->assertJsonPath('data.height', 300)
            ->assertJsonPath('data.alt_text', 'Hero Banner');

        $assetId = $response->json('data.id');
        $storedAsset = MediaAsset::findOrFail($assetId);
        $this->assertEquals($website->id, $storedAsset->website_id);
        Storage::disk('public')->assertExists($storedAsset->storage_path);

        // 2. Editor uploads JPEG
        $jpgFile = UploadedFile::fake()->image('thumbnail.jpg', 200, 200);
        $editorResponse = $this->actingAs($editor)->postJson(route('websites.media.store', $website), [
            'file' => $jpgFile,
        ]);

        $editorResponse->assertCreated()
            ->assertJsonPath('data.original_filename', 'thumbnail.jpg')
            ->assertJsonPath('data.mime_type', 'image/jpeg');

        // 3. GIF format
        $gifFile = UploadedFile::fake()->image('anim.gif', 100, 100);
        $gifResponse = $this->actingAs($owner)->postJson(route('websites.media.store', $website), [
            'file' => $gifFile,
        ]);
        $gifResponse->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/gif');
    }

    public function test_viewer_cannot_upload_update_or_delete_media(): void
    {
        Storage::fake('public');
        [$website, , , $viewer] = $this->createWebsiteWithMembers();
        $asset = MediaAsset::factory()->create(['website_id' => $website->id]);

        // Viewer cannot upload
        $file = UploadedFile::fake()->image('test.png', 100, 100);
        $this->actingAs($viewer)->postJson(route('websites.media.store', $website), [
            'file' => $file,
        ])->assertForbidden();

        // Viewer can list
        $this->actingAs($viewer)->getJson(route('websites.media.index', $website))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Viewer cannot update alt text
        $this->actingAs($viewer)->putJson(route('websites.media.update', [$website, $asset]), [
            'alt_text' => 'New text',
        ])->assertForbidden();

        // Viewer cannot delete
        $this->actingAs($viewer)->deleteJson(route('websites.media.destroy', [$website, $asset]))
            ->assertForbidden();
    }

    public function test_svg_uploads_are_strictly_rejected(): void
    {
        Storage::fake('public');
        [$website, $owner] = $this->createWebsiteWithMembers();

        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("XSS")</script></svg>';
        $svgFile = UploadedFile::fake()->createWithContent('vector.svg', $svgContent);

        $response = $this->actingAs($owner)->postJson(route('websites.media.store', $website), [
            'file' => $svgFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_spoofed_mime_and_malicious_files_are_rejected(): void
    {
        Storage::fake('public');
        [$website, $owner] = $this->createWebsiteWithMembers();

        // PHP script disguised with a .jpg extension
        $fakeJpg = UploadedFile::fake()->createWithContent('shell.jpg', '<?php phpinfo(); ?>');

        $response = $this->actingAs($owner)->postJson(route('websites.media.store', $website), [
            'file' => $fakeJpg,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_oversized_files_exceeding_5mb_are_rejected(): void
    {
        Storage::fake('public');
        [$website, $owner] = $this->createWebsiteWithMembers();

        // 5MB + 1KB
        $largeFile = UploadedFile::fake()->image('large.jpg', 100, 100)->size(5121);

        $response = $this->actingAs($owner)->postJson(route('websites.media.store', $website), [
            'file' => $largeFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_images_exceeding_maximum_dimension_limits_are_rejected(): void
    {
        Storage::fake('public');
        [$website, $owner] = $this->createWebsiteWithMembers();

        // Dimensions exceeding 4096px
        $giantImage = UploadedFile::fake()->image('giant.png', 4097, 1000);

        $response = $this->actingAs($owner)->postJson(route('websites.media.store', $website), [
            'file' => $giantImage,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_images_below_minimum_dimension_limits_are_rejected(): void
    {
        Storage::fake('public');
        [$website, $owner] = $this->createWebsiteWithMembers();

        // Dimensions below 16px
        $tinyImage = UploadedFile::fake()->image('tiny.png', 10, 10);

        $response = $this->actingAs($owner)->postJson(route('websites.media.store', $website), [
            'file' => $tinyImage,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_paginated_listing_with_search_and_mime_filtering(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        // Create 26 assets
        MediaAsset::factory()->create([
            'website_id' => $website->id,
            'original_filename' => 'special-logo.png',
            'mime_type' => 'image/png',
            'alt_text' => 'Brand logo',
        ]);

        MediaAsset::factory()->create([
            'website_id' => $website->id,
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Sunny beach',
        ]);

        MediaAsset::factory()->count(24)->create([
            'website_id' => $website->id,
            'mime_type' => 'image/webp',
        ]);

        // Default pagination: 24 per page
        $response = $this->actingAs($owner)->getJson(route('websites.media.index', $website));
        $response->assertOk()
            ->assertJsonCount(24, 'data')
            ->assertJsonPath('meta.per_page', 24)
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.last_page', 2);

        // Filter by search query (filename)
        $searchResponse = $this->actingAs($owner)->getJson(route('websites.media.index', [
            $website,
            'search' => 'special-logo',
        ]));
        $searchResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_filename', 'special-logo.png');

        // Filter by search query (alt text)
        $altSearchResponse = $this->actingAs($owner)->getJson(route('websites.media.index', [
            $website,
            'search' => 'Sunny beach',
        ]));
        $altSearchResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.alt_text', 'Sunny beach');

        // Filter by MIME type
        $mimeResponse = $this->actingAs($owner)->getJson(route('websites.media.index', [
            $website,
            'type' => 'image/jpeg',
        ]));
        $mimeResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mime_type', 'image/jpeg');
    }

    public function test_cross_website_idor_access_returns_404(): void
    {
        [$websiteA, $ownerA] = $this->createWebsiteWithMembers();
        [$websiteB] = $this->createWebsiteWithMembers();

        $assetB = MediaAsset::factory()->create(['website_id' => $websiteB->id]);

        // User A attempts to update or delete Website B's asset under Website A's URL
        $this->actingAs($ownerA)->putJson(route('websites.media.update', [$websiteA, $assetB]), [
            'alt_text' => 'Hacked',
        ])->assertNotFound();

        $this->actingAs($ownerA)->deleteJson(route('websites.media.destroy', [$websiteA, $assetB]))
            ->assertNotFound();
    }

    public function test_update_alt_text_persists_successfully(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();
        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'alt_text' => 'Old description',
        ]);

        $response = $this->actingAs($owner)->putJson(route('websites.media.update', [$website, $asset]), [
            'alt_text' => 'Updated accessible description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.alt_text', 'Updated accessible description');

        $this->assertEquals('Updated accessible description', $asset->fresh()->alt_text);
    }

    public function test_unreferenced_asset_can_be_deleted_and_unlinked(): void
    {
        Storage::fake('public');
        [$website, $owner] = $this->createWebsiteWithMembers();

        $file = UploadedFile::fake()->image('to-delete.png', 100, 100);
        $service = new MediaStorageService;
        $asset = $service->store($file, $website, $owner);

        Storage::disk('public')->assertExists($asset->storage_path);

        $response = $this->actingAs($owner)->deleteJson(route('websites.media.destroy', [$website, $asset]));
        $response->assertOk()
            ->assertJsonPath('success', true);

        // Assert soft deleted in database
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);

        // Physical file is retained during grace period to prevent TOCTOU race conditions
        Storage::disk('public')->assertExists($asset->storage_path);

        // When deferred cleanup command runs with --force, physical file is unlinked and record purged
        $this->artisan('media:prune-unreferenced', ['--force' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($asset->storage_path);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    public function test_reference_scanner_path_matching_is_exact_and_avoids_substring_collisions(): void
    {
        $scanner = new MediaUsageScanner;
        $asset = MediaAsset::factory()->make([
            'storage_path' => 'media/1/a1b2c3d4.jpg',
        ]);

        // Exact matches
        $this->assertTrue($scanner->isAssetReferencedInUrl('/storage/media/1/a1b2c3d4.jpg', $asset));
        $this->assertTrue($scanner->isAssetReferencedInUrl('media/1/a1b2c3d4.jpg', $asset));
        $this->assertTrue($scanner->isAssetReferencedInUrl('https://example.com/storage/media/1/a1b2c3d4.jpg', $asset));
        $this->assertTrue($scanner->isAssetReferencedInUrl('/storage/media/1/a1b2c3d4.jpg?v=1', $asset));

        // Substring collisions MUST return false
        $this->assertFalse($scanner->isAssetReferencedInUrl('/storage/media/1/a1b2c3d4-thumb.jpg', $asset));
        $this->assertFalse($scanner->isAssetReferencedInUrl('/storage/media/1/prefix-a1b2c3d4.jpg', $asset));
        $this->assertFalse($scanner->isAssetReferencedInUrl('/storage/media/2/a1b2c3d4.jpg', $asset));
        $this->assertFalse($scanner->isAssetReferencedInUrl('https://example.com/other/path/image.jpg', $asset));
    }

    public function test_reference_scanner_blocks_deletion_when_document_references_asset(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => 'media/1/referenced-asset.png',
        ]);

        $document = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'section_1',
                'type' => 'Section',
                'props' => [],
                'children' => [
                    [
                        'id' => 'image_1',
                        'type' => 'Image',
                        'props' => [
                            'src' => '/storage/media/1/referenced-asset.png',
                            'alt' => 'Hero image',
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $scanner = new MediaUsageScanner;
        $this->assertTrue($scanner->isAssetReferencedInDocument($document, $asset));

        // If mock scanner reports usage, destroy endpoint rejects with 422
        $mockScanner = $this->mock(MediaUsageScanner::class);
        $mockScanner->shouldReceive('findUsagesInWebsite')
            ->once()
            ->withArgs(fn ($w, $a) => $w->id === $website->id && $a->id === $asset->id)
            ->andReturn([
                ['id' => 42, 'title' => 'Landing Page', 'slug' => 'landing', 'reference_type' => 'confirmed'],
            ]);

        $response = $this->actingAs($owner)->deleteJson(route('websites.media.destroy', [$website, $asset]));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_in_use')
            ->assertJsonPath('referencing_pages.0.id', 42)
            ->assertJsonPath('referencing_pages.0.title', 'Landing Page');

        // Asset must remain in DB and not be soft deleted
        $this->assertNotSoftDeleted('media_assets', ['id' => $asset->id]);
    }

    public function test_reference_scanner_fails_closed_on_malformed_json_containing_filename(): void
    {
        $scanner = new MediaUsageScanner;
        $asset = MediaAsset::factory()->make([
            'storage_path' => 'media/1/corrupted-draft.png',
        ]);

        // Malformed JSON that includes the filename
        $malformedJsonWithFilename = '{"root": {"type": "Image", "props": {"src": "/storage/media/1/corrupted-draft.png"';

        // Must fail closed: indeterminate reference detected
        $inspection = $scanner->inspectDocumentReference($malformedJsonWithFilename, $asset);
        $this->assertTrue($inspection['referenced']);
        $this->assertTrue($inspection['indeterminate']);
        $this->assertTrue($scanner->isAssetReferencedInDocument($malformedJsonWithFilename, $asset));

        // When finding usages, this page is included with reference_type = indeterminate
        [$website, $owner] = $this->createWebsiteWithMembers();
        $dbAsset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => 'media/1/corrupted-draft.png',
        ]);

        $mockScanner = $this->mock(MediaUsageScanner::class);
        $mockScanner->shouldReceive('findUsagesInWebsite')
            ->once()
            ->andReturn([
                ['id' => 99, 'title' => 'Broken Draft', 'slug' => 'broken', 'reference_type' => 'indeterminate'],
            ]);

        $response = $this->actingAs($owner)->deleteJson(route('websites.media.destroy', [$website, $dbAsset]));
        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_in_use')
            ->assertJsonPath('referencing_pages.0.reference_type', 'indeterminate');

        $this->assertNotSoftDeleted('media_assets', ['id' => $dbAsset->id]);
    }

    public function test_reference_scanner_permits_deletion_on_malformed_json_without_filename(): void
    {
        $scanner = new MediaUsageScanner;
        $asset = MediaAsset::factory()->make([
            'storage_path' => 'media/1/safe-asset.png',
        ]);

        // Malformed JSON that has completely different content and does NOT contain safe-asset.png
        $malformedJsonWithoutFilename = '{"invalid json syntax: [1, 2, 3';

        $inspection = $scanner->inspectDocumentReference($malformedJsonWithoutFilename, $asset);
        $this->assertFalse($inspection['referenced']);
        $this->assertFalse($inspection['indeterminate']);
        $this->assertFalse($scanner->isAssetReferencedInDocument($malformedJsonWithoutFilename, $asset));
    }

    public function test_reference_scanner_fails_closed_on_invalid_utf8_containing_filename(): void
    {
        $scanner = new MediaUsageScanner;
        $asset = MediaAsset::factory()->make([
            'storage_path' => 'media/1/utf8-asset.png',
        ]);

        // Malformed byte sequence containing the target filename
        $invalidUtf8 = "{\"data\": \"media/1/utf8-asset.png\xC3\x28\"}";

        $inspection = $scanner->inspectDocumentReference($invalidUtf8, $asset);
        $this->assertTrue($inspection['referenced']);
        $this->assertTrue($inspection['indeterminate']);
    }

    public function test_destroy_endpoint_handles_scanner_exception_safely(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();
        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => 'media/1/scan-error.png',
        ]);

        $mockScanner = $this->mock(MediaUsageScanner::class);
        $mockScanner->shouldReceive('findUsagesInWebsite')
            ->once()
            ->andThrow(new MediaUsageScanException('Simulated database query failure'));

        $response = $this->actingAs($owner)->deleteJson(route('websites.media.destroy', [$website, $asset]));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_scan_error');

        // Asset must NOT be soft deleted or removed
        $this->assertNotSoftDeleted('media_assets', ['id' => $asset->id]);
    }

    public function test_prune_unreferenced_command_respects_grace_period(): void
    {
        Storage::fake('public');
        [$website] = $this->createWebsiteWithMembers();

        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => "media/{$website->id}/recent.png",
            'deleted_at' => now()->subHours(2), // Deleted 2 hours ago
        ]);
        Storage::disk('public')->put($asset->storage_path, 'image-content');

        // Run cleanup with default 24h grace period
        $this->artisan('media:prune-unreferenced', ['--hours' => 24])
            ->assertSuccessful();

        // Asset was within grace period: must NOT be pruned
        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
    }

    public function test_prune_unreferenced_command_cleans_up_after_grace_period(): void
    {
        Storage::fake('public');
        [$website] = $this->createWebsiteWithMembers();

        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => "media/{$website->id}/expired.png",
            'deleted_at' => now()->subHours(25), // Deleted 25 hours ago
        ]);
        Storage::disk('public')->put($asset->storage_path, 'image-content');

        // Run cleanup with 24h grace period
        $this->artisan('media:prune-unreferenced', ['--hours' => 24])
            ->assertSuccessful();

        // Asset has passed grace period and is unreferenced: must be unlinked and purged
        Storage::disk('public')->assertMissing($asset->storage_path);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    public function test_prune_unreferenced_command_retains_asset_if_referenced_at_cleanup_time(): void
    {
        Storage::fake('public');
        [$website] = $this->createWebsiteWithMembers();

        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => "media/{$website->id}/active.png",
            'deleted_at' => now()->subHours(25),
        ]);
        Storage::disk('public')->put($asset->storage_path, 'image-content');

        // Mock scanner reporting that a page acquired a reference to the asset during grace period
        $mockScanner = $this->mock(MediaUsageScanner::class);
        $mockScanner->shouldReceive('findUsagesInWebsite')
            ->once()
            ->andReturn([
                ['id' => 10, 'title' => 'Home', 'slug' => 'home', 'reference_type' => 'confirmed'],
            ]);

        $this->artisan('media:prune-unreferenced', ['--hours' => 24])
            ->assertSuccessful();

        // Asset must be retained on disk!
        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
    }

    public function test_prune_unreferenced_command_retains_asset_on_scan_error(): void
    {
        Storage::fake('public');
        [$website] = $this->createWebsiteWithMembers();

        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => "media/{$website->id}/error-case.png",
            'deleted_at' => now()->subHours(25),
        ]);
        Storage::disk('public')->put($asset->storage_path, 'image-content');

        $mockScanner = $this->mock(MediaUsageScanner::class);
        $mockScanner->shouldReceive('findUsagesInWebsite')
            ->once()
            ->andThrow(new MediaUsageScanException('Database connection failed during prune'));

        // Command fails or logs warning, but file MUST be retained
        $this->artisan('media:prune-unreferenced', ['--hours' => 24])
            ->assertFailed();

        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
    }

    public function test_prune_unreferenced_command_is_idempotent(): void
    {
        Storage::fake('public');
        [$website] = $this->createWebsiteWithMembers();

        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => "media/{$website->id}/idempotent.png",
            'deleted_at' => now()->subHours(25),
        ]);
        Storage::disk('public')->put($asset->storage_path, 'image-content');

        // Run 1: unlinks and purges
        $this->artisan('media:prune-unreferenced', ['--force' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($asset->storage_path);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);

        // Run 2: immediately runs again, clean exit
        $this->artisan('media:prune-unreferenced', ['--force' => true])
            ->assertSuccessful();
    }

    public function test_prune_unreferenced_command_rejects_paths_outside_website_directory(): void
    {
        Storage::fake('public');
        [$website] = $this->createWebsiteWithMembers();

        // Asset with path traversal or outside website dir
        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => 'media/999/malicious.png', // outside website 1
            'deleted_at' => now()->subHours(25),
        ]);
        Storage::disk('public')->put($asset->storage_path, 'image-content');

        $this->artisan('media:prune-unreferenced', ['--hours' => 24])
            ->assertFailed();

        // Must refuse to delete
        Storage::disk('public')->assertExists($asset->storage_path);
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
    }
}
