<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 120);
            $table->string('slug', 160)->unique();
            $table->unsignedInteger('current_version')->default(0);
            $table->unsignedInteger('published_version')->nullable();
            $table->string('published_slug', 160)->nullable()->unique();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('cms_page_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cms_page_id')->constrained('cms_pages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 120);
            $table->string('slug', 160);
            $table->json('sections');
            $table->json('media_ids');
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['cms_page_id', 'version']);
            $table->index(['slug', 'cms_page_id']);
        });

        Schema::create('cms_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('original_name', 255);
            $table->string('original_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->json('variants');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_page_versions');
        Schema::dropIfExists('cms_pages');
    }
};
