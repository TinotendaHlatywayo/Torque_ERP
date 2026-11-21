<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_media', function (Blueprint $table) {
            $table->string('preferred_variant', 40)->nullable()->after('variants');
        });
    }

    public function down(): void
    {
        Schema::table('cms_media', function (Blueprint $table) {
            $table->dropColumn('preferred_variant');
        });
    }
};
