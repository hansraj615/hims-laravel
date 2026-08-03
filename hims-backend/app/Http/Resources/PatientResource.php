<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'branch_id' => $this->branch_id,
            'uhid' => $this->uhid,
            'salutation' => $this->salutation,
            'patient_category' => $this->patient_category,
            'registration_source' => $this->registration_source,
            'referred_by' => $this->referred_by,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'blood_group' => $this->blood_group,
            'marital_status' => $this->marital_status,
            'occupation' => $this->occupation,
            'nationality' => $this->nationality,
            'preferred_language' => $this->preferred_language,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age_years' => $this->age_years,
            'age_months' => $this->age_months,
            'age_days' => $this->age_days,
            'mobile' => $this->mobile,
            'alternate_mobile' => $this->alternate_mobile,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'country' => $this->country,
            'identity_type' => $this->identity_type,
            'identity_number' => $this->identity_number,
            'abha_id' => $this->abha_id,
            'abha_number' => $this->abha_number,
            'abha_address' => $this->abha_address,
            'abha_verification_status' => $this->abha_verification_status,
            'abha_verified_at' => $this->abha_verified_at?->toISOString(),
            'abdm_last_transaction_id' => $this->abdm_last_transaction_id,
            'abdm_consent_reference' => $this->abdm_consent_reference,
            'abdm_scan_share_payload' => $this->abdm_scan_share_payload,
            'abdm_profile_payload' => $this->abdm_profile_payload,
            'guardian_name' => $this->guardian_name,
            'guardian_relation' => $this->guardian_relation,
            'guardian_mobile' => $this->guardian_mobile,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_mobile' => $this->emergency_contact_mobile,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'consent_sms' => $this->consent_sms,
            'consent_email' => $this->consent_email,
            'consent_whatsapp' => $this->consent_whatsapp,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'registered_at' => $this->registered_at?->toISOString(),
            'registered_by' => $this->registered_by,
        ];
    }
}
