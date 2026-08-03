<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('hospitals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_group_id')->nullable()->constrained('hospital_groups')->nullOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('code', 30)->unique();
            $table->string('registration_number')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('facility_type', 40)->default('hospital');
            $table->text('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'status']);
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('department_type', 40)->default('clinical');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'branch_id', 'status']);
        });

        Schema::create('user_hospital_branch_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('assignment_type', 40)->default('staff');
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hospital_id', 'branch_id', 'department_id'], 'user_assignment_unique');
            $table->index(['hospital_id', 'branch_id', 'status'], 'user_assignment_tenant_status_index');
            $table->index(['user_id', 'status', 'is_default'], 'user_assignment_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hospital_branch_assignments');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('hospitals');
        Schema::dropIfExists('hospital_groups');
    }
};
