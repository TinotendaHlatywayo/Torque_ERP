<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('public_slug', 180)->nullable();
            $table->text('public_description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->ulid('public_image_id')->nullable();
            $table->string('public_image_alt', 250)->nullable();
            $table->unique(['branch_id', 'public_slug']);
            $table->index(['branch_id', 'is_public', 'is_active', 'name']);
            $table->foreign('public_image_id')->references('id')->on('cms_media')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['public_image_id']);
            $table->dropUnique(['branch_id', 'public_slug']);
            $table->dropIndex(['branch_id', 'is_public', 'is_active', 'name']);
            $table->dropColumn(['public_slug', 'public_description', 'is_public', 'public_image_id', 'public_image_alt']);
        });
    }
};
