<?php

namespace Tests\Feature\Publishing;

use App\Domains\Publishing\Services\DocumentHtmlRenderer;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PagePreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createValidDocument(string $heading = 'Draft Heading'): array
    {
        return [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
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
                'title' => 'Preview Page',
                'description' => 'Preview test page',
            ],
        ];
    }

    private function createSiteAndPage(): array
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $page = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'document_json' => $this->createValidDocument('Draft Heading'),
            'status' => 'draft',
        ]);

        return [$website, $page, $user];
    }

    public function test_authenticated_user_can_view_private_preview_with_watermark_and_anti_indexing_headers(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();

        $response = $this->actingAs($user)
            ->get(route('websites.pages.preview.show', [
                'website' => $website->id,
                'page' => $page->id,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));

        $content = $response->getContent();
        $this->assertStringStartsWith('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('PREVIEW MODE', $content);
        $this->assertStringContainsString('Draft Heading', $content);
        $this->assertGreaterThan(strpos($content, '<body class='), strpos($content, 'PREVIEW MODE'));

        $publicHtml = app(DocumentHtmlRenderer::class)->renderDocument($page->document_json);
        $this->assertStringStartsWith('<!DOCTYPE html>', $publicHtml);
        $this->assertStringNotContainsString('PREVIEW MODE', $publicHtml);
    }

    public function test_preview_renders_aliases_with_the_same_canonical_sanitization_as_public_rendering(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();
        $aliasDocument = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'preview_alias_root',
                'type' => 'Section',
                'props' => [],
                'children' => [[
                    'id' => 'preview_alias_text',
                    'type' => 'Rich Text',
                    'props' => ['content' => '<p onclick="alert(1)">Canonical preview</p><script>alert(2)</script>'],
                    'children' => [],
                ]],
            ],
        ];
        $page->update(['document_json' => $aliasDocument]);

        $response = $this->actingAs($user)->get(route('websites.pages.preview.show', [$website, $page]));

        $response->assertOk();
        $previewHtml = $response->getContent();
        $publicHtml = app(DocumentHtmlRenderer::class)->renderDocument($aliasDocument);
        $this->assertStringContainsString('Canonical preview', $previewHtml);
        $this->assertStringContainsString('Canonical preview', $publicHtml);
        $this->assertStringNotContainsString('onclick', $previewHtml);
        $this->assertStringNotContainsString('<script', $previewHtml);
        $this->assertStringNotContainsString('preview-node-error', $previewHtml);
        $this->assertStringNotContainsString('onclick', $publicHtml);
        $this->assertStringNotContainsString('<script', $publicHtml);
    }

    public function test_authenticated_preview_can_render_historical_revision(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();

        $revDoc = $this->createValidDocument('Historical Revision #42');
        $revision = PageRevision::factory()->create([
            'page_id' => $page->id,
            'document_json' => $revDoc,
            'document_hash' => PageRevision::canonicalizeDocument($revDoc),
            'revision_number' => 42,
        ]);

        $response = $this->actingAs($user)
            ->get(route('websites.pages.preview.show', [
                'website' => $website->id,
                'page' => $page->id,
                'revision' => $revision->id,
            ]));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Historical Revision #42', $content);
    }

    public function test_unauthenticated_or_non_member_cannot_access_private_preview(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();
        $stranger = User::factory()->create();

        // Guest redirected to login
        $this->get(route('websites.pages.preview.show', [
            'website' => $website->id,
            'page' => $page->id,
        ]))->assertRedirect();

        // Stranger receives 403
        $this->actingAs($stranger)
            ->get(route('websites.pages.preview.show', [
                'website' => $website->id,
                'page' => $page->id,
            ]))->assertForbidden();
    }

    public function test_guest_can_access_preview_with_valid_temporary_signed_url(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();

        $signedUrl = URL::temporarySignedRoute('preview.signed', now()->addMinutes(60), [
            'page' => $page->id,
        ]);

        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('PREVIEW MODE', $response->getContent());
        $this->assertStringContainsString('Draft Heading', $response->getContent());
    }

    public function test_signed_preview_fails_with_403_when_signature_is_tampered_or_expired(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();

        // Tampered signature
        $tamperedUrl = URL::temporarySignedRoute('preview.signed', now()->addMinutes(60), [
            'page' => $page->id,
        ]).'&tampered=1';

        $response = $this->get($tamperedUrl);
        $response->assertForbidden();
        $this->assertStringContainsString('expired or is invalid', $response->getContent());

        // Expired signature
        $expiredUrl = URL::temporarySignedRoute('preview.signed', now()->subMinute(), [
            'page' => $page->id,
        ]);

        $response2 = $this->get($expiredUrl);
        $response2->assertForbidden();
        $this->assertStringContainsString('expired or is invalid', $response2->getContent());
    }

    public function test_generate_shareable_preview_link(): void
    {
        [$website, $page, $user] = $this->createSiteAndPage();

        $response = $this->actingAs($user)
            ->postJson(route('websites.pages.preview.share-link', [
                'website' => $website->id,
                'page' => $page->id,
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'signed_url',
                'expires_at',
            ]);

        $signedUrl = $response->json('signed_url');
        $this->assertNotEmpty($signedUrl);

        // Verify the generated URL is valid and accessible
        $this->get($signedUrl)->assertOk();
    }

    public function test_preview_strictly_omits_hidden_nodes_and_their_descendants(): void
    {
        $customDoc = [
            'schemaVersion' => 1,
            'root' => [
                'id' => 'root_sec',
                'type' => 'Section',
                'props' => ['padding' => 'medium', 'background' => 'transparent'],
                'children' => [
                    [
                        'id' => 'preview_visible_node',
                        'type' => 'Heading',
                        'props' => ['text' => 'Visible in Preview', 'level' => 1],
                        'children' => [],
                    ],
                    [
                        'id' => 'preview_hidden_leaf',
                        'type' => 'Heading',
                        'props' => ['text' => 'Hidden Secret in Preview', 'level' => 2],
                        'hidden' => true,
                        'children' => [],
                    ],
                    [
                        'id' => 'preview_hidden_container',
                        'type' => 'Container',
                        'props' => ['maxWidth' => 'lg', 'centered' => true],
                        'hidden' => true,
                        'children' => [
                            [
                                'id' => 'preview_hidden_child_btn',
                                'type' => 'Button',
                                'props' => ['label' => 'Hidden Secret Button', 'url' => '#'],
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'pageSettings' => [
                'title' => 'Preview Page with Hidden Elements',
                'description' => 'Test preview sanitization',
            ],
        ];

        [$website, $page, $user] = $this->createSiteAndPage();
        $page->update(['document_json' => $customDoc]);

        // 1. Authenticated author preview route
        $response = $this->actingAs($user)->get(route('websites.pages.preview.show', [
            'website' => $website->id,
            'page' => $page->id,
        ]));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Visible in Preview', $content);
        $this->assertStringContainsString('preview_visible_node', $content);

        $this->assertStringNotContainsString('Hidden Secret in Preview', $content);
        $this->assertStringNotContainsString('preview_hidden_leaf', $content);
        $this->assertStringNotContainsString('preview_hidden_container', $content);
        $this->assertStringNotContainsString('Hidden Secret Button', $content);
        $this->assertStringNotContainsString('preview_hidden_child_btn', $content);

        // 2. Signed public preview route
        $signedUrl = URL::temporarySignedRoute('preview.signed', now()->addMinutes(30), [
            'page' => $page->id,
        ]);

        $signedResponse = $this->get($signedUrl);
        $signedResponse->assertOk();
        $signedContent = $signedResponse->getContent();

        $this->assertStringContainsString('Visible in Preview', $signedContent);
        $this->assertStringNotContainsString('Hidden Secret in Preview', $signedContent);
        $this->assertStringNotContainsString('Hidden Secret Button', $signedContent);
    }
}
