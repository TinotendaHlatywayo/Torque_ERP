<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('device_id', 64);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('operation_type', 40);
            $table->string('operation_id', 64);
            $table->unsignedInteger('payload_version')->default(1);
            $table->string('idempotency_key', 100);
            $table->string('status', 20)->default('queued');
            $table->timestamp('client_time');
            $table->timestamp('server_time')->nullable();
            $table->json('payload');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_class', 40)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'operation_id']);
            $table->unique(['branch_id', 'idempotency_key']);
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
