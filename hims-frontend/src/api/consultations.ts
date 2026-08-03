import type { ApiEnvelope } from '../types/api'
import { apiClient } from './client'
import { csrfCookie } from './csrf'

export type ConsultationStatus = 'draft' | 'in_progress' | 'completed'

export type PrescriptionItem = {
  id?: number
  medicine_name: string
  generic_name?: string | null
  formulation?: string | null
  strength?: string | null
  route?: string | null
  frequency?: string | null
  duration?: string | null
  quantity?: number | null
  instructions?: string | null
}

export type ConsultationDiagnosis = {
  code?: string | null
  system?: string | null
  display: string
  type?: string | null
}

export type ConsultationVitals = {
  temperature_c?: number | null
  pulse_bpm?: number | null
  respiratory_rate?: number | null
  bp_systolic?: number | null
  bp_diastolic?: number | null
  spo2_percent?: number | null
  height_cm?: number | null
  weight_kg?: number | null
}

export type Prescription = {
  id: number
  encounter_id: number
  prescription_number: string
  status: string
  prescribed_at: string | null
  items?: PrescriptionItem[]
}

export type Consultation = {
  id: number
  hospital_id: number
  branch_id: number
  patient_id: number
  appointment_id: number | null
  opd_queue_id: number | null
  department_id: number | null
  doctor_user_id: number | null
  encounter_number: string
  encounter_type: string
  status: ConsultationStatus | string
  vitals: ConsultationVitals | null
  chief_complaints: string[] | Record<string, unknown> | null
  diagnoses: ConsultationDiagnosis[] | null
  care_plan: { notes?: string } | null
  started_at: string | null
  completed_at: string | null
  patient: { id: number; uhid: string; name: string; mobile: string | null } | null
  doctor: { id: number; name: string } | null
  department: { id: number; name: string; code: string } | null
  prescriptions?: Prescription[]
  created_at: string
  updated_at: string
}

export type ConsultationStartPayload = {
  opd_queue_id?: number | null
  patient_id?: number | null
  appointment_id?: number | null
  doctor_user_id?: number | null
}

export type ConsultationUpdatePayload = {
  vitals?: ConsultationVitals
  chief_complaints?: string[]
  diagnoses?: ConsultationDiagnosis[]
  care_plan?: { notes?: string }
  prescription_items?: PrescriptionItem[]
}

export type ConsultationListParams = {
  status?: string
  patient_id?: number
  doctor_user_id?: number
  date?: string
}

export function prescriptionItemsFromConsultation(consultation: Consultation): PrescriptionItem[] {
  const first = consultation.prescriptions?.[0]
  return first?.items ?? []
}

export function complaintTextFromConsultation(consultation: Consultation): string {
  const complaints = consultation.chief_complaints
  if (!complaints) {
    return ''
  }
  if (Array.isArray(complaints)) {
    return complaints.join(', ')
  }
  if (typeof complaints === 'object' && 'notes' in complaints) {
    return String((complaints as { notes?: string }).notes ?? '')
  }
  return ''
}

export function diagnosisTextFromConsultation(consultation: Consultation): string {
  return (consultation.diagnoses ?? []).map((item) => item.display).join(', ')
}

export async function getConsultations(params: ConsultationListParams = {}) {
  const response = await apiClient.get<ApiEnvelope<Consultation[]>>('/opd/consultations', { params })
  return response.data
}

export async function getConsultation(id: number) {
  const response = await apiClient.get<ApiEnvelope<Consultation>>(`/opd/consultations/${id}`)
  return response.data
}

export async function createConsultation(payload: ConsultationStartPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Consultation>>('/opd/consultations', payload)
  return response.data
}

export async function updateConsultation(id: number, payload: ConsultationUpdatePayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<Consultation>>(`/opd/consultations/${id}`, payload)
  return response.data
}

export async function completeConsultation(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Consultation>>(`/opd/consultations/${id}/complete`)
  return response.data
}
