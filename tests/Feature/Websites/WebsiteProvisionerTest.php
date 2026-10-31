<?php

namespace Tests\Feature\Websites;

use App\Domains\Websites\Contracts\WebsiteProvisionerInterface;
use App\Domains\Websites\Services\StarterSetCatalog;
use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\WebsiteMembership;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_creates_website_membership_and_starter_pages_atomically(): void
    {
        $owner = User::factory()->create();
        /** @var WebsiteProvisionerInterface $provisioner */
        $provisioner = app(WebsiteProvisionerInterface::class);

        $website = $provisioner->provision($owner, [
            'name' => 'Acme Inc',
            'slug' => 'acme-inc',
        ]);

        $this->assertNotNull($website->id);
        $this->assertEquals('Acme Inc', $website->name);
        $this->assertEquals('acme-inc', $website->slug);
        $this->assertEquals($owner->id, $website->owner_id);

        // 1. Owner membership synchronized
        $membership = WebsiteMembership::where('website_id', $website->id)
            ->where('user_id', $owner->id)
            ->first();
        $this->assertNotNull($membership);
        $this->assertEquals(WebsiteRole::OWNER, $membership->role);

        // 2. Exactly three starter pages created: Home, About, Contact
        $pages = Page::where('website_id', $website->id)->orderBy('id')->get();
        $this->assertCount(3, $pages);

        $home = $pages->firstWhere('slug', 'home');
        $about = $pages->firstWhere('slug', 'about');
        $contact = $pages->firstWhere('slug', 'contact');

        $this->assertNotNull($home);
        $this->assertNotNull($about);
        $this->assertNotNull($contact);

        // Titles and statuses
        $this->assertEquals('Home', $home->title);
        $this->assertEquals('About', $about->title);
        $this->assertEquals('Contact', $contact->title);

        $this->assertEquals(PageStatus::DRAFT, $home->status);
        $this->assertEquals(PageStatus::DRAFT, $about->status);
        $this->assertEquals(PageStatus::DRAFT, $contact->status);

        // Exactly one homepage
        $this->assertTrue($home->is_homepage);
        $this->assertFalse($about->is_homepage);
        $this->assertFalse($contact->is_homepage);

        // Valid document and revision 1 for every starter page
        foreach ([$home, $about, $contact] as $page) {
            $this->assertIsArray($page->document_json);
            $this->assertEquals(1, $page->document_json['schemaVersion']);
            $this->assertNotEmpty($page->document_json['root']['children']);

            $revisions = PageRevision::where('page_id', $page->id)->get();
            $this->assertCount(1, $revisions);

            $rev1 = $revisions->first();
            $this->assertEquals(1, $rev1->revision_number);
            $this->assertEquals(1, $rev1->schema_version);
            $this->assertFalse($rev1->is_autosave);
            $this->assertNotEmpty($rev1->change_summary);
            $this->assertStringNotContainsString('autosave', strtolower($rev1->change_summary));
            $this->assertEquals($owner->id, $rev1->created_by);
            $this->assertEquals(PageRevision::canonicalizeDocument($page->document_json), $rev1->document_hash);
        }
    }

    public function test_provision_rolls_back_completely_on_failure(): void
    {
        $owner = User::factory()->create();

        $mockCatalog = $this->createMock(StarterSetCatalog::class);
        $mockCatalog->method('get')->willThrowException(new Exception('Catalog explosion'));
        $this->app->instance(StarterSetCatalog::class, $mockCatalog);

        /** @var WebsiteProvisionerInterface $provisioner */
        $provisioner = app(WebsiteProvisionerInterface::class);

        try {
            $provisioner->provision($owner, [
                'name' => 'Failing Site',
                'slug' => 'failing-site',
            ]);
            $this->fail('Expected exception was not thrown.');
        } catch (Exception $e) {
            $this->assertEquals('Catalog explosion', $e->getMessage());
        }

        $this->assertDatabaseMissing('websites', ['slug' => 'failing-site']);
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('page_revisions', 0);
        $this->assertDatabaseCount('website_memberships', 0);
    }
}
