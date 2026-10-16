<?php

namespace Tests\Feature\Pages;

use App\Enums\PageStatus;
use App\Enums\WebsiteRole;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_editor_can_create_page(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();

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

        // Editor creates a page
        $response = $this->actingAs($editor)->post(route('websites.pages.store', $website), [
            'title' => 'About Us',
            'slug' => 'about-us',
            'website_id' => 99999, // Attempted IDOR injection should be ignored
        ]);

        $response->assertRedirect(route('websites.show', $website));
        $this->assertDatabaseHas('pages', [
            'website_id' => $website->id,
            'title' => 'About Us',
            'slug' => 'about-us',
            'status' => PageStatus::DRAFT->value,
            'created_by' => $editor->id,
        ]);
    }

    public function test_viewer_cannot_create_or_mutate_pages(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $viewer->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        $page = Page::factory()->create(['website_id' => $website->id]);

        $this->actingAs($viewer)
            ->post(route('websites.pages.store', $website), ['title' => 'X', 'slug' => 'x'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('websites.pages.update', [$website, $page]), [
                'title' => 'Updated by Viewer',
                'slug' => 'updated-viewer',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('websites.pages.destroy', [$website, $page]))
            ->assertForbidden();
    }

    public function test_slug_must_be_unique_within_the_same_website(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        Page::factory()->create([
            'website_id' => $website->id,
            'slug' => 'contact',
        ]);

        $response = $this->actingAs($owner)->post(route('websites.pages.store', $website), [
            'title' => 'Contact Us Again',
            'slug' => 'contact',
        ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_same_slug_in_different_websites_is_permitted(): void
    {
        $owner = User::factory()->create();

        $websiteA = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $websiteA->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $websiteB = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $websiteB->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        // Create page with slug 'pricing' on website A
        Page::factory()->create([
            'website_id' => $websiteA->id,
            'slug' => 'pricing',
        ]);

        // Create page with same slug 'pricing' on website B
        $response = $this->actingAs($owner)->post(route('websites.pages.store', $websiteB), [
            'title' => 'Pricing for B',
            'slug' => 'pricing',
        ]);

        $response->assertRedirect(route('websites.show', $websiteB));
        $this->assertDatabaseHas('pages', [
            'website_id' => $websiteB->id,
            'slug' => 'pricing',
        ]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);
        $page = Page::factory()->create(['website_id' => $website->id]);

        $response = $this->actingAs($owner)->put(route('websites.pages.update', [$website, $page]), [
            'title' => 'Updated',
            'slug' => 'updated',
            'status' => 'invalid_status_value',
        ]);

        $response->assertSessionHasErrors(['status']);
    }

    public function test_cross_website_page_mutation_is_rejected_idor_prevention(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $websiteA = Website::factory()->create(['owner_id' => $ownerA->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $websiteA->id,
            'user_id' => $ownerA->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $websiteB = Website::factory()->create(['owner_id' => $ownerB->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $websiteB->id,
            'user_id' => $ownerB->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $pageB = Page::factory()->create(['website_id' => $websiteB->id]);

        // Attacker attempts to edit page of Website B via Website A route
        $this->actingAs($ownerA)
            ->get(route('websites.pages.edit', [$websiteA, $pageB]))
            ->assertNotFound();

        // Attacker attempts to update page of Website B via Website A route
        $this->actingAs($ownerA)
            ->put(route('websites.pages.update', [$websiteA, $pageB]), [
                'title' => 'Hacked',
                'slug' => 'hacked',
                'status' => 'draft',
            ])
            ->assertNotFound();

        // Attacker attempts to delete page of Website B via Website A route
        $this->actingAs($ownerA)
            ->delete(route('websites.pages.destroy', [$websiteA, $pageB]))
            ->assertNotFound();
    }
}
