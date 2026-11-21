<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_maintenances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches', 'id', 'amt_branch_fk')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets', 'id', 'amt_asset_fk')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('performed_date');
            $table->unsignedBigInteger('cost_minor');
            $table->string('currency_code', 3);
            $table->string('provider', 180);
            $table->string('description', 500);
            $table->foreignUlid('expense_id')->nullable()->constrained('expenses', 'id', 'amt_expense_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignUlid('payment_id')->nullable()->constrained('money_movements', 'id', 'amt_payment_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users', 'id', 'amt_actor_fk')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'asset_id', 'performed_date'], 'amt_asset_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
    }
};
