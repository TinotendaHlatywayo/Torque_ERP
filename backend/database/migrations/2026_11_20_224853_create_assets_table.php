<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches', 'id', 'as_branch_fk')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->date('acquisition_date');
            $table->unsignedBigInteger('cost_minor');
            $table->string('currency_code', 3);
            $table->string('location', 180);
            $table->string('custodian', 180);
            $table->string('status', 20)->default('active');
            $table->string('source_type', 30)->nullable();
            $table->string('source_id', 36)->nullable();
            $table->date('disposal_date')->nullable();
            $table->unsignedBigInteger('disposal_proceeds_minor')->nullable();
            $table->text('disposal_notes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users', 'id', 'as_actor_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'source_type', 'source_id'], 'as_source_uniq');
            $table->index(['branch_id', 'status', 'acquisition_date'], 'as_branch_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
