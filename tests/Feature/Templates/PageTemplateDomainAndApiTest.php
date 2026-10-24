<?php

namespace Tests\Feature\Templates;

use App\Domains\Templates\Services\TemplateInstantiationService;
use App\Enums\WebsiteRole;
use App\Models\PageTemplate;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Database\Seeders\StarterTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTemplateDomainAndApiTest extends TestCase
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

    private function validDocument(): array
    {
        return [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec_test',
                'type' => 'Section',
                'props' => [
                    'padding' => 'medium',
                    'background' => 'transparent',
                ],
                'children' => [
                    [
                        'id' => 'node_cnt_test',
                        'type' => 'Container',
                        'props' => [
                            'maxWidth' => 'lg',
                            'centered' => true,
                        ],
                        'children' => [
                            [
                                'id' => 'node_head_test',
                                'type' => 'Heading',
                                'props' => [
                                    'text' => 'Test Heading',
                                    'level' => 2,
                                    'align' => 'left',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_guests_cannot_access_template_endpoints(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        $template = PageTemplate::factory()->forWebsite($website)->create();

        $this->getJson(route('websites.templates.index', $website))->assertUnauthorized();
        $this->getJson(route('websites.templates.show', [$website, $template]))->assertUnauthorized();
        $this->postJson(route('websites.templates.store', $website))->assertUnauthorized();
        $this->putJson(route('websites.templates.update', [$website, $template]))->assertUnauthorized();
        $this->deleteJson(route('websites.templates.destroy', [$website, $template]))->assertUnauthorized();
    }

    public function test_website_members_can_list_and_view_global_and_own_templates(): void
    {
        [$website, $owner, $editor, $viewer] = $this->createWebsiteWithMembers();

        $globalTmpl = PageTemplate::factory()->global()->create(['name' => 'Global Blueprint']);
        $customTmpl = PageTemplate::factory()->forWebsite($website)->create(['name' => 'Custom Blueprint']);

        foreach ([$owner, $editor, $viewer] as $user) {
            $response = $this->actingAs($user)->getJson(route('websites.templates.index', $website));
            $response->assertOk();
            $data = $response->json('data');
            $this->assertCount(2, $data);

            $this->actingAs($user)
                ->getJson(route('websites.templates.show', [$website, $globalTmpl]))
                ->assertOk()
                ->assertJsonPath('data.name', 'Global Blueprint');

            $this->actingAs($user)
                ->getJson(route('websites.templates.show', [$website, $customTmpl]))
                ->assertOk()
                ->assertJsonPath('data.name', 'Custom Blueprint');
        }
    }

    public function test_viewer_cannot_create_or_update_or_delete_templates(): void
    {
        [$website, , , $viewer] = $this->createWebsiteWithMembers();
        $customTmpl = PageTemplate::factory()->forWebsite($website)->create();

        $this->actingAs($viewer)
            ->postJson(route('websites.templates.store', $website), [
                'name' => 'Unauthorized Template',
                'document_json' => $this->validDocument(),
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->putJson(route('websites.templates.update', [$website, $customTmpl]), [
                'name' => 'Updated Name',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->deleteJson(route('websites.templates.destroy', [$website, $customTmpl]))
            ->assertForbidden();
    }

    public function test_owner_and_editor_can_create_and_update_custom_templates(): void
    {
        [$website, $owner, $editor] = $this->createWebsiteWithMembers();

        // 1. Owner creates custom template
        $response = $this->actingAs($owner)
            ->postJson(route('websites.templates.store', $website), [
                'name' => 'Owner Custom Template',
                'description' => 'A custom layout created by the website owner.',
                'category' => 'landing',
                'document_json' => $this->validDocument(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Owner Custom Template')
            ->assertJsonPath('data.website_id', $website->id);

        $templateId = $response->json('data.id');

        // 2. Editor updates the custom template
        $updateResponse = $this->actingAs($editor)
            ->putJson(route('websites.templates.update', [$website, $templateId]), [
                'name' => 'Editor Updated Template',
                'description' => 'Updated description.',
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Editor Updated Template')
            ->assertJsonPath('data.description', 'Updated description.');
    }

    public function test_global_templates_are_strictly_immutable(): void
    {
        [$website, $owner, $editor] = $this->createWebsiteWithMembers();

        $globalTmpl = PageTemplate::factory()->global()->create(['name' => 'Immutable System Template']);

        // Owner attempt to update global template -> 403 Forbidden
        $this->actingAs($owner)
            ->putJson(route('websites.templates.update', [$website, $globalTmpl]), [
                'name' => 'Tampered Name',
            ])
            ->assertForbidden();

        // Editor attempt to delete global template -> 403 Forbidden
        $this->actingAs($editor)
            ->deleteJson(route('websites.templates.destroy', [$website, $globalTmpl]))
            ->assertForbidden();

        $this->assertDatabaseHas('page_templates', [
            'id' => $globalTmpl->id,
            'name' => 'Immutable System Template',
            'website_id' => null,
        ]);
    }

    public function test_cross_website_template_access_returns_404_idor_protection(): void
    {
        [$websiteA, $ownerA] = $this->createWebsiteWithMembers();
        [$websiteB, $ownerB] = $this->createWebsiteWithMembers();

        $templateA = PageTemplate::factory()->forWebsite($websiteA)->create(['name' => 'Site A Template']);

        // Owner B attempts to view Site A's template via Site B route
        $this->actingAs($ownerB)
            ->getJson(route('websites.templates.show', [$websiteB, $templateA]))
            ->assertNotFound();

        // Owner B attempts to update Site A's template via Site B route
        $this->actingAs($ownerB)
            ->putJson(route('websites.templates.update', [$websiteB, $templateA]), [
                'name' => 'Hacked Name',
            ])
            ->assertNotFound();

        // Owner B attempts to delete Site A's template via Site B route
        $this->actingAs($ownerB)
            ->deleteJson(route('websites.templates.destroy', [$websiteB, $templateA]))
            ->assertNotFound();
    }

    public function test_invalid_document_schema_v1_rejected_on_store_and_update(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();
        $template = PageTemplate::factory()->forWebsite($website)->create();

        // 1. Unknown component type
        $invalidDocUnknown = $this->validDocument();
        $invalidDocUnknown['root']['children'][0]['type'] = 'NonExistentWidget';

        $this->actingAs($owner)
            ->postJson(route('websites.templates.store', $website), [
                'name' => 'Invalid Widget Template',
                'document_json' => $invalidDocUnknown,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Template document failed Schema v1 validation.');

        // 2. Unknown prop on valid component
        $invalidDocProp = $this->validDocument();
        $invalidDocProp['root']['children'][0]['props']['unsupportedProp'] = 'malicious';

        $this->actingAs($owner)
            ->putJson(route('websites.templates.update', [$website, $template]), [
                'document_json' => $invalidDocProp,
            ])
            ->assertStatus(422);

        // 3. Duplicate node IDs
        $invalidDocDuplicate = $this->validDocument();
        $invalidDocDuplicate['root']['children'][] = [
            'id' => 'root_sec_test', // duplicate of root id
            'type' => 'Container',
            'props' => ['maxWidth' => 'lg'],
            'children' => [],
        ];

        $this->actingAs($owner)
            ->postJson(route('websites.templates.store', $website), [
                'name' => 'Duplicate Node ID Template',
                'document_json' => $invalidDocDuplicate,
            ])
            ->assertStatus(422);
    }

    public function test_seeder_is_idempotent_and_preserves_intentionally_modified_global_templates(): void
    {
        $seeder = new StarterTemplateSeeder;

        // 1. First run: seeds 5 templates
        $seeder->run();
        $this->assertEquals(5, PageTemplate::global()->count());

        // 2. Intentionally customize one global template
        $blankTemplate = PageTemplate::global()->where('slug', 'blank')->firstOrFail();
        $blankTemplate->update([
            'name' => 'Customized Blank Canvas',
            'description' => 'Locally customized blank canvas layout.',
        ]);

        // 3. Second run: non-destructive policy preserves the modification
        $seeder->run(force: false);
        $this->assertEquals(5, PageTemplate::global()->count());

        $reloaded = PageTemplate::global()->where('slug', 'blank')->firstOrFail();
        $this->assertEquals('Customized Blank Canvas', $reloaded->name);
        $this->assertEquals('Locally customized blank canvas layout.', $reloaded->description);

        // 4. Force run: restores default blueprint
        $seeder->run(force: true);
        $reloadedForced = PageTemplate::global()->where('slug', 'blank')->firstOrFail();
        $this->assertEquals('Blank Canvas', $reloadedForced->name);
    }

    public function test_template_instantiation_renews_all_node_ids_with_contract_and_preserves_source(): void
    {
        $template = PageTemplate::factory()->global()->create([
            'document_json' => $this->validDocument(),
        ]);

        $service = app(TemplateInstantiationService::class);

        // First instantiation
        $doc1 = $service->instantiate($template, 'Page 1');
        // Second instantiation
        $doc2 = $service->instantiate($template, 'Page 2');

        $rootIdOriginal = $template->document_json['root']['id'];
        $rootId1 = $doc1['root']['id'];
        $rootId2 = $doc2['root']['id'];

        // 1. Fresh IDs generated
        $this->assertNotEquals($rootIdOriginal, $rootId1);
        $this->assertNotEquals($rootIdOriginal, $rootId2);
        $this->assertNotEquals($rootId1, $rootId2);

        // 2. Conforms strictly to schema pattern /^[a-zA-Z0-9_-]{3,64}$/
        $this->assertMatchesRegularExpression(TemplateInstantiationService::NODE_ID_PATTERN, $rootId1);
        $this->assertMatchesRegularExpression(TemplateInstantiationService::NODE_ID_PATTERN, $rootId2);
        $this->assertLessThanOrEqual(64, strlen($rootId1));

        // 3. Child nodes also renewed
        $childId1 = $doc1['root']['children'][0]['id'];
        $childId2 = $doc2['root']['children'][0]['id'];
        $this->assertNotEquals($childId1, $childId2);
        $this->assertMatchesRegularExpression(TemplateInstantiationService::NODE_ID_PATTERN, $childId1);

        // 4. Source template document remains unmutated
        $template->refresh();
        $this->assertEquals($rootIdOriginal, $template->document_json['root']['id']);
    }

    public function test_page_create_and_builder_flow_with_template(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();
        $template = PageTemplate::factory()->forWebsite($website)->create([
            'document_json' => $this->validDocument(),
        ]);

        // 1. Page creation form contains templates
        $createPageResponse = $this->actingAs($owner)
            ->get(route('websites.pages.create', $website));
        $createPageResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pages/Create')
                ->has('templates')
            );

        // 2. Submit new page with template_id
        $storeResponse = $this->actingAs($owner)
            ->post(route('websites.pages.store', $website), [
                'title' => 'New Landing Page',
                'slug' => 'new-landing',
                'template_id' => $template->id,
            ]);

        // Must redirect to builder with ?template={id}
        $storeResponse->assertRedirect();
        $redirectUrl = $storeResponse->headers->get('Location') ?? '';
        $this->assertStringContainsString('template='.$template->id, $redirectUrl);

        // 3. Opening builder with template initializes canvas
        $builderResponse = $this->actingAs($owner)
            ->get($redirectUrl);

        $builderResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pages/Builder')
                ->has('initialDocument')
                ->where('initialDocument.pageSettings.title', 'New Landing Page')
            );
    }

    public function test_inactive_template_instantiation_returns_404(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $inactiveTemplate = PageTemplate::factory()->forWebsite($website)->inactive()->create([
            'document_json' => $this->validDocument(),
        ]);

        $this->actingAs($owner)
            ->postJson(route('websites.templates.instantiate', [$website, $inactiveTemplate]))
            ->assertNotFound();

        $inactiveGlobal = PageTemplate::factory()->global()->inactive()->create([
            'document_json' => $this->validDocument(),
        ]);

        $this->actingAs($owner)
            ->postJson(route('websites.templates.instantiate', [$website, $inactiveGlobal]))
            ->assertNotFound();
    }

    public function test_category_whitelist_validation(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();
        $template = PageTemplate::factory()->forWebsite($website)->create();

        // Each allowed category must be accepted
        foreach (PageTemplate::ALLOWED_CATEGORIES as $cat) {
            $resp = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
                'name' => "Template {$cat}",
                'category' => $cat,
                'document_json' => $this->validDocument(),
            ]);
            $resp->assertCreated()->assertJsonPath('data.category', $cat);
        }

        // Unsupported category must be rejected with 422
        $respInvalid = $this->actingAs($owner)->postJson(route('websites.templates.store', $website), [
            'name' => 'Template Invalid Category',
            'category' => 'unsupported_cat_123',
            'document_json' => $this->validDocument(),
        ]);
        $respInvalid->assertStatus(422)->assertJsonValidationErrors(['category']);

        $respInvalidUpdate = $this->actingAs($owner)->putJson(route('websites.templates.update', [$website, $template]), [
            'category' => 'malicious_cat',
        ]);
        $respInvalidUpdate->assertStatus(422)->assertJsonValidationErrors(['category']);
    }

    public function test_store_page_template_id_validation_rules(): void
    {
        [$websiteA, $ownerA] = $this->createWebsiteWithMembers();
        [$websiteB] = $this->createWebsiteWithMembers();

        $activeSiteTemplate = PageTemplate::factory()->forWebsite($websiteA)->create();
        $inactiveSiteTemplate = PageTemplate::factory()->forWebsite($websiteA)->inactive()->create();
        $activeForeignTemplate = PageTemplate::factory()->forWebsite($websiteB)->create();
        $activeGlobalTemplate = PageTemplate::factory()->global()->create();
        $inactiveGlobalTemplate = PageTemplate::factory()->global()->inactive()->create();

        // 1. Valid site template passes
        $this->actingAs($ownerA)->post(route('websites.pages.store', $websiteA), [
            'title' => 'Page 1',
            'slug' => 'page-1',
            'template_id' => $activeSiteTemplate->id,
        ])->assertRedirect();

        // 2. Valid global template passes
        $this->actingAs($ownerA)->post(route('websites.pages.store', $websiteA), [
            'title' => 'Page 2',
            'slug' => 'page-2',
            'template_id' => $activeGlobalTemplate->id,
        ])->assertRedirect();

        // 3. Nonexistent template ID fails validation
        $this->actingAs($ownerA)->post(route('websites.pages.store', $websiteA), [
            'title' => 'Page 3',
            'slug' => 'page-3',
            'template_id' => 999999,
        ])->assertSessionHasErrors(['template_id']);

        // 4. Inactive site template fails validation
        $this->actingAs($ownerA)->post(route('websites.pages.store', $websiteA), [
            'title' => 'Page 4',
            'slug' => 'page-4',
            'template_id' => $inactiveSiteTemplate->id,
        ])->assertSessionHasErrors(['template_id']);

        // 5. Inactive global template fails validation
        $this->actingAs($ownerA)->post(route('websites.pages.store', $websiteA), [
            'title' => 'Page 5',
            'slug' => 'page-5',
            'template_id' => $inactiveGlobalTemplate->id,
        ])->assertSessionHasErrors(['template_id']);

        // 6. Cross-website template fails validation
        $this->actingAs($ownerA)->post(route('websites.pages.store', $websiteA), [
            'title' => 'Page 6',
            'slug' => 'page-6',
            'template_id' => $activeForeignTemplate->id,
        ])->assertSessionHasErrors(['template_id']);
    }

    public function test_templates_seed_artisan_command_with_and_without_force(): void
    {
        $this->artisan('templates:seed')->assertSuccessful();
        $this->assertEquals(5, PageTemplate::global()->count());

        // Modify a seeded template
        $blankTemplate = PageTemplate::global()->where('slug', 'blank')->firstOrFail();
        $blankTemplate->update(['name' => 'Customized Blank Title']);

        // Running without --force preserves customization
        $this->artisan('templates:seed')->assertSuccessful();
        $this->assertEquals('Customized Blank Title', $blankTemplate->fresh()->name);

        // Running with --force overwrites customization
        $this->artisan('templates:seed', ['--force' => true])->assertSuccessful();
        $this->assertEquals('Blank Canvas', $blankTemplate->fresh()->name);
    }
}
