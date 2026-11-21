<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_key', 64);
            $table->string('reference', 128)->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->unique(['provider', 'event_key'], 'webhook_events_provider_event_key_unique');
            $table->index(['provider', 'received_at'], 'webhook_events_provider_received_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
