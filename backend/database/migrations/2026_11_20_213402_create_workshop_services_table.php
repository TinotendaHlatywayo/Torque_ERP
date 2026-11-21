<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->decimal('default_hours', 8, 2)->default(1);
            $table->unsignedBigInteger('default_rate_minor')->default(0);
            $table->string('currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'code'], 'ws_branch_code_uniq');
            $table->index(['branch_id', 'is_active', 'name'], 'ws_branch_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_services');
    }
};
