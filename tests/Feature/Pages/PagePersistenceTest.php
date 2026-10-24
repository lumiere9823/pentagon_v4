<?php

namespace Tests\Feature\Pages;

use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\Page;
use App\Models\PageTemplate;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PagePersistenceTest extends TestCase
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
            'title' => 'Landing Page',
            'slug' => 'landing',
            'status' => PageStatus::DRAFT,
            'lock_version' => $lockVersion,
            'document_json' => $document ?? $this->validDocumentWithHeading(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        return [$website, $page];
    }

    public function test_guest_cannot_update_page_content(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $response = $this->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $this->validDocumentWithHeading(),
            'lock_version' => 1,
        ]);

        $response->assertUnauthorized();
    }

    public function test_viewer_cannot_update_page_content(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $viewer->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        $response = $this->actingAs($viewer)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $this->validDocumentWithHeading(),
            'lock_version' => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_cross_website_page_update_returns_404_idor(): void
    {
        $owner = User::factory()->create();
        [$websiteA, $pageA] = $this->createWebsiteWithPage($owner);
        [$websiteB, $pageB] = $this->createWebsiteWithPage($owner);

        $response = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$websiteA, $pageB]), [
            'document_json' => $this->validDocumentWithHeading(),
            'lock_version' => 1,
        ]);

        $response->assertNotFound();
    }

    public function test_owner_can_save_page_content_and_increments_lock_version(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $newDoc = $this->validDocumentWithHeading('Updated Title');

        $response = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $newDoc,
            'lock_version' => 1,
            'change_summary' => 'Initial header edit',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 200,
                'lock_version' => 2,
            ]);

        $page->refresh();
        $this->assertSame(2, $page->lock_version);
        $this->assertSame('Updated Title', $page->document_json['root']['children'][0]['props']['text']);
        $this->assertSame($owner->id, $page->updated_by);
    }

    public function test_editor_can_save_page_content(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $editor->id,
            'role' => WebsiteRole::EDITOR,
        ]);

        $newDoc = $this->validDocumentWithHeading('Editor Content');

        $response = $this->actingAs($editor)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $newDoc,
            'lock_version' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'lock_version' => 2,
            ]);

        $page->refresh();
        $this->assertSame(2, $page->lock_version);
        $this->assertSame($editor->id, $page->updated_by);
    }

    public function test_saving_invalid_document_schema_is_rejected_with_422(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $invalidDoc = [
            'schema_version' => 1,
            'root' => [
                'id' => 'root',
                'type' => 'unknown_component_type',
                'props' => [],
            ],
        ];

        $response = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $invalidDoc,
            'lock_version' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'validation_failed',
            ]);

        $page->refresh();
        $this->assertSame(1, $page->lock_version);
    }

    public function test_stale_lock_version_returns_409_conflict(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner, null, 5);

        // Submit with lock_version = 4 (stale)
        $response = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $this->validDocumentWithHeading(),
            'lock_version' => 4,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'document_conflict',
                'current_lock_version' => 5,
            ]);

        $page->refresh();
        $this->assertSame(5, $page->lock_version);
    }

    public function test_builder_view_hydrates_persisted_document_and_lock_version(): void
    {
        $owner = User::factory()->create();
        $doc = $this->validDocumentWithHeading('Persisted in DB');
        [$website, $page] = $this->createWebsiteWithPage($owner, $doc, 3);

        $response = $this->actingAs($owner)->get(route('websites.pages.builder', [$website, $page]));

        $response->assertOk()
            ->assertInertia(fn (Assert $pageAssert) => $pageAssert
                ->component('Pages/Builder')
                ->where('page.id', $page->id)
                ->where('page.lock_version', 3)
                ->where('initialDocument.root.children.0.props.text', 'Persisted in DB')
            );
    }

    public function test_creating_page_from_template_persists_initial_document_and_revision_one(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $templateDoc = $this->validDocumentWithHeading('Template Heading');
        $template = PageTemplate::create([
            'website_id' => $website->id,
            'name' => 'Agency Template',
            'slug' => 'agency-template',
            'description' => 'A starter template',
            'category' => 'landing',
            'document_json' => $templateDoc,
            'schema_version' => 1,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->post(route('websites.pages.store', $website), [
            'title' => 'New Page From Template',
            'slug' => 'new-from-template',
            'status' => 'draft',
            'template_id' => $template->id,
        ]);

        $createdPage = Page::where('slug', 'new-from-template')->firstOrFail();
        $response->assertRedirect(route('websites.pages.builder', ['website' => $website, 'page' => $createdPage, 'template' => $template->id]));

        $this->assertNotNull($createdPage->document_json);
        $this->assertSame('Template Heading', $createdPage->document_json['root']['children'][0]['props']['text']);
        $this->assertSame(1, $createdPage->lock_version);

        // Verify Revision #1 was created
        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $createdPage->id,
            'revision_number' => 1,
            'change_summary' => 'Initialized from template: Agency Template',
        ]);
    }

    public function test_creating_page_without_template_persists_canonical_empty_document(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        // 1. API creation without template_id
        $response = $this->actingAs($owner)->post(route('websites.pages.store', $website), [
            'title' => 'Blank Canvas Page',
            'slug' => 'blank-canvas',
            'status' => 'draft',
        ]);

        $response->assertRedirect(route('websites.show', $website));

        $createdPage = Page::where('slug', 'blank-canvas')->firstOrFail();

        // Must persist authoritative Document Schema v1 empty document immediately
        $this->assertNotNull($createdPage->document_json);
        $this->assertSame(1, $createdPage->document_json['schemaVersion']);
        $this->assertSame('Section', $createdPage->document_json['root']['type']);
        $this->assertSame('Blank Canvas Page', $createdPage->document_json['pageSettings']['title']);
        $this->assertSame(1, $createdPage->lock_version);

        // Anti-bloat policy (D-028): no revision created yet
        $this->assertDatabaseMissing('page_revisions', [
            'page_id' => $createdPage->id,
        ]);

        // 2. Reopening the builder hydrates the persisted empty document without template query param
        $builderResponse = $this->actingAs($owner)->get(route('websites.pages.builder', [$website, $createdPage]));

        $builderResponse->assertOk()
            ->assertInertia(fn (Assert $pageAssert) => $pageAssert
                ->component('Pages/Builder')
                ->where('page.id', $createdPage->id)
                ->where('page.lock_version', 1)
                ->where('initialDocument.pageSettings.title', 'Blank Canvas Page')
            );
    }

    public function test_ui_compatible_creation_with_null_template_id_persists_empty_document(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        // Form submission with explicit template_id => null
        $response = $this->actingAs($owner)->post(route('websites.pages.store', $website), [
            'title' => 'UI Form Page',
            'slug' => 'ui-form-page',
            'status' => 'draft',
            'template_id' => null,
        ]);

        $response->assertRedirect(route('websites.show', $website));

        $createdPage = Page::where('slug', 'ui-form-page')->firstOrFail();
        $this->assertNotNull($createdPage->document_json);
        $this->assertSame('UI Form Page', $createdPage->document_json['pageSettings']['title']);
        $this->assertSame(1, $createdPage->lock_version);
    }
}
