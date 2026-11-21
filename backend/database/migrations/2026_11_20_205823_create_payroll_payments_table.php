<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('payroll_run_id')->constrained('payroll_runs')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('payment_account_id')->constrained('payment_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('money_movement_id')->nullable()->constrained('money_movements')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->dateTime('paid_at');
            $table->string('method', 40);
            $table->string('reference', 120)->nullable();
            $table->string('status', 20)->default('recorded');
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'idempotency_key']);
            $table->unique(['id', 'branch_id']);
            $table->index(['branch_id', 'payroll_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
    }
};
