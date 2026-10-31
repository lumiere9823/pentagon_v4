<?php

namespace Tests\Feature\Publishing;

use App\Enums\MediaAssetStatus;
use App\Enums\PageReleaseStatus;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRelease;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagePublishingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createValidDocument(string $heading = 'Published Heading'): array
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
                                'props' => ['text' => $heading, 'level' => 1],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Sample Page',
                'description' => 'A valid test page',
            ],
        ];
    }

    private function createSiteAndPage(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $owner = $role === 'owner' ? $user : User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        if ($role !== 'owner') {
            WebsiteMembership::create([
                'website_id' => $website->id,
                'user_id' => $owner->id,
                'role' => 'owner',
            ]);
        }

        $page = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $owner->id,
            'document_json' => $this->createValidDocument(),
            'status' => 'draft',
        ]);

        return [$website, $page, $user];
    }

    public function test_owner_can_publish_page_successfully(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage('owner');

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.publish', [
                'website' => $website->id,
                'page' => $page->id,
            ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Page published successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'release' => ['id', 'status', 'release_number'],
                'public_url',
            ]);

        $this->assertDatabaseHas('page_releases', [
            'page_id' => $page->id,
            'release_number' => 1,
            'status' => PageReleaseStatus::ACTIVE->value,
        ]);
    }

    public function test_editor_can_publish_page(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage('editor');

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.publish', [
                'website' => $website->id,
                'page' => $page->id,
            ]));

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_viewer_cannot_publish_page(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage('viewer');

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.publish', [
                'website' => $website->id,
                'page' => $page->id,
            ]));

        $response->assertForbidden();
    }

    public function test_cross_website_publish_aborts_with_404_idor_prevention(): void
    {
        [$website1, $page1, $user] = $this->createSiteAndPage('owner');
        $website2 = Website::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.publish', [
                'website' => $website2->id,
                'page' => $page1->id,
            ]));

        $response->assertNotFound();
    }

    public function test_publish_fails_with_422_when_referenced_media_is_unavailable(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage('owner');

        MediaAsset::factory()->create([
            'website_id' => $website->id,
            'status' => MediaAssetStatus::PENDING_DELETION,
            'storage_path' => "media/{$website->id}/bad-img.jpg",
        ]);

        $badDoc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => ['padding' => 'medium'],
                'children' => [
                    [
                        'id' => 'img_node',
                        'type' => 'Image',
                        'props' => [
                            'src' => "storage/media/{$website->id}/bad-img.jpg",
                            'alt' => 'Bad Image',
                        ],
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => ['title' => 'Page', 'description' => 'Desc'],
        ];

        $page->update(['document_json' => $badDoc]);

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.publish', [
                'website' => $website->id,
                'page' => $page->id,
            ]));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'media_asset_unavailable',
            ]);
    }

    public function test_publish_rejects_empty_image_source_before_release_staging(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage('owner');
        $document = $this->createValidDocument();
        $document['root']['children'][] = [
            'id' => 'image_empty',
            'type' => 'Image',
            'props' => ['src' => '', 'alt' => 'Missing'],
            'children' => [],
        ];
        $page->update(['document_json' => $document]);

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.publish', [
                'website' => $website->id,
                'page' => $page->id,
            ]));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'validation_failed',
                'details' => [[
                    'path' => 'root.children[1].props.src',
                    'code' => 'empty_image_source',
                ]],
            ]);
        $this->assertDatabaseMissing('page_releases', ['page_id' => $page->id]);
    }

    public function test_releases_index_returns_list_of_page_releases(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage('owner');

        // Publish two releases
        $this->actingAs($user)->postJson(route('websites.pages.publish', ['website' => $website->id, 'page' => $page->id]))->assertOk();
        $page->update(['document_json' => $this->createValidDocument('Second Version')]);
        $this->actingAs($user)->postJson(route('websites.pages.publish', ['website' => $website->id, 'page' => $page->id]))->assertOk();

        $response = $this->actingAs($user)
            ->getJson(route('websites.pages.releases.index', [
                'website' => $website->id,
                'page' => $page->id,
            ]));

        $response->assertOk()
            ->assertJsonCount(2, 'releases')
            ->assertJsonPath('releases.0.release_number', 2)
            ->assertJsonPath('releases.1.release_number', 1);

        // Limit parameter bounds the returned list
        $limitResponse = $this->actingAs($user)
            ->getJson(route('websites.pages.releases.index', [
                'website' => $website->id,
                'page' => $page->id,
                'limit' => 1,
            ]));

        $limitResponse->assertOk()
            ->assertJsonCount(1, 'releases')
            ->assertJsonPath('releases.0.release_number', 2);
    }

    public function test_rollback_public_release_endpoint_succeeds(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage('owner');

        // Release 1
        $this->actingAs($user)->postJson(route('websites.pages.publish', ['website' => $website->id, 'page' => $page->id]))->assertOk();
        $release1 = PageRelease::where('page_id', $page->id)->first();

        // Release 2
        $page->update(['document_json' => $this->createValidDocument('Release 2')]);
        $this->actingAs($user)->postJson(route('websites.pages.publish', ['website' => $website->id, 'page' => $page->id]))->assertOk();

        // Rollback to Release 1
        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.releases.rollback', [
                'website' => $website->id,
                'page' => $page->id,
                'release' => $release1->id,
            ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals(PageReleaseStatus::ACTIVE, $release1->fresh()->status);
    }

    public function test_rollback_aborts_with_404_if_release_belongs_to_different_page(): void
    {
        [$website, $page1, $user] = $this->createSiteAndPage('owner');
        $page2 = Page::factory()->create(['website_id' => $website->id, 'created_by' => $user->id]);

        $this->actingAs($user)->postJson(route('websites.pages.publish', ['website' => $website->id, 'page' => $page1->id]))->assertOk();
        $release1 = PageRelease::where('page_id', $page1->id)->first();

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.releases.rollback', [
                'website' => $website->id,
                'page' => $page2->id,
                'release' => $release1->id,
            ]));

        $response->assertNotFound();
    }

    public function test_unpublish_transitions_published_page_to_draft_and_public_route_returns_404(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage('owner');

        // Publish page
        $this->actingAs($user)
            ->postJson(route('websites.pages.publish', ['website' => $website->id, 'page' => $page->id]))
            ->assertOk();

        // Verify public route is accessible
        $this->get(route('public.sites.pages.show', ['website' => $website->slug, 'page' => $page->slug]))
            ->assertOk();

        $originalDocJson = $page->fresh()->document_json;
        $originalRevisionsCount = $page->revisions()->count();

        // Unpublish page
        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.unpublish', ['website' => $website->id, 'page' => $page->id]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'draft',
            ]);

        $freshPage = $page->fresh();
        $this->assertEquals('draft', $freshPage->status->value);

        // Invariant: Draft document_json and revision history are NOT mutated by unpublishing
        $this->assertEquals($originalDocJson, $freshPage->document_json);
        $this->assertEquals($originalRevisionsCount, $freshPage->revisions()->count());

        // Public route now returns 404
        $this->get(route('public.sites.pages.show', ['website' => $website->slug, 'page' => $page->slug]))
            ->assertNotFound();
    }

    public function test_unpublish_denies_viewer_role(): void
    {
        [$website, $page, $viewer] = $this->createSiteAndPage('viewer');

        $response = $this->actingAs($viewer)
            ->postJson(route('websites.pages.unpublish', ['website' => $website->id, 'page' => $page->id]));

        $response->assertForbidden();
    }

    public function test_unpublish_aborts_with_404_on_cross_tenant_mismatch(): void
    {
        [$website1, $page1, $user] = $this->createSiteAndPage('owner');
        $website2 = Website::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.unpublish', ['website' => $website2->id, 'page' => $page1->id]));

        $response->assertNotFound();
    }
}
