<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('scope_type', 40);
            $table->string('scope_id', 64);
            $table->string('report_type', 80);
            $table->string('file_path', 500);
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['scope_type', 'scope_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
