<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUlid('employee_id')->nullable()->constrained('employees')->cascadeOnUpdate()->nullOnDelete();
            $table->string('attachable_type', 40);
            $table->ulid('attachable_id');
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['branch_id', 'attachable_type', 'attachable_id'], 'hr_attachments_target_index');
            $table->index(['branch_id', 'employee_id']);
            $table->unique(['id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attachments');
    }
};
