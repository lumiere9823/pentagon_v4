<?php

namespace Tests\Feature\Websites;

use App\Enums\WebsiteRole;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_member_as_editor_or_viewer(): void
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create(['email' => 'collaborator@example.com']);

        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $response = $this->actingAs($owner)->post(route('websites.memberships.store', $website), [
            'email' => 'collaborator@example.com',
            'role' => 'editor',
        ]);

        $response->assertRedirect(route('websites.show', $website));
        $this->assertDatabaseHas('website_memberships', [
            'website_id' => $website->id,
            'user_id' => $targetUser->id,
            'role' => WebsiteRole::EDITOR->value,
        ]);
    }

    public function test_cannot_assign_owner_role_via_membership_api(): void
    {
        $owner = User::factory()->create();
        User::factory()->create(['email' => 'hacker@example.com']);

        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $response = $this->actingAs($owner)->post(route('websites.memberships.store', $website), [
            'email' => 'hacker@example.com',
            'role' => 'owner',
        ]);

        $response->assertSessionHasErrors(['role']);
    }

    public function test_cannot_add_same_user_twice_to_website(): void
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create(['email' => 'member@example.com']);

        $website = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);
        WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $targetUser->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        $response = $this->actingAs($owner)->post(route('websites.memberships.store', $website), [
            'email' => 'member@example.com',
            'role' => 'editor',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_editor_and_viewer_cannot_manage_memberships(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $targetUser = User::factory()->create(['email' => 'target@example.com']);

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

        $this->actingAs($editor)
            ->post(route('websites.memberships.store', $website), [
                'email' => 'target@example.com',
                'role' => 'viewer',
            ])
            ->assertForbidden();
    }

    public function test_owner_cannot_demote_primary_owner_membership(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        $ownerMembership = WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $response = $this->actingAs($owner)->put(
            route('websites.memberships.update', [$website, $ownerMembership]),
            ['role' => 'viewer']
        );

        $response->assertForbidden();
    }

    public function test_owner_cannot_delete_primary_owner_membership(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $owner->id]);
        $ownerMembership = WebsiteMembership::factory()->create([
            'website_id' => $website->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        $response = $this->actingAs($owner)->delete(
            route('websites.memberships.destroy', [$website, $ownerMembership])
        );

        $response->assertForbidden();
    }

    public function test_cross_website_membership_mutation_is_rejected_idor_prevention(): void
    {
        $owner = User::factory()->create();

        // Website A
        $websiteA = Website::factory()->create(['owner_id' => $owner->id]);
        WebsiteMembership::factory()->create([
            'website_id' => $websiteA->id,
            'user_id' => $owner->id,
            'role' => WebsiteRole::OWNER,
        ]);

        // Website B with a membership
        $otherOwner = User::factory()->create();
        $memberB = User::factory()->create();
        $websiteB = Website::factory()->create(['owner_id' => $otherOwner->id]);
        $membershipB = WebsiteMembership::factory()->create([
            'website_id' => $websiteB->id,
            'user_id' => $memberB->id,
            'role' => WebsiteRole::VIEWER,
        ]);

        // Attacker attempts to mutate Website B's membership via Website A route
        $this->actingAs($owner)->put(
            route('websites.memberships.update', [$websiteA, $membershipB]),
            ['role' => 'editor']
        )->assertNotFound();

        $this->actingAs($owner)->delete(
            route('websites.memberships.destroy', [$websiteA, $membershipB])
        )->assertNotFound();
    }
}
