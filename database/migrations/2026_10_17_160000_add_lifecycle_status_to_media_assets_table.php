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
        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->after('original_filename');
            $table->index(['website_id', 'status']);
        });

        // Backfill existing soft-deleted assets consistently with deleted_at
        DB::table('media_assets')
            ->whereNotNull('deleted_at')
            ->update(['status' => 'pending_deletion']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
