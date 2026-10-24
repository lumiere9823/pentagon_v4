<?php

namespace Tests\Feature\Publishing;

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
        $this->assertStringContainsString('PREVIEW MODE', $content);
        $this->assertStringContainsString('Draft Heading', $content);
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
}
