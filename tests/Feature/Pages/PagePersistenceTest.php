<?php

namespace Tests\Feature\Pages;

use App\Enums\MediaAssetStatus;
use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageRevision;
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

    public function test_save_persists_canonical_document_and_repeated_alias_input_does_not_duplicate_revision(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);
        $asset = MediaAsset::factory()->create([
            'website_id' => $website->id,
            'status' => MediaAssetStatus::ACTIVE,
            'storage_path' => "media/{$website->id}/canonical-image.jpg",
            'deleted_at' => null,
        ]);
        $document = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'canonical_save_root',
                'type' => 'Section',
                'props' => [],
                'children' => [[
                    'id' => 'canonical_save_grid',
                    'type' => 'Card Grid',
                    'props' => [],
                    'children' => [
                        [
                            'id' => 'canonical_save_text',
                            'type' => 'Rich Text',
                            'props' => ['content' => '<p onclick="alert(1)">Keep me</p><script>alert(2)</script>'],
                            'children' => [],
                        ],
                        [
                            'id' => 'canonical_save_image',
                            'type' => 'Image',
                            'props' => [
                                'src' => "/storage/{$asset->storage_path}",
                                'alt' => 'Canonical image',
                            ],
                            'children' => [],
                        ],
                    ],
                ]],
            ],
        ];

        $firstResponse = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $document,
            'lock_version' => 1,
            'change_summary' => 'Canonical alias save',
        ]);

        $firstResponse->assertOk()->assertJsonPath('lock_version', 2);
        $page->refresh();
        $canonical = $page->document_json;
        $this->assertSame('CardGrid', $canonical['root']['children'][0]['type']);
        $this->assertSame('RichText', $canonical['root']['children'][0]['children'][0]['type']);
        $this->assertSame(['columns' => 3, 'gap' => 'medium'], $canonical['root']['children'][0]['props']);
        $this->assertStringContainsString('Keep me', $canonical['root']['children'][0]['children'][0]['props']['content']);
        $this->assertStringNotContainsString('onclick', $canonical['root']['children'][0]['children'][0]['props']['content']);
        $this->assertStringNotContainsString('<script', $canonical['root']['children'][0]['children'][0]['props']['content']);
        $this->assertSame("/storage/{$asset->storage_path}", $canonical['root']['children'][0]['children'][1]['props']['src']);

        $revision = PageRevision::where('page_id', $page->id)->sole();
        $this->assertSame($canonical, $revision->document_json);
        $this->assertSame(PageRevision::canonicalizeDocument($canonical), $revision->document_hash);

        $secondResponse = $this->actingAs($owner)->putJson(route('websites.pages.content.update', [$website, $page]), [
            'document_json' => $document,
            'lock_version' => 2,
            'change_summary' => 'Same canonical content',
        ]);

        $secondResponse->assertOk()
            ->assertJsonPath('lock_version', 3)
            ->assertJsonPath('revision_id', null);
        $this->assertSame($canonical, $page->fresh()->document_json);
        $this->assertSame(1, PageRevision::where('page_id', $page->id)->count());
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

    public function test_save_and_reload_preserves_locked_and_hidden_properties_across_database_roundtrip(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $docWithLockAndHidden = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_section',
                'type' => 'Section',
                'props' => [
                    'padding' => 'medium',
                    'background' => 'transparent',
                ],
                'children' => [
                    [
                        'id' => 'locked_container',
                        'type' => 'Container',
                        'props' => ['maxWidth' => 'lg', 'centered' => true],
                        'locked' => true,
                        'children' => [
                            [
                                'id' => 'unlocked_child',
                                'type' => 'Heading',
                                'props' => ['text' => 'Inside Locked Container', 'level' => 2],
                                'children' => [],
                            ],
                        ],
                    ],
                    [
                        'id' => 'hidden_heading',
                        'type' => 'Heading',
                        'props' => ['text' => 'Directly Hidden Title', 'level' => 3],
                        'hidden' => true,
                        'children' => [],
                    ],
                    [
                        'id' => 'explicitly_visible_heading',
                        'type' => 'Heading',
                        'props' => ['text' => 'Explicitly Visible Title', 'level' => 4],
                        'hidden' => false,
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Lock and Visibility Page',
                'description' => 'Preserved properties',
            ],
        ];

        $response = $this->actingAs($owner)->putJson(
            route('websites.pages.content.update', [$website, $page]),
            [
                'document_json' => $docWithLockAndHidden,
                'lock_version' => 1,
                'change_summary' => 'Save with locked and hidden nodes',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lock_version', 2);

        // Reload directly from database
        $freshPage = $page->fresh();
        $this->assertNotNull($freshPage);
        $this->assertSame(2, $freshPage->lock_version);

        $persistedRoot = $freshPage->document_json['root'];
        $this->assertTrue($persistedRoot['children'][0]['locked']);
        $this->assertArrayNotHasKey('locked', $persistedRoot['children'][0]['children'][0]);
        $this->assertTrue($persistedRoot['children'][1]['hidden']);
        $this->assertFalse($persistedRoot['children'][2]['hidden']);

        // Latest revision also retains identical canonical AST
        /** @var PageRevision $latestRevision */
        $latestRevision = PageRevision::where('page_id', $page->id)->orderByDesc('revision_number')->firstOrFail();
        $revRoot = $latestRevision->document_json['root'];
        $this->assertTrue($revRoot['children'][0]['locked']);
        $this->assertTrue($revRoot['children'][1]['hidden']);
        $this->assertFalse($revRoot['children'][2]['hidden']);
    }

    public function test_save_rejects_invalid_locked_or_hidden_types_with_422(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        // 1. Invalid locked type (string instead of boolean)
        $docInvalidLocked = $this->validDocumentWithHeading();
        $docInvalidLocked['root']['children'][0]['locked'] = 'true';

        $resLocked = $this->actingAs($owner)->putJson(
            route('websites.pages.content.update', [$website, $page]),
            ['document_json' => $docInvalidLocked, 'lock_version' => 1]
        );
        $resLocked->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'validation_failed',
            ]);

        // 2. Invalid hidden type (number instead of boolean)
        $docInvalidHidden = $this->validDocumentWithHeading();
        $docInvalidHidden['root']['children'][0]['hidden'] = 1;

        $resHidden = $this->actingAs($owner)->putJson(
            route('websites.pages.content.update', [$website, $page]),
            ['document_json' => $docInvalidHidden, 'lock_version' => 1]
        );
        $resHidden->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'validation_failed',
            ]);
    }

    public function test_save_rejects_hidden_root_with_422(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $docHiddenRoot = $this->validDocumentWithHeading();
        $docHiddenRoot['root']['hidden'] = true;

        $response = $this->actingAs($owner)->putJson(
            route('websites.pages.content.update', [$website, $page]),
            ['document_json' => $docHiddenRoot, 'lock_version' => 1]
        );

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'validation_failed',
            ]);
    }

    public function test_save_and_reload_preserves_custom_node_labels(): void
    {
        $owner = User::factory()->create();
        [$website, $page] = $this->createWebsiteWithPage($owner);

        $docWithLabels = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => ['padding' => 'medium', 'background' => 'transparent'],
                'label' => 'Top Section Hero',
                'children' => [
                    [
                        'id' => 'child_heading',
                        'type' => 'Heading',
                        'props' => ['text' => 'Custom Labeled Title', 'level' => 2],
                        'label' => 'Primary Heading Banner',
                        'children' => [],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Page with Custom Labels',
                'description' => 'Test custom label roundtrip',
            ],
        ];

        $response = $this->actingAs($owner)->putJson(
            route('websites.pages.content.update', [$website, $page]),
            [
                'document_json' => $docWithLabels,
                'lock_version' => 1,
                'change_summary' => 'Save with custom node labels',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lock_version', 2);

        $freshPage = $page->fresh();
        $this->assertNotNull($freshPage);
        $this->assertSame('Top Section Hero', $freshPage->document_json['root']['label']);
        $this->assertSame('Primary Heading Banner', $freshPage->document_json['root']['children'][0]['label']);
    }
}
