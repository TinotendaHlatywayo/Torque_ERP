<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('slug');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('suspended_at')->nullable()->after('approved_by');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('suspension_reason')->nullable()->after('suspended_by');
            $table->timestamp('archived_at')->nullable()->after('suspension_reason');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->unique('host');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['host']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['suspended_by']);
            $table->dropColumn(['status', 'approved_at', 'approved_by', 'suspended_at', 'suspended_by', 'suspension_reason', 'archived_at']);
        });
    }
};
