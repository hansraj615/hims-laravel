<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->string('abha_number', 30)->nullable()->after('abha_id');
            $table->string('abha_address')->nullable()->after('abha_number');
            $table->string('abha_verification_status', 30)->default('not_verified')->after('abha_address');
            $table->timestamp('abha_verified_at')->nullable()->after('abha_verification_status');
            $table->string('abdm_last_transaction_id')->nullable()->after('abha_verified_at');
            $table->string('abdm_consent_reference')->nullable()->after('abdm_last_transaction_id');
            $table->json('abdm_scan_share_payload')->nullable()->after('abdm_consent_reference');
            $table->json('abdm_profile_payload')->nullable()->after('abdm_scan_share_payload');

            $table->index(['hospital_id', 'abha_number'], 'patients_abha_number_index');
            $table->index(['hospital_id', 'abha_address'], 'patients_abha_address_index');
            $table->index(['hospital_id', 'abha_verification_status'], 'patients_abha_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex('patients_abha_number_index');
            $table->dropIndex('patients_abha_address_index');
            $table->dropIndex('patients_abha_status_index');

            $table->dropColumn([
                'abha_number',
                'abha_address',
                'abha_verification_status',
                'abha_verified_at',
                'abdm_last_transaction_id',
                'abdm_consent_reference',
                'abdm_scan_share_payload',
                'abdm_profile_payload',
            ]);
        });
    }
};
