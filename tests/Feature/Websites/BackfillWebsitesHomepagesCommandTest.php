<?php

namespace Tests\Feature\Websites;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillWebsitesHomepagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_actions_without_persisting_changes(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'dry-site']);

        $homePage = Page::factory()->create([
            'website_id' => $website->id,
            'slug' => 'home',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        $this->artisan('websites:backfill-homepages')
            ->expectsOutputToContain('DRY-RUN')
            ->expectsOutputToContain('Total websites processed: 1')
            ->assertSuccessful();

        // No changes made in database
        $this->assertFalse($homePage->fresh()->is_homepage);
        $this->assertEquals(0, Page::where('website_id', $website->id)->where('is_homepage', true)->count());
    }

    public function test_selection_priority_prefers_page_with_home_slug(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'priority-site']);

        // Older page but slug is 'about'
        $aboutPage = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'About',
            'slug' => 'about',
            'is_homepage' => false,
            'created_at' => now()->subDays(5),
            'created_by' => $user->id,
        ]);

        // Newer page with slug 'home'
        $homePage = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'Home',
            'slug' => 'home',
            'is_homepage' => false,
            'created_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $this->artisan('websites:backfill-homepages', ['--commit' => true])
            ->expectsOutputToContain('Assigned existing home page: home')
            ->assertSuccessful();

        $this->assertTrue($homePage->fresh()->is_homepage);
        $this->assertFalse($aboutPage->fresh()->is_homepage);
    }

    public function test_selection_priority_falls_back_to_oldest_page_when_no_home_slug(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'no-home-slug-site']);

        $oldestPage = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'First Page',
            'slug' => 'first',
            'is_homepage' => false,
            'created_at' => now()->subDays(10),
            'created_by' => $user->id,
        ]);

        $newerPage = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'Second Page',
            'slug' => 'second',
            'is_homepage' => false,
            'created_at' => now()->subDays(2),
            'created_by' => $user->id,
        ]);

        $this->artisan('websites:backfill-homepages', ['--commit' => true])
            ->expectsOutputToContain('Assigned oldest page: first')
            ->assertSuccessful();

        $this->assertTrue($oldestPage->fresh()->is_homepage);
        $this->assertFalse($newerPage->fresh()->is_homepage);
    }

    public function test_creates_canonical_home_page_and_revision_when_website_has_zero_pages(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'empty-site']);

        $this->assertEquals(0, Page::where('website_id', $website->id)->count());

        $this->artisan('websites:backfill-homepages', ['--commit' => true])
            ->expectsOutputToContain('Created canonical Home page')
            ->assertSuccessful();

        $pages = Page::where('website_id', $website->id)->get();
        $this->assertCount(1, $pages);

        $createdHome = $pages->first();
        $this->assertEquals('Home', $createdHome->title);
        $this->assertEquals('home', $createdHome->slug);
        $this->assertTrue($createdHome->is_homepage);
        $this->assertEquals(PageStatus::DRAFT, $createdHome->status);
        $this->assertIsArray($createdHome->document_json);

        $revisions = PageRevision::where('page_id', $createdHome->id)->get();
        $this->assertCount(1, $revisions);
        $this->assertEquals(1, $revisions->first()->revision_number);
        $this->assertFalse($revisions->first()->is_autosave);
    }

    public function test_backfill_is_idempotent_on_repeated_committed_runs(): void
    {
        $user = User::factory()->create();
        $website1 = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'site-1']);
        $website2 = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'site-2']);

        Page::factory()->create([
            'website_id' => $website1->id,
            'slug' => 'page-a',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        // First run
        $this->artisan('websites:backfill-homepages', ['--commit' => true])
            ->expectsOutputToContain('Total websites processed: 2')
            ->assertSuccessful();

        // Second run must find zero websites needing backfill
        $this->artisan('websites:backfill-homepages', ['--commit' => true])
            ->expectsOutputToContain('All websites already have a homepage')
            ->assertSuccessful();
    }
}
