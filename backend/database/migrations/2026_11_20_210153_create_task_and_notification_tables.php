<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 20)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('event_type', 60)->nullable();
            $table->string('reference_type', 60)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'due_at']);
            $table->index(['branch_id', 'assigned_to', 'status']);
        });

        Schema::create('task_completions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('task_id')->constrained('tasks')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['branch_id', 'task_id', 'created_at']);
        });

        Schema::create('task_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('task_id')->nullable()->constrained('tasks')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->json('payload')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['branch_id', 'event_type', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('event_type', 60);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'user_id', 'channel', 'event_type'], 'notif_pref_unique');
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignUlid('task_id')->nullable()->constrained('tasks')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('event_type', 60);
            $table->string('channel', 20);
            $table->string('recipient_key', 100);
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'event_type', 'recipient_key', 'channel'], 'notif_delivery_unique');
            $table->index(['branch_id', 'user_id', 'status', 'created_at'], 'notif_delivery_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('task_events');
        Schema::dropIfExists('task_completions');
        Schema::dropIfExists('tasks');
    }
};
