<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->string('salutation', 20)->nullable()->after('uhid');
            $table->unsignedTinyInteger('age_months')->nullable()->after('age_years');
            $table->unsignedTinyInteger('age_days')->nullable()->after('age_months');
            $table->string('blood_group', 10)->nullable()->after('gender');
            $table->string('marital_status', 30)->nullable()->after('blood_group');
            $table->string('occupation')->nullable()->after('marital_status');
            $table->string('nationality', 80)->nullable()->after('occupation');
            $table->string('preferred_language', 80)->nullable()->after('nationality');
            $table->string('abha_id', 80)->nullable()->after('identity_number');
            $table->string('patient_category', 40)->default('general')->after('branch_id');
            $table->string('registration_source', 40)->default('walk_in')->after('patient_category');
            $table->string('referred_by')->nullable()->after('registration_source');
            $table->string('guardian_name')->nullable()->after('referred_by');
            $table->string('guardian_relation', 80)->nullable()->after('guardian_name');
            $table->string('guardian_mobile', 20)->nullable()->after('guardian_relation');
            $table->string('district', 80)->nullable()->after('city');
            $table->string('country', 80)->nullable()->after('pincode');
            $table->boolean('consent_sms')->default(true)->after('emergency_contact_relation');
            $table->boolean('consent_email')->default(false)->after('consent_sms');
            $table->boolean('consent_whatsapp')->default(false)->after('consent_email');
            $table->text('remarks')->nullable()->after('consent_whatsapp');

            $table->index(['hospital_id', 'patient_category'], 'patients_category_index');
            $table->index(['hospital_id', 'abha_id'], 'patients_abha_index');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex('patients_category_index');
            $table->dropIndex('patients_abha_index');

            $table->dropColumn([
                'salutation',
                'age_months',
                'age_days',
                'blood_group',
                'marital_status',
                'occupation',
                'nationality',
                'preferred_language',
                'abha_id',
                'patient_category',
                'registration_source',
                'referred_by',
                'guardian_name',
                'guardian_relation',
                'guardian_mobile',
                'district',
                'country',
                'consent_sms',
                'consent_email',
                'consent_whatsapp',
                'remarks',
            ]);
        });
    }
};
