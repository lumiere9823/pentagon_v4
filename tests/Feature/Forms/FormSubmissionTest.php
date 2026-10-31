<?php

use App\Models\FormSubmission;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    RateLimiter::clear('public-forms');
});

test('public visitors can submit a valid contact form without CSRF session token', function () {
    $website = Website::factory()->create(['slug' => 'test-agency']);
    $page = Page::factory()->create(['website_id' => $website->id, 'slug' => 'contact']);

    $response = $this->postJson("/sites/{$website->slug}/forms/contact", [
        '_page_slug' => 'contact',
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'message' => 'Hello from public site!',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Thank you for your submission.',
        ]);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Pragma'))->toBe('no-cache');

    $this->assertDatabaseHas('form_submissions', [
        'website_id' => $website->id,
        'page_id' => $page->id,
        'form_name' => 'contact',
        'status' => 'new',
    ]);

    /** @var FormSubmission $submission */
    $submission = FormSubmission::where('website_id', $website->id)->firstOrFail();
    expect($submission->payload['name'])->toBe('Alice Smith')
        ->and($submission->payload['email'])->toBe('alice@example.com')
        ->and($submission->payload['message'])->toBe('Hello from public site!');
});

test('it silently drops automated bot submissions filling the honeypot field', function () {
    $website = Website::factory()->create(['slug' => 'bot-protected']);

    $response = $this->postJson("/sites/{$website->slug}/forms/lead", [
        '_hp_verification' => 'I am a spam bot',
        'name' => 'Spam Bot',
        'email' => 'spambot@malicious.com',
        'message' => 'Buy cheap pharmaceuticals!',
    ]);

    // Responds 201 with success message to mislead the bot, but does not persist
    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'id' => null,
        ]);

    $this->assertDatabaseCount('form_submissions', 0);
});

test('it strips raw HTML tags from form fields to prevent stored XSS', function () {
    $website = Website::factory()->create(['slug' => 'xss-test']);

    $response = $this->postJson("/sites/{$website->slug}/forms/contact", [
        'name' => '<b>Hacker</b>',
        'message' => '<script>alert("pwned")</script>Inquiry message',
    ]);

    $response->assertStatus(201);

    /** @var FormSubmission $submission */
    $submission = FormSubmission::where('website_id', $website->id)->firstOrFail();
    expect($submission->payload['name'])->toBe('Hacker')
        ->and($submission->payload['message'])->toBe('Inquiry message');
});

test('it rejects submissions exceeding 64KB with HTTP 422', function () {
    $website = Website::factory()->create(['slug' => 'size-bound']);

    $largeData = str_repeat('X', 70000);

    $response = $this->postJson("/sites/{$website->slug}/forms/upload", [
        'data' => $largeData,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payload']);
});

test('authorized website members can list form submissions in admin endpoint', function () {
    $user = User::factory()->create();
    $website = Website::factory()->create();
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    FormSubmission::factory()->count(3)->create(['website_id' => $website->id]);

    $response = $this->actingAs($user)->getJson("/websites/{$website->id}/submissions");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('unauthorized users cannot access website submissions', function () {
    $unrelatedUser = User::factory()->create();
    $website = Website::factory()->create();

    $response = $this->actingAs($unrelatedUser)->getJson("/websites/{$website->id}/submissions");

    $response->assertStatus(403);
});

test('authorized website members can view submissions via Inertia page', function () {
    $user = User::factory()->create();
    $website = Website::factory()->create(['name' => 'Acme Agency', 'slug' => 'acme']);
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    FormSubmission::factory()->create([
        'website_id' => $website->id,
        'form_name' => 'newsletter',
        'status' => 'new',
    ]);
    FormSubmission::factory()->create([
        'website_id' => $website->id,
        'form_name' => 'contact',
        'status' => 'read',
    ]);

    $response = $this->actingAs($user)->get("/websites/{$website->id}/submissions");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Websites/Submissions')
            ->where('website.id', $website->id)
            ->where('website.name', 'Acme Agency')
            ->where('website.slug', 'acme')
            ->where('userRole', 'editor')
            ->where('can.manage_submissions', true)
            ->where('distinctFormNames', ['contact', 'newsletter'])
            ->has('submissions.data', 2)
        );
});

test('viewers have read-only access to submissions page', function () {
    $viewer = User::factory()->create();
    $website = Website::factory()->create();
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $viewer->id,
        'role' => 'viewer',
    ]);

    FormSubmission::factory()->create(['website_id' => $website->id]);

    $response = $this->actingAs($viewer)->get("/websites/{$website->id}/submissions");

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Websites/Submissions')
            ->where('userRole', 'viewer')
            ->where('can.manage_submissions', false)
            ->has('submissions.data', 1)
        );
});

test('submissions listing can be filtered by form_name and status', function () {
    $user = User::factory()->create();
    $website = Website::factory()->create();
    WebsiteMembership::factory()->create([
        'website_id' => $website->id,
        'user_id' => $user->id,
        'role' => 'editor',
    ]);

    FormSubmission::factory()->create([
        'website_id' => $website->id,
        'form_name' => 'contact',
        'status' => 'new',
    ]);
    FormSubmission::factory()->create([
        'website_id' => $website->id,
        'form_name' => 'contact',
        'status' => 'archived',
    ]);
    FormSubmission::factory()->create([
        'website_id' => $website->id,
        'form_name' => 'newsletter',
        'status' => 'new',
    ]);

    $filteredResponse = $this->actingAs($user)->get("/websites/{$website->id}/submissions?form_name=contact&status=new");

    $filteredResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Websites/Submissions')
            ->where('filters.form_name', 'contact')
            ->where('filters.status', 'new')
            ->has('submissions.data', 1)
        );
});
