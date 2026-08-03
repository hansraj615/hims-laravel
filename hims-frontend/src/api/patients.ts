import type { ApiEnvelope } from '../types/api'
import { csrfCookie } from './csrf'
import { apiClient } from './client'

export type Patient = {
  id: number
  hospital_id: number
  branch_id: number | null
  uhid: string
  salutation: 'mr' | 'mrs' | 'ms' | 'miss' | 'master' | 'baby' | 'dr' | 'prof' | null
  patient_category: 'general' | 'emergency' | 'vip' | 'staff' | 'camp' | 'unknown'
  registration_source: 'walk_in' | 'referral' | 'online' | 'camp' | 'transfer'
  referred_by: string | null
  first_name: string
  middle_name: string | null
  last_name: string | null
  full_name: string
  gender: 'male' | 'female' | 'other' | 'unknown'
  blood_group:
    | 'a_positive'
    | 'a_negative'
    | 'b_positive'
    | 'b_negative'
    | 'ab_positive'
    | 'ab_negative'
    | 'o_positive'
    | 'o_negative'
    | 'unknown'
    | null
  marital_status: 'single' | 'married' | 'widowed' | 'divorced' | 'separated' | 'unknown' | null
  occupation: string | null
  nationality: string | null
  preferred_language: string | null
  date_of_birth: string | null
  age_years: number | null
  age_months: number | null
  age_days: number | null
  mobile: string | null
  alternate_mobile: string | null
  email: string | null
  address: string | null
  city: string | null
  district: string | null
  state: string | null
  pincode: string | null
  country: string | null
  identity_type: 'aadhaar' | 'passport' | 'driving_license' | 'voter_id' | 'other' | null
  identity_number: string | null
  abha_id: string | null
  guardian_name: string | null
  guardian_relation: string | null
  guardian_mobile: string | null
  emergency_contact_name: string | null
  emergency_contact_mobile: string | null
  emergency_contact_relation: string | null
  consent_sms: boolean
  consent_email: boolean
  consent_whatsapp: boolean
  remarks: string | null
  status: 'active' | 'inactive' | 'deceased'
  registered_at: string | null
  registered_by: number | null
}

export type PatientPayload = {
  branch_id?: number | null
  salutation?: Patient['salutation']
  patient_category: Patient['patient_category']
  registration_source: Patient['registration_source']
  referred_by?: string | null
  first_name: string
  middle_name?: string | null
  last_name?: string | null
  gender: Patient['gender']
  blood_group?: Patient['blood_group']
  marital_status?: Patient['marital_status']
  occupation?: string | null
  nationality?: string | null
  preferred_language?: string | null
  date_of_birth?: string | null
  age_years?: number | null
  age_months?: number | null
  age_days?: number | null
  mobile?: string | null
  alternate_mobile?: string | null
  email?: string | null
  address?: string | null
  city?: string | null
  district?: string | null
  state?: string | null
  pincode?: string | null
  country?: string | null
  identity_type?: Patient['identity_type']
  identity_number?: string | null
  abha_id?: string | null
  guardian_name?: string | null
  guardian_relation?: string | null
  guardian_mobile?: string | null
  emergency_contact_name?: string | null
  emergency_contact_mobile?: string | null
  emergency_contact_relation?: string | null
  consent_sms: boolean
  consent_email: boolean
  consent_whatsapp: boolean
  remarks?: string | null
  status: Patient['status']
}

export type PatientDuplicateParams = {
  mobile?: string | null
  name?: string | null
  identity_type?: Patient['identity_type']
  identity_number?: string | null
  abha_id?: string | null
}

export type PatientDocument = {
  id: number
  patient_id: number
  document_type: string
  title: string
  file_path: string | null
  mime_type: string | null
  file_size: number | null
  metadata: Record<string, unknown> | null
  uploaded_by: number | null
  created_at: string
}

export type PatientDocumentPayload = {
  document_type: string
  title: string
  file_path?: string | null
  mime_type?: string | null
  file_size?: number | null
  metadata?: Record<string, unknown> | null
}

export async function getPatients() {
  const response = await apiClient.get<ApiEnvelope<Patient[]>>('/patients')
  return response.data
}

export async function getPatient(id: number) {
  const response = await apiClient.get<ApiEnvelope<Patient>>(`/patients/${id}`)
  return response.data
}

export async function getPatientDocuments(patientId: number) {
  const response = await apiClient.get<ApiEnvelope<PatientDocument[]>>(`/patients/${patientId}/documents`)
  return response.data
}

export async function addPatientDocument(patientId: number, payload: PatientDocumentPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<PatientDocument>>(`/patients/${patientId}/documents`, payload)
  return response.data
}

export async function getPatientDuplicates(params: PatientDuplicateParams) {
  const response = await apiClient.get<ApiEnvelope<Patient[]>>('/patients/duplicates', { params })
  return response.data
}

export async function createPatient(payload: PatientPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Patient>>('/patients', payload)
  return response.data
}

export async function updatePatient(id: number, payload: PatientPayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<Patient>>(`/patients/${id}`, payload)
  return response.data
}

export type ClinicalHistoryEncounter = {
  id: number
  date: string | null
  encounter_number: string
  status: string
  encounter_type: string
  doctor: { id: number; name: string } | null
  department: { id: number; name: string; code: string } | null
  vitals_summary: {
    temperature_c?: number | null
    pulse_bpm?: number | null
    bp_systolic?: number | null
    bp_diastolic?: number | null
    spo2_percent?: number | null
  }
  chief_complaints: string[]
  diagnoses: Array<{ display: string | null; code: string | null; system: string | null }>
  prescription_items: Array<{
    medicine_name: string
    strength: string | null
    route: string | null
    frequency: string | null
    duration: string | null
    instructions: string | null
  }>
  care_plan_notes: string | null
}

export type ClinicalHistoryResponse = {
  patient_id: number
  uhid: string
  encounters: ClinicalHistoryEncounter[]
}

export async function getPatientClinicalHistory(
  patientId: number,
  params: { exclude_encounter_id?: number; limit?: number; status?: string } = {},
) {
  const response = await apiClient.get<ApiEnvelope<ClinicalHistoryResponse>>(
    `/patients/${patientId}/clinical-history`,
    { params },
  )
  return response.data
}
