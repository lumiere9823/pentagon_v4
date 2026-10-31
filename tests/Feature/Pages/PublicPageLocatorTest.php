<?php

namespace Tests\Feature\Pages;

use App\Domains\Pages\Services\PublicPageLocator;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageLocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_homepage_returns_homepage_or_null(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        $locator = app(PublicPageLocator::class);
        $this->assertNull($locator->findHomepage($website));

        $page = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'slug' => 'home',
            'created_by' => $user->id,
        ]);

        $found = $locator->findHomepage($website);
        $this->assertNotNull($found);
        $this->assertEquals($page->id, $found->id);
    }

    public function test_canonical_path_and_url_for_homepage_and_ordinary_page(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id, 'slug' => 'mysite']);

        $home = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'slug' => 'home',
            'created_by' => $user->id,
        ]);

        $about = Page::factory()->create([
            'website_id' => $website->id,
            'slug' => 'about',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        $locator = app(PublicPageLocator::class);

        // Relative canonical paths
        $this->assertEquals('/sites/mysite', $locator->getCanonicalPath($home, $website));
        $this->assertEquals('/sites/mysite/about', $locator->getCanonicalPath($about, $website));

        // Absolute canonical URLs
        $this->assertEquals(url('/sites/mysite'), $locator->getCanonicalUrl($home, $website));
        $this->assertEquals(url('/sites/mysite/about'), $locator->getCanonicalUrl($about, $website));
    }
}
