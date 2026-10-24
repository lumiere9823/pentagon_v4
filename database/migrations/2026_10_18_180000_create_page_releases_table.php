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
        Schema::create('page_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignId('page_revision_id')->constrained('page_revisions')->cascadeOnDelete();
            $table->unsignedInteger('release_number');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('schema_version')->default(1);
            $table->unsignedInteger('renderer_version')->default(1);
            $table->jsonb('manifest_json')->nullable();
            $table->string('directory_path', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'release_number']);
            $table->index(['page_id', 'status']);
        });

        // Partial unique index enforcing maximum of one active release per page
        // Supported natively by both PostgreSQL and SQLite 3.8.0+
        DB::statement("CREATE UNIQUE INDEX page_releases_one_active_per_page ON page_releases (page_id) WHERE status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_releases');
    }
};
