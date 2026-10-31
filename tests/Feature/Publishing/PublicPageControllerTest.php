<?php

namespace Tests\Feature\Publishing;

use App\Domains\Publishing\Services\PagePublishingService;
use App\Domains\Websites\Services\HomepageManager;
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

    public function test_serves_active_published_homepage_at_website_root_with_200_and_etag(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'home-site']);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $homePage = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'slug' => 'home',
            'document_json' => $this->createValidDocument('Home Landing Page'),
            'status' => 'draft',
        ]);

        $release = $this->publishingService->publish($homePage, $user);

        // GET /sites/home-site
        $response = $this->get(route('public.sites.root', ['website' => $website->slug]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));

        $etag = $release->manifest_json['html_hash'];
        $response->assertHeader('ETag', '"'.$etag.'"');
        $this->assertStringContainsString('Home Landing Page', $response->getContent());
    }

    public function test_root_returns_304_when_if_none_match_matches_etag(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'home-site-304']);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $homePage = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'slug' => 'home',
            'document_json' => $this->createValidDocument('Home 304'),
            'status' => 'draft',
        ]);

        $release = $this->publishingService->publish($homePage, $user);
        $etag = $release->manifest_json['html_hash'];

        $response = $this->withHeaders([
            'If-None-Match' => '"'.$etag.'"',
        ])->get(route('public.sites.root', ['website' => $website->slug]));

        $response->assertStatus(304);
        $response->assertHeader('ETag', '"'.$etag.'"');
        $this->assertEmpty($response->getContent());
    }

    public function test_aborts_404_when_homepage_is_draft_status(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'draft-root-site']);

        Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'slug' => 'home',
            'status' => 'draft',
        ]);

        $response = $this->get(route('public.sites.root', ['website' => $website->slug]));

        $response->assertNotFound();
    }

    public function test_aborts_404_when_website_has_no_homepage(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'no-home-site']);

        $response = $this->get(route('public.sites.root', ['website' => $website->slug]));

        $response->assertNotFound();
    }

    public function test_request_to_technical_slug_of_current_homepage_permanently_redirects_to_root(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'redirect-site']);

        Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'slug' => 'home',
            'status' => 'published',
        ]);

        $response = $this->get(route('public.sites.pages.show', [
            'website' => $website->slug,
            'page' => 'home',
        ]));

        $response->assertRedirect(route('public.sites.root', ['website' => $website->slug]));
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function test_former_homepage_resumes_serving_ordinary_slug_after_reassignment(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'reassign-site']);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $page1 = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'slug' => 'page-one',
            'document_json' => $this->createValidDocument('Page One Original Home'),
            'status' => 'draft',
        ]);

        $page2 = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
            'slug' => 'page-two',
            'is_homepage' => false,
            'document_json' => $this->createValidDocument('Page Two New Home'),
            'status' => 'draft',
        ]);

        $this->publishingService->publish($page1, $user);
        $this->publishingService->publish($page2, $user);

        // Reassign homepage to page2
        app(HomepageManager::class)->assign($website, $page2, $user);

        // 1. Root serves page2
        $rootResponse = $this->get(route('public.sites.root', ['website' => $website->slug]));
        $rootResponse->assertOk();
        $this->assertStringContainsString('Page Two New Home', $rootResponse->getContent());

        // 2. Technical slug of new homepage (page-two) redirects 301 to root
        $p2Response = $this->get(route('public.sites.pages.show', [
            'website' => $website->slug,
            'page' => 'page-two',
        ]));
        $p2Response->assertRedirect(route('public.sites.root', ['website' => $website->slug]));
        $this->assertEquals(301, $p2Response->getStatusCode());

        // 3. Former homepage (page-one) resumes ordinary slug delivery with 200
        $p1Response = $this->get(route('public.sites.pages.show', [
            'website' => $website->slug,
            'page' => 'page-one',
        ]));
        $p1Response->assertOk();
        $this->assertStringContainsString('Page One Original Home', $p1Response->getContent());
    }
}
