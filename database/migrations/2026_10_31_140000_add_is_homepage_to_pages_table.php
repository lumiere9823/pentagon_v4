<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('is_homepage')->default(false);
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX pages_one_homepage_per_website ON pages (website_id) WHERE is_homepage = 1');
        } else {
            DB::statement('CREATE UNIQUE INDEX pages_one_homepage_per_website ON pages (website_id) WHERE is_homepage = true');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pages_one_homepage_per_website');

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('is_homepage');
        });
    }
};
