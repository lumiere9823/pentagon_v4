<?php

namespace Tests\Feature\Pages;

use App\Domains\DocumentEngine\Validation\DocumentValidator;
use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsiteWithPage(User $owner): array
    {
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $page = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'Home Page',
            'slug' => 'home',
            'status' => PageStatus::DRAFT,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        return [$website, $page];
    }

    public function test_unauthenticated_user_cannot_access_builder_route(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $response = $this->get(route('websites.pages.builder', [$website, $page]));
        $response->assertRedirect(route('login'));
    }

    public function test_non_member_cannot_access_builder_route(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $response = $this->actingAs($stranger)->get(route('websites.pages.builder', [$website, $page]));
        $response->assertForbidden();
    }

    public function test_cross_website_page_access_returns_404_idor(): void
    {
        $owner = User::factory()->create();
        [$websiteA, $pageA] = $this->createWebsiteWithPage($owner);
        [$websiteB, $pageB] = $this->createWebsiteWithPage($owner);

        // Attempt to access pageB under websiteA's URL
        $response = $this->actingAs($owner)->get(route('websites.pages.builder', [$websiteA, $pageB]));
        $response->assertNotFound();
    }

    public function test_owner_can_access_builder_route_with_edit_capability(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $response = $this->actingAs($owner)->get(route('websites.pages.builder', [$website, $page]));

        $response->assertOk()
            ->assertInertia(fn (Assert $pageAssert) => $pageAssert
                ->component('Pages/Builder')
                ->where('website.id', $website->id)
                ->where('website.name', $website->name)
                ->where('page.id', $page->id)
                ->where('page.title', 'Home Page')
                ->where('userRole', 'owner')
                ->where('can.edit', true)
                ->where('can.delete', true)
            );
    }

    public function test_editor_can_access_builder_route_with_edit_capability(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $editor->id,
            'role' => WebsiteRole::EDITOR,
        ]);

        $response = $this->actingAs($editor)->get(route('websites.pages.builder', [$website, $page]));

        $response->assertOk()
            ->assertInertia(fn (Assert $pageAssert) => $pageAssert
                ->component('Pages/Builder')
                ->where('website.id', $website->id)
                ->where('page.id', $page->id)
                ->where('userRole', 'editor')
                ->where('can.edit', true)
                ->where('can.delete', true)
            );
    }

    public function test_viewer_accesses_builder_route_with_read_only_capability(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $viewer->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        $response = $this->actingAs($viewer)->get(route('websites.pages.builder', [$website, $page]));

        $response->assertOk()
            ->assertInertia(fn (Assert $pageAssert) => $pageAssert
                ->component('Pages/Builder')
                ->where('website.id', $website->id)
                ->where('page.id', $page->id)
                ->where('userRole', 'viewer')
                ->where('can.edit', false)
                ->where('can.delete', false)
            );
    }

    public function test_editor_alias_route_works_identically(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $response = $this->actingAs($owner)->get(route('websites.pages.editor', [$website, $page]));

        $response->assertOk()
            ->assertInertia(fn (Assert $pageAssert) => $pageAssert
                ->component('Pages/Builder')
                ->where('page.id', $page->id)
                ->where('can.edit', true)
            );
    }

    public function test_default_editor_document_is_fully_valid_with_server_validator(): void
    {
        $validator = new DocumentValidator;

        $defaultDoc = [
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
                        'id' => 'node_container_default',
                        'type' => 'Container',
                        'props' => [
                            'maxWidth' => 'lg',
                            'centered' => true,
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

        $result = $validator->validate($defaultDoc);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_twelve_components_fixture_is_fully_valid_with_server_validator(): void
    {
        $validator = new DocumentValidator;
        $fixturePath = base_path('tests/Fixtures/Documents/all_twelve_components.json');
        $rawJson = file_get_contents($fixturePath);

        $result = $validator->validate($rawJson);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }
}
