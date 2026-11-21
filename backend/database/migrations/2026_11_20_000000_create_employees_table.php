<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_number', 60);
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('email', 180)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('job_title', 160)->nullable();
            $table->string('department', 160)->nullable();
            $table->string('employment_type', 30)->default('full_time');
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('status', 30)->default('hired');
            $table->string('national_id', 80)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name', 160)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['branch_id', 'employee_number']);
            $table->unique(['branch_id', 'user_id']);
            $table->index(['branch_id', 'status', 'last_name']);
            $table->index(['branch_id', 'department']);
            $table->index(['branch_id', 'email']);

            $table->unique(['id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
