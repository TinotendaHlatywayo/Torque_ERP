<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('reservation_id')->unique()->constrained('reservations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('provider', 32)->default('paynow');
            $table->string('merchant_reference', 64)->unique();
            $table->string('provider_reference', 100)->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->string('status', 32)->default('initiating');
            $table->text('poll_url')->nullable();
            $table->text('redirect_url')->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_payments');
    }
};
