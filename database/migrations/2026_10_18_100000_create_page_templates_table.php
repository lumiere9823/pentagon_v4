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
        Schema::create('page_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->nullable()->constrained('websites')->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('slug', 128);
            $table->text('description')->nullable();
            $table->string('category', 64)->default('general');
            $table->integer('schema_version')->default(1);
            $table->json('document_json');
            $table->string('thumbnail_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('website_id');
        });

        // Partial unique index for website-scoped custom templates
        DB::statement('CREATE UNIQUE INDEX page_templates_website_slug_unique ON page_templates (website_id, slug) WHERE website_id IS NOT NULL');

        // Partial unique index for global starter templates (website_id IS NULL)
        DB::statement('CREATE UNIQUE INDEX page_templates_global_slug_unique ON page_templates (slug) WHERE website_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_templates');
    }
};
