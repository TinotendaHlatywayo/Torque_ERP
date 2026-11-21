<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->json('content');
            $table->json('published_content')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('published_version')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_site_contents');
    }
};
