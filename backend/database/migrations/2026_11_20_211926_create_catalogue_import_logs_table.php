<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_import_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type', 40); // fitment | oem
            $table->string('source', 200);
            $table->string('format', 40); // json
            $table->string('idempotency_key', 120);
            $table->unsignedInteger('rows_received')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['branch_id', 'type', 'idempotency_key'], 'cil_branch_type_idem_unique');
            $table->index(['branch_id', 'type', 'created_at'], 'cil_branch_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_import_logs');
    }
};
