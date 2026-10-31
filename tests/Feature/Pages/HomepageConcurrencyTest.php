<?php

namespace Tests\Feature\Pages;

use App\Domains\Websites\Services\HomepageManager;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class HomepageConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for concurrency verification.');
        }

        config([
            'database.connections.pgsql_worker_2' => config('database.connections.pgsql'),
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::connection('pgsql')->table('page_revisions')->delete();
                DB::connection('pgsql')->table('pages')->delete();
                DB::connection('pgsql')->table('website_memberships')->delete();
                DB::connection('pgsql')->table('websites')->delete();
                DB::connection('pgsql')->table('users')->delete();
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }

        parent::tearDown();
    }

    public function test_concurrent_homepage_assignments_serialize_and_leave_exactly_one_homepage(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        WebsiteMembership::create([
            'website_id' => $website->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $page1 = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'title' => 'Page One',
            'slug' => 'page-one',
            'created_by' => $user->id,
        ]);

        $page2 = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'Page Two',
            'slug' => 'page-two',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        $page3 = Page::factory()->create([
            'website_id' => $website->id,
            'title' => 'Page Three',
            'slug' => 'page-three',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        $manager = app(HomepageManager::class);

        // Assign page2
        $manager->assign($website, $page2, $user);

        // Verify page2 is now homepage
        $this->assertEquals(1, Page::where('website_id', $website->id)->where('is_homepage', true)->count());
        $this->assertTrue($page2->fresh()->is_homepage);
        $this->assertFalse($page1->fresh()->is_homepage);

        // Assign page3
        $manager->assign($website, $page3, $user);

        // Verify page3 is now homepage and exactly one homepage exists
        $this->assertEquals(1, Page::where('website_id', $website->id)->where('is_homepage', true)->count());
        $this->assertTrue($page3->fresh()->is_homepage);
        $this->assertFalse($page2->fresh()->is_homepage);
        $this->assertFalse($page1->fresh()->is_homepage);
    }

    public function test_row_locking_blocks_concurrent_assignment_until_commit(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['owner_id' => $user->id]);

        $page1 = Page::factory()->homepage()->create([
            'website_id' => $website->id,
            'slug' => 'p1',
            'created_by' => $user->id,
        ]);

        $page2 = Page::factory()->create([
            'website_id' => $website->id,
            'slug' => 'p2',
            'is_homepage' => false,
            'created_by' => $user->id,
        ]);

        // Connection 1 begins transaction and locks website
        $conn1 = DB::connection('pgsql');
        $conn1->beginTransaction();
        $conn1->table('websites')->where('id', $website->id)->lockForUpdate()->first();

        // Update homepage on connection 1
        $conn1->table('pages')->where('website_id', $website->id)->where('is_homepage', true)->update(['is_homepage' => false]);
        $conn1->table('pages')->where('id', $page2->id)->update(['is_homepage' => true]);

        // Commit connection 1
        $conn1->commit();

        // Exactly one homepage in the database
        $this->assertEquals(1, Page::where('website_id', $website->id)->where('is_homepage', true)->count());
        $this->assertTrue($page2->fresh()->is_homepage);
        $this->assertFalse($page1->fresh()->is_homepage);
    }
}
