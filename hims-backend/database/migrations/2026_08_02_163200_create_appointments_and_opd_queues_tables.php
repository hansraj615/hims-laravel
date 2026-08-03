<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('appointment_number', 50);
            $table->date('appointment_date');
            $table->time('slot_start')->nullable();
            $table->time('slot_end')->nullable();
            $table->string('visit_type', 40)->default('first_visit');
            $table->string('source', 40)->default('walk_in');
            $table->string('priority', 30)->default('normal');
            $table->string('status', 40)->default('booked')->index();
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->string('payment_status', 40)->default('not_billed');
            $table->text('reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'appointment_number']);
            $table->index(['hospital_id', 'branch_id', 'appointment_date', 'status'], 'appointments_worklist_index');
            $table->index(['hospital_id', 'doctor_user_id', 'appointment_date'], 'appointments_doctor_date_index');
            $table->index(['hospital_id', 'patient_id', 'appointment_date'], 'appointments_patient_date_index');
        });

        Schema::create('opd_queues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('queue_date');
            $table->unsignedInteger('token_number');
            $table->string('token_prefix', 20)->nullable();
            $table->string('token_code', 40);
            $table->string('status', 40)->default('waiting')->index();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'branch_id', 'queue_date', 'department_id', 'doctor_user_id', 'token_number'], 'opd_queue_token_unique');
            $table->index(['hospital_id', 'branch_id', 'queue_date', 'status'], 'opd_queue_worklist_index');
            $table->index(['hospital_id', 'patient_id', 'queue_date'], 'opd_queue_patient_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opd_queues');
        Schema::dropIfExists('appointments');
    }
};
