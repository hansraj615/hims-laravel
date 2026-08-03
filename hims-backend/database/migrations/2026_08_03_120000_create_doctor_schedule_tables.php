<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday … 6=Saturday (Carbon)
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(15);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['hospital_id', 'doctor_user_id', 'day_of_week', 'status'], 'doctor_schedules_lookup_idx');
        });

        Schema::create('doctor_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['hospital_id', 'doctor_user_id', 'start_date', 'end_date'], 'doctor_leaves_range_idx');
        });

        Schema::create('doctor_fee_masters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('visit_type', 30);
            $table->decimal('fee_amount', 12, 2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['hospital_id', 'doctor_user_id', 'visit_type'], 'doctor_fee_masters_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_fee_masters');
        Schema::dropIfExists('doctor_leaves');
        Schema::dropIfExists('doctor_schedules');
    }
};
