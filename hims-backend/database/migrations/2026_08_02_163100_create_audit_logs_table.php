<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 80);
            $table->string('event', 80);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 80)->nullable();
            $table->string('previous_hash', 128)->nullable();
            $table->string('hash', 128)->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['hospital_id', 'branch_id', 'module', 'occurred_at'], 'audit_logs_tenant_module_index');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index(['user_id', 'occurred_at'], 'audit_logs_user_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
