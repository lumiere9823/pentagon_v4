<?php

namespace Tests\Feature\Templates;

use App\Domains\Media\Services\MediaUsageScanner;
use App\Enums\MediaAssetStatus;
use App\Enums\WebsiteRole;
use App\Models\MediaAsset;
use App\Models\PageTemplate;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateMediaOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsiteWithMembers(): array
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        return [$website, $owner];
    }

    private function documentWithImage(string $src): array
    {
        return [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_img',
                'type' => 'Section',
                'props' => [
                    'padding' => 'medium',
                    'background' => 'transparent',
                ],
                'children' => [
                    [
                        'id' => 'node_img_test',
                        'type' => 'Image',
                        'props' => [
                            'src' => $src,
                            'alt' => 'Test Image',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_custom_template_can_reference_active_media_asset_owned_by_same_website(): void
    {
        Storage::fake('public');
        [$website, $owner] = $this->createWebsiteWithMembers();

        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'status' => MediaAssetStatus::ACTIVE,
            'storage_path' => "media/{$website->id}/hero.png",
            'deleted_at' => null,
        ]);

        $response = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template With Active Image',
            'document_json' => $this->documentWithImage("/storage/{$asset->storage_path}"),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Template With Active Image');

        // Confirm reference is detected by MediaUsageScanner
        $scanner = app(MediaUsageScanner::class);
        $usages = $scanner->findUsagesInWebsite($website, $asset);
        $this->assertNotEmpty($usages);
        $this->assertEquals('confirmed', $usages[0]['reference_type']);
    }

    public function test_custom_template_referencing_another_websites_media_asset_is_rejected(): void
    {
        Storage::fake('public');
        [$websiteA, $ownerA] = $this->createWebsiteWithMembers();
        [$websiteB] = $this->createWebsiteWithMembers();

        // Asset owned by Website B
        $assetB = MediaAsset::factory()->create([
            'website_id' => $websiteB->id,
            'status' => MediaAssetStatus::ACTIVE,
            'storage_path' => "media/{$websiteB->id}/secret.png",
        ]);

        // Website A owner attempts to reference Website B's asset
        $response = $this->actingAs($ownerA)->postJson(route('websites.templates.store', $websiteA), [
            'name' => 'Template Referencing Foreign Asset',
            'document_json' => $this->documentWithImage("/storage/{$assetB->storage_path}"),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_asset_unavailable')
            ->assertJsonPath('message', 'One or more media assets referenced in this template are unavailable or invalid.');

        $this->assertDatabaseMissing('page_templates', [
            'name' => 'Template Referencing Foreign Asset',
        ]);
    }

    public function test_custom_template_referencing_nonexistent_local_media_is_rejected(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $response = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template With Missing Image',
            'document_json' => $this->documentWithImage("/storage/media/{$website->id}/ghost.png"),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_asset_unavailable');
    }

    public function test_custom_template_referencing_soft_deleted_asset_is_rejected(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $trashedAsset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'status' => MediaAssetStatus::ACTIVE,
            'storage_path' => "media/{$website->id}/trashed.png",
            'deleted_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template With Trashed Image',
            'document_json' => $this->documentWithImage("/storage/{$trashedAsset->storage_path}"),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_asset_unavailable');
    }

    public function test_custom_template_referencing_pending_deletion_or_purging_asset_is_rejected(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $pendingAsset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'status' => MediaAssetStatus::PENDING_DELETION,
            'storage_path' => "media/{$website->id}/pending.png",
            'deleted_at' => null,
        ]);

        $responsePending = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template With Pending Deletion Image',
            'document_json' => $this->documentWithImage("/storage/{$pendingAsset->storage_path}"),
        ]);

        $responsePending->assertStatus(422)
            ->assertJsonPath('error_code', 'media_asset_unavailable');

        $purgingAsset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'status' => MediaAssetStatus::PURGING,
            'storage_path' => "media/{$website->id}/purging.png",
            'deleted_at' => null,
        ]);

        $responsePurging = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template With Purging Image',
            'document_json' => $this->documentWithImage("/storage/{$purgingAsset->storage_path}"),
        ]);

        $responsePurging->assertStatus(422)
            ->assertJsonPath('error_code', 'media_asset_unavailable');
    }

    public function test_external_image_urls_are_accepted_and_bypass_local_media_lock(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $externalUrl = 'https://images.unsplash.com/photo-1579546929518-9e396f3cc809';

        $response = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template With External Unsplash Image',
            'document_json' => $this->documentWithImage($externalUrl),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Template With External Unsplash Image');
    }

    public function test_global_template_referencing_tenant_owned_media_is_detected_and_rejected(): void
    {
        [$website] = $this->createWebsiteWithMembers();

        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'storage_path' => "media/{$website->id}/tenant.png",
        ]);

        $scanner = app(MediaUsageScanner::class);

        // Inspect document with website = null (representing global scope)
        $inspection = $scanner->inspectAssetReferences(
            $this->documentWithImage("/storage/{$asset->storage_path}"),
            website: null
        );

        // Must flag foreign media because global templates cannot reference tenant storage!
        $this->assertTrue($inspection['has_foreign_media']);
        $this->assertContains($asset->id, $inspection['foreign_asset_ids']);
    }

    public function test_scanner_indeterminate_or_unparseable_document_blocks_update_safely(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        // Create a valid template first
        $template = PageTemplate::factory()->forWebsite($website)->create();

        // Directly corrupt the template document JSON in database to simulate indeterminate state
        $template->document_json = '{"invalid_json": true, truncated...';
        $template->saveQuietly();

        // Attempt to update the template
        $response = $this->actingAs($owner)->putJson(route('websites.templates.update', [$website, $template]), [
            'name' => 'New Name Attempt',
            'document_json' => $this->documentWithImage('https://example.com/valid.png'),
        ]);

        // Must abort with media_scan_indeterminate to protect old un-scannable references
        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'media_scan_indeterminate')
            ->assertJsonPath('message', 'Existing template media references cannot be scanned reliably.');
    }
}
