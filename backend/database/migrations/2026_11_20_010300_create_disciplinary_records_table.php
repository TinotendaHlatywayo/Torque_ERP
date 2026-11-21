<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('incident_date');
            $table->string('category', 40);
            $table->string('severity', 40);
            $table->string('summary', 240);
            $table->text('details')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('action_taken')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'status', 'incident_date']);
            $table->index(['branch_id', 'employee_id', 'incident_date']);
            $table->unique(['id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_records');
    }
};
