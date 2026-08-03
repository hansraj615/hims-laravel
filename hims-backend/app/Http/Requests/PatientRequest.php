<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => filled($this->input('mobile'))
                ? PhoneNumber::normalizeIndianMobile((string) $this->input('mobile'))
                : null,
            'alternate_mobile' => filled($this->input('alternate_mobile'))
                ? PhoneNumber::normalizeIndianMobile((string) $this->input('alternate_mobile'))
                : null,
            'emergency_contact_mobile' => filled($this->input('emergency_contact_mobile'))
                ? PhoneNumber::normalizeIndianMobile((string) $this->input('emergency_contact_mobile'))
                : null,
            'guardian_mobile' => filled($this->input('guardian_mobile'))
                ? PhoneNumber::normalizeIndianMobile((string) $this->input('guardian_mobile'))
                : null,
        ]);
    }

    public function rules(): array
    {
        $context = app(TenantContext::class);

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('hospital_id', $context->hospitalId()),
            ],
            'salutation' => ['nullable', Rule::in(['mr', 'mrs', 'ms', 'miss', 'master', 'baby', 'dr', 'prof'])],
            'patient_category' => ['required', Rule::in(['general', 'emergency', 'vip', 'staff', 'camp', 'unknown'])],
            'registration_source' => ['required', Rule::in(['walk_in', 'referral', 'online', 'camp', 'transfer'])],
            'referred_by' => ['nullable', 'string', 'max:191'],
            'first_name' => ['required', 'string', 'max:191'],
            'middle_name' => ['nullable', 'string', 'max:191'],
            'last_name' => ['nullable', 'string', 'max:191'],
            'gender' => ['required', Rule::in(['male', 'female', 'other', 'unknown'])],
            'blood_group' => ['nullable', Rule::in(['a_positive', 'a_negative', 'b_positive', 'b_negative', 'ab_positive', 'ab_negative', 'o_positive', 'o_negative', 'unknown'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'widowed', 'divorced', 'separated', 'unknown'])],
            'occupation' => ['nullable', 'string', 'max:191'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'preferred_language' => ['nullable', 'string', 'max:80'],
            'date_of_birth' => ['nullable', 'required_without:age_years', 'date', 'before_or_equal:today'],
            'age_years' => ['nullable', 'required_without:date_of_birth', 'integer', 'min:0', 'max:130'],
            'age_months' => ['nullable', 'integer', 'min:0', 'max:11'],
            'age_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'alternate_mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:80'],
            'identity_type' => ['nullable', Rule::in(['aadhaar', 'passport', 'driving_license', 'voter_id', 'other'])],
            'identity_number' => ['nullable', 'required_with:identity_type', 'string', 'max:80'],
            'abha_id' => ['nullable', 'string', 'max:80'],
            'abha_number' => ['nullable', 'string', 'max:30'],
            'abha_address' => ['nullable', 'string', 'max:191'],
            'abha_verification_status' => ['nullable', Rule::in(['not_verified', 'otp_pending', 'verified', 'failed', 'revoked'])],
            'abha_verified_at' => ['nullable', 'date'],
            'abdm_last_transaction_id' => ['nullable', 'string', 'max:191'],
            'abdm_consent_reference' => ['nullable', 'string', 'max:191'],
            'abdm_scan_share_payload' => ['nullable', 'array'],
            'abdm_profile_payload' => ['nullable', 'array'],
            'guardian_name' => ['nullable', 'string', 'max:191'],
            'guardian_relation' => ['nullable', 'string', 'max:80'],
            'guardian_mobile' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_mobile' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:80'],
            'consent_sms' => ['required', 'boolean'],
            'consent_email' => ['required', 'boolean'],
            'consent_whatsapp' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'deceased'])],
        ];
    }
}
