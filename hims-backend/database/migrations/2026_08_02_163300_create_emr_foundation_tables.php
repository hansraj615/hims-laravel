<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('opd_queue_id')->nullable()->constrained('opd_queues')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('encounter_number', 50);
            $table->string('encounter_type', 40)->default('opd');
            $table->string('status', 40)->default('draft')->index();
            $table->json('vitals')->nullable();
            $table->json('chief_complaints')->nullable();
            $table->json('clinical_history')->nullable();
            $table->json('examination')->nullable();
            $table->json('diagnoses')->nullable();
            $table->json('care_plan')->nullable();
            $table->json('follow_up')->nullable();
            $table->json('fhir_payload')->nullable();
            $table->string('fhir_version', 20)->default('4.0.1');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'encounter_number']);
            $table->index(['hospital_id', 'branch_id', 'status'], 'encounters_tenant_status_index');
            $table->index(['hospital_id', 'patient_id', 'created_at'], 'encounters_patient_timeline_index');
            $table->index(['hospital_id', 'doctor_user_id', 'status'], 'encounters_doctor_status_index');
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('prescribed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prescription_number', 50);
            $table->string('status', 40)->default('draft')->index();
            $table->json('fhir_payload')->nullable();
            $table->timestamp('prescribed_at')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'prescription_number']);
            $table->index(['hospital_id', 'patient_id', 'prescribed_at'], 'prescriptions_patient_index');
            $table->index(['hospital_id', 'encounter_id'], 'prescriptions_encounter_index');
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->string('medicine_name');
            $table->string('generic_name')->nullable();
            $table->string('formulation')->nullable();
            $table->string('strength')->nullable();
            $table->string('route', 40)->nullable();
            $table->string('frequency', 80)->nullable();
            $table->string('duration', 80)->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_schedule_h')->default(false);
            $table->boolean('is_schedule_h1')->default(false);
            $table->json('cdsco_metadata')->nullable();
            $table->json('fhir_payload')->nullable();
            $table->timestamps();

            $table->index(['prescription_id', 'medicine_name'], 'prescription_items_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('encounters');
    }
};
