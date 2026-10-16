<?php

namespace Tests\Feature\Websites;

use App\Enums\WebsiteRole;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_website_endpoints(): void
    {
        $website = Website::factory()->create();

        $this->get(route('websites.index'))->assertRedirect(route('login'));
        $this->get(route('websites.create'))->assertRedirect(route('login'));
        $this->post(route('websites.store'), ['name' => 'Test', 'slug' => 'test'])->assertRedirect(route('login'));
        $this->get(route('websites.show', $website))->assertRedirect(route('login'));
        $this->put(route('websites.update', $website), ['name' => 'Test', 'slug' => 'test'])->assertRedirect(route('login'));
        $this->delete(route('websites.destroy', $website))->assertRedirect(route('login'));
    }

    public function test_user_can_view_only_websites_they_belong_to(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Website where user is owner
        $ownWebsite = Website::factory()->create(['owner_id' => $user->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $ownWebsite->id,
            'user_id' => $user->id,
            'role' => WebsiteRole::OWNER,
        ]);

        // Website of another user
        $foreignWebsite = Website::factory()->create(['owner_id' => $otherUser->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $foreignWebsite->id,
            'user_id' => $otherUser->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $response = $this->actingAs($user)->get(route('websites.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Websites/Index')
            ->has('websites', 1)
            ->where('websites.0.id', $ownWebsite->id)
        );
    }

    public function test_user_can_create_website_and_owner_membership_is_synchronized(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('websites.store'), [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'owner_id' => 99999, // Attempted spoofing should be ignored
        ]);

        $website = Website::where('slug', 'acme-corp')->first();
        $this->assertNotNull($website);
        $this->assertEquals($user->id, $website->owner_id);

        // Check membership invariant
        $membership = WebsiteMembership::where('website_id', $website->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($membership);
        $this->assertEquals(WebsiteRole::OWNER, $membership->role);

        $response->assertRedirect(route('websites.show', $website));
    }

    public function test_website_slug_must_be_unique(): void
    {
        $user = User::factory()->create();
        Website::factory()->create(['slug' => 'duplicate-slug']);

        $response = $this->actingAs($user)->post(route('websites.store'), [
            'name' => 'Duplicate Site',
            'slug' => 'duplicate-slug',
        ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_owner_can_update_website(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $response = $this->actingAs($user)->put(route('websites.update', $website), [
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
        ]);

        $response->assertRedirect(route('websites.show', $website));
        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
        ]);
    }

    public function test_editor_and_viewer_cannot_update_website(): void
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

        $this->actingAs($editor)
            ->put(route('websites.update', $website), [
                'name' => 'Hacked by Editor',
                'slug' => 'hacked-editor',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('websites.update', $website), [
                'name' => 'Hacked by Viewer',
                'slug' => 'hacked-viewer',
            ])
            ->assertForbidden();
    }

    public function test_unrelated_user_cannot_view_or_mutate_website(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $this->actingAs($stranger)->get(route('websites.show', $website))->assertForbidden();
        $this->actingAs($stranger)->put(route('websites.update', $website), ['name' => 'X', 'slug' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->delete(route('websites.destroy', $website))->assertForbidden();
    }

    public function test_owner_can_delete_website(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $response = $this->actingAs($user)->delete(route('websites.destroy', $website));

        $response->assertRedirect(route('websites.index'));
        $this->assertDatabaseMissing('websites', ['id' => $website->id]);
    }
}
