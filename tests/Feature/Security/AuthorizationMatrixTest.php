<?php

use App\Models\FormSubmission;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageTemplate;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;

test('global security headers are delivered on web responses', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

test('unauthenticated guests are redirected to login for protected tenant routes', function () {
    $website = Website::factory()->create();
    $page = Page::factory()->create(['website_id' => $website->id]);

    $this->get("/websites/{$website->id}")->assertRedirect('/login');
    $this->get("/websites/{$website->id}/pages/{$page->id}/edit")->assertRedirect('/login');
    $this->get("/websites/{$website->id}/pages/{$page->id}/builder")->assertRedirect('/login');
    $this->get("/websites/{$website->id}/media")->assertRedirect('/login');
    $this->get("/websites/{$website->id}/submissions")->assertRedirect('/login');
});

test('cross-tenant IDOR attacks return HTTP 404 without leaking resource existence (D-008)', function () {
    $userA = User::factory()->create();
    $websiteA = Website::factory()->create();
    WebsiteMembership::factory()->create([
        'website_id' => $websiteA->id,
        'user_id' => $userA->id,
        'role' => 'owner',
    ]);

    // Website B belongs to someone else
    $websiteB = Website::factory()->create();
    $pageB = Page::factory()->create(['website_id' => $websiteB->id]);
    $mediaB = MediaAsset::factory()->create(['website_id' => $websiteB->id]);
    $templateB = PageTemplate::factory()->create(['website_id' => $websiteB->id]);
    $submissionB = FormSubmission::factory()->create(['website_id' => $websiteB->id]);

    // 1. Cross-tenant Page access
    $this->actingAs($userA)->get("/websites/{$websiteA->id}/pages/{$pageB->id}/edit")->assertStatus(404);
    $this->actingAs($userA)->get("/websites/{$websiteA->id}/pages/{$pageB->id}/builder")->assertStatus(404);
    $this->actingAs($userA)->putJson("/websites/{$websiteA->id}/pages/{$pageB->id}/content", [
        'document_json' => ['schemaVersion' => 1, 'root' => ['id' => 'root', 'type' => 'Container', 'props' => [], 'children' => []]],
        'lock_version' => 1,
    ])->assertStatus(404);

    // 2. Cross-tenant Media access
    $this->actingAs($userA)->putJson("/websites/{$websiteA->id}/media/{$mediaB->id}", [
        'alt_text' => 'Hacked Alt',
    ])->assertStatus(404);
    $this->actingAs($userA)->deleteJson("/websites/{$websiteA->id}/media/{$mediaB->id}")->assertStatus(404);

    // 3. Cross-tenant Template access
    $this->actingAs($userA)->get("/websites/{$websiteA->id}/templates/{$templateB->id}")->assertStatus(404);
    $this->actingAs($userA)->delete("/websites/{$websiteA->id}/templates/{$templateB->id}")->assertStatus(404);

    // 4. Cross-tenant Form Submission access
    $this->actingAs($userA)->patchJson("/websites/{$websiteA->id}/submissions/{$submissionB->id}", [
        'status' => 'read',
    ])->assertStatus(404);
    $this->actingAs($userA)->deleteJson("/websites/{$websiteA->id}/submissions/{$submissionB->id}")->assertStatus(404);
});

test('tenant viewers have read access but are forbidden from mutation operations', function () {
    $viewer = User::factory()->create();
    $website = Website::factory()->create();
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $viewer->id,
        'role' => 'viewer',
    ]);
    $page = Page::factory()->create(['website_id' => $website->id]);
    $submission = FormSubmission::factory()->create([
        'website_id' => $website->id,
        'status' => 'new',
    ]);

    // Viewers retain read-only access to submissions
    $this->actingAs($viewer)
        ->getJson("/websites/{$website->id}/submissions")
        ->assertOk();

    // Viewers cannot mutate submissions
    $this->actingAs($viewer)
        ->patchJson("/websites/{$website->id}/submissions/{$submission->id}", [
            'status' => 'read',
        ])
        ->assertForbidden();
    $this->assertDatabaseHas('form_submissions', [
        'id' => $submission->id,
        'status' => 'new',
    ]);

    $this->actingAs($viewer)
        ->deleteJson("/websites/{$website->id}/submissions/{$submission->id}")
        ->assertForbidden();
    $this->assertDatabaseHas('form_submissions', ['id' => $submission->id]);

    // Viewers cannot save content
    $this->actingAs($viewer)->putJson("/websites/{$website->id}/pages/{$page->id}/content", [
        'document_json' => ['schemaVersion' => 1, 'root' => ['id' => 'root', 'type' => 'Container', 'props' => [], 'children' => []]],
        'lock_version' => 1,
    ])->assertStatus(403);

    // Viewers cannot publish
    $this->actingAs($viewer)->postJson("/websites/{$website->id}/pages/{$page->id}/publish")->assertStatus(403);

    // Viewers cannot delete pages
    $this->actingAs($viewer)->delete("/websites/{$website->id}/pages/{$page->id}")->assertStatus(403);

    // Viewers cannot manage members
    $newUser = User::factory()->create();
    $this->actingAs($viewer)->post("/websites/{$website->id}/memberships", [
        'email' => $newUser->email,
        'role' => 'editor',
    ])->assertStatus(403);
});

test('tenant editors can edit and publish pages but cannot alter memberships or delete website', function () {
    $editor = User::factory()->create();
    $website = Website::factory()->create();
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $editor->id,
        'role' => 'editor',
    ]);
    $page = Page::factory()->create(['website_id' => $website->id]);

    // Editors cannot delete website
    $this->actingAs($editor)->delete("/websites/{$website->id}")->assertStatus(403);

    // Editors cannot add members
    $newUser = User::factory()->create();
    $this->actingAs($editor)->post("/websites/{$website->id}/memberships", [
        'email' => $newUser->email,
        'role' => 'editor',
    ])->assertStatus(403);
});

test('tenant owners and editors can update and delete form submissions', function (string $role) {
    $user = User::factory()->create();
    $website = Website::factory()->create();
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);
    $submissionToUpdate = FormSubmission::factory()->create([
        'website_id' => $website->id,
        'status' => 'new',
    ]);
    $submissionToDelete = FormSubmission::factory()->create([
        'website_id' => $website->id,
    ]);

    $this->actingAs($user)
        ->patchJson("/websites/{$website->id}/submissions/{$submissionToUpdate->id}", [
            'status' => 'read',
        ])
        ->assertOk();
    $this->assertDatabaseHas('form_submissions', [
        'id' => $submissionToUpdate->id,
        'status' => 'read',
    ]);

    $this->actingAs($user)
        ->deleteJson("/websites/{$website->id}/submissions/{$submissionToDelete->id}")
        ->assertOk();
    $this->assertDatabaseMissing('form_submissions', ['id' => $submissionToDelete->id]);
})->with(['owner', 'editor']);
