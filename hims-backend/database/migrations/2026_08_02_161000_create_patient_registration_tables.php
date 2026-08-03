<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->default(0);
            $table->string('name', 50);
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['hospital_id', 'branch_id', 'name']);
        });

        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('uhid', 40);
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender', 20);
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('age_years')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('alternate_mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('identity_type', 40)->nullable();
            $table->string('identity_number', 80)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_mobile', 20)->nullable();
            $table->string('emergency_contact_relation', 80)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'uhid']);
            $table->index(['hospital_id', 'branch_id', 'status']);
            $table->index(['hospital_id', 'mobile']);
            $table->index(['hospital_id', 'identity_type', 'identity_number'], 'patients_identity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
        Schema::dropIfExists('sequence_counters');
    }
};
