<?php

namespace Tests\Feature\Pages;

use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_is_homepage_defaults_to_false_and_casts_to_boolean(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        $page = Page::factory()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
        ]);

        $this->assertFalse($page->is_homepage);
        $this->assertIsBool($page->is_homepage);

        $fresh = $page->fresh();
        $this->assertFalse($fresh->is_homepage);
        $this->assertIsBool($fresh->is_homepage);
    }

    public function test_homepage_factory_state_sets_is_homepage_to_true(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        $page = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'created_by' => $user->id,
        ]);

        $this->assertTrue($page->is_homepage);
        $this->assertTrue($page->fresh()->is_homepage);
    }

    public function test_partial_unique_index_rejects_multiple_homepages_for_same_website(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        // First homepage succeeds
        Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'slug' => 'home-one',
            'created_by' => $user->id,
        ]);

        // Second homepage on the same website must fail with QueryException (unique constraint)
        $this->expectException(QueryException::class);

        Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'slug' => 'home-two',
            'created_by' => $user->id,
        ]);
    }

    public function test_partial_unique_index_allows_multiple_non_homepage_pages_on_same_website(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        $page1 = Page::factory()->create([
            'website_id' => $website->id,
            'slug' => 'about',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        $page2 = Page::factory()->create([
            'website_id' => $website->id,
            'slug' => 'contact',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        $page3 = Page::factory()->create([
            'website_id' => $website->id,
            'slug' => 'pricing',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseCount('pages', 3);
        $this->assertFalse($page1->fresh()->is_homepage);
        $this->assertFalse($page2->fresh()->is_homepage);
        $this->assertFalse($page3->fresh()->is_homepage);
    }

    public function test_partial_unique_index_allows_different_websites_to_each_have_one_homepage(): void
    {
        $user = User::factory()->create();
        $websiteA = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'site-a']);
        $websiteB = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'site-b']);

        $pageA = Page::factory()->homepage()->create([
            'website_id' => $websiteA->id,
            'slug' => 'home',
            'created_by' => $user->id,
        ]);

        $pageB = Page::factory()->homepage()->create([
            'website_id' => $websiteB->id,
            'slug' => 'home',
            'created_by' => $user->id,
        ]);

        $this->assertTrue($pageA->fresh()->is_homepage);
        $this->assertTrue($pageB->fresh()->is_homepage);
    }
}
