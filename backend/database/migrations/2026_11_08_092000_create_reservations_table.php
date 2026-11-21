<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->ulid('product_id');
            $table->ulid('unit_id');
            $table->string('reservation_number', 40);
            $table->string('product_name', 180);
            $table->decimal('quantity', 18, 4);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->text('customer_name');
            $table->text('customer_email')->nullable();
            $table->text('customer_phone')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->string('status', 24)->default('awaiting_payment');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'idempotency_key']);
            $table->unique(['branch_id', 'reservation_number']);
            $table->index(['branch_id', 'status', 'expires_at']);
            $table->index(['branch_id', 'created_at']);
            $table->foreign(['product_id', 'branch_id'])->references(['id', 'branch_id'])->on('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['unit_id', 'branch_id'])->references(['id', 'branch_id'])->on('units')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
