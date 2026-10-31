<?php

namespace Tests\Feature\Websites;

use App\Enums\WebsiteRole;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsiteWithMembers(): array
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();

        $website = Website::factory()->create(['owner_id' => $owner->id]);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $editor->id,
            'role' => WebsiteRole::EDITOR,
        ]);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $viewer->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        return [$website, $owner, $editor, $viewer];
    }

    public function test_owner_can_assign_another_page_as_homepage(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $oldHome = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'title' => 'Home',
            'slug' => 'home',
            'created_by' => $owner->id,
        ]);

        $aboutPage = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'About Us',
            'slug' => 'about',
            'is_homepage' => false,
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->put(
            route('websites.homepage.update', $website),
            ['page_id' => $aboutPage->id]
        );

        $response->assertRedirect(route('websites.show', $website));
        $response->assertSessionHas('success');

        $this->assertFalse($oldHome->fresh()->is_homepage);
        $this->assertTrue($aboutPage->fresh()->is_homepage);
    }

    public function test_assigning_already_current_homepage_is_idempotent(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $home = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'title' => 'Home',
            'slug' => 'home',
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->put(
            route('websites.homepage.update', $website),
            ['page_id' => $home->id]
        );

        $response->assertRedirect(route('websites.show', $website));
        $this->assertTrue($home->fresh()->is_homepage);
    }

    public function test_editor_and_viewer_cannot_assign_homepage(): void
    {
        [$website, $owner, $editor, $viewer] = $this->createWebsiteWithMembers();

        $home = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'title' => 'Home',
            'slug' => 'home',
            'created_by' => $owner->id,
        ]);

        $about = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'About',
            'slug' => 'about',
            'is_homepage' => false,
            'created_by' => $owner->id,
        ]);

        // Editor forbidden
        $this->actingAs($editor)->put(
            route('websites.homepage.update', $website),
            ['page_id' => $about->id]
        )->assertForbidden();

        // Viewer forbidden
        $this->actingAs($viewer)->put(
            route('websites.homepage.update', $website),
            ['page_id' => $about->id]
        )->assertForbidden();

        $this->assertTrue($home->fresh()->is_homepage);
        $this->assertFalse($about->fresh()->is_homepage);
    }

    public function test_cannot_assign_page_from_another_website(): void
    {
        [$websiteA, $ownerA] = $this->createWebsiteWithMembers();
        [$websiteB, $ownerB] = $this->createWebsiteWithMembers();

        $pageB = Page::factory()->create([
            'website_id' => $websiteB->id,
            'title' => 'Foreign Page',
            'slug' => 'foreign',
            'is_homepage' => false,
            'created_by' => $ownerB->id,
        ]);

        $response = $this->actingAs($ownerA)->put(
            route('websites.homepage.update', $websiteA),
            ['page_id' => $pageB->id]
        );

        $response->assertSessionHasErrors(['page_id']);
        $this->assertFalse($pageB->fresh()->is_homepage);
    }

    public function test_cannot_delete_current_homepage(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $home = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'title' => 'Home',
            'slug' => 'home',
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->deleteJson(
            route('websites.pages.destroy', [$website, $home])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        $this->assertDatabaseHas('pages', ['id' => $home->id]);
    }

    public function test_non_homepage_remains_deletable(): void
    {
        [$website, $owner] = $this->createWebsiteWithMembers();

        $home = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'title' => 'Home',
            'slug' => 'home',
            'created_by' => $owner->id,
        ]);

        $about = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'About',
            'slug' => 'about',
            'is_homepage' => false,
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->delete(
            route('websites.pages.destroy', [$website, $about])
        );

        $response->assertRedirect(route('websites.show', $website));
        $this->assertDatabaseMissing('pages', ['id' => $about->id]);
        $this->assertDatabaseHas('pages', ['id' => $home->id]);
    }
}
