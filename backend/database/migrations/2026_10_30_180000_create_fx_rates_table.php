<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->string('source', 80);
            $table->timestamp('effective_at');
            $table->string('rounding_mode', 20)->default('half_up');
            $table->foreignId('entered_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['from_currency', 'to_currency', 'effective_at']);
            $table->index(['branch_id', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
