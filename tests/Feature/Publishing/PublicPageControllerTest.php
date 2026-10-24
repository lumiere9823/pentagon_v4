<?php

namespace Tests\Feature\Publishing;

use App\Domains\Publishing\Services\PagePublishingService;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected PagePublishingService $publishingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishingService = app(PagePublishingService::class);
        Storage::fake('local');
    }

    private function createValidDocument(string $heading = 'Public Content'): array
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
                'title' => 'Public Test Page',
                'description' => 'A public test page',
            ],
        ];
    }

    private function createPublishedPage(): array
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'test-site']);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $page = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'slug' => 'about-us',
            'document_json' => $this->createValidDocument('Welcome to Our Site'),
            'status' => 'draft',
        ]);

        $release = $this->publishingService->publish($page, $user);

        return [$website, $page, $release];
    }

    public function test_serves_active_published_page_with_200_and_etag(): void
    {
        [$website, $page, $release] = $this->createPublishedPage();

        $response = $this->get(route('public.sites.pages.show', [
            'website' => $website->slug,
            'page' => $page->slug,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));

        $etag = $release->manifest_json['html_hash'];
        $response->assertHeader('ETag', '"'.$etag.'"');
        $this->assertStringContainsString('Welcome to Our Site', $response->getContent());
    }

    public function test_returns_304_not_modified_when_if_none_match_matches_etag(): void
    {
        [$website, $page, $release] = $this->createPublishedPage();
        $etag = $release->manifest_json['html_hash'];

        $response = $this->withHeaders([
            'If-None-Match' => '"'.$etag.'"',
        ])->get(route('public.sites.pages.show', [
            'website' => $website->slug,
            'page' => $page->slug,
        ]));

        $response->assertStatus(304);
        $response->assertHeader('ETag', '"'.$etag.'"');
        $this->assertEmpty($response->getContent());
    }

    public function test_returns_200_when_if_none_match_does_not_match(): void
    {
        [$website, $page, $release] = $this->createPublishedPage();

        $response = $this->withHeaders([
            'If-None-Match' => '"outdated-etag"',
        ])->get(route('public.sites.pages.show', [
            'website' => $website->slug,
            'page' => $page->slug,
        ]));

        $response->assertOk();
        $this->assertNotEmpty($response->getContent());
    }

    public function test_aborts_404_when_page_is_in_draft_status(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'draft-site']);
        $page = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'slug' => 'draft-page',
            'status' => 'draft',
        ]);

        $response = $this->get(route('public.sites.pages.show', [
            'website' => $website->slug,
            'page' => $page->slug,
        ]));

        $response->assertNotFound();
    }

    public function test_aborts_404_when_website_or_page_slug_does_not_exist(): void
    {
        $response = $this->get(route('public.sites.pages.show', [
            'website' => 'nonexistent-site',
            'page' => 'nonexistent-page',
        ]));

        $response->assertNotFound();
    }
}
