import type { ApiEnvelope } from '../types/api'
import type { Invoice } from './billing'
import { apiClient } from './client'
import { csrfCookie } from './csrf'

export type Ward = {
  id: number
  hospital_id: number
  branch_id: number
  name: string
  code: string
  ward_type: string
  status: string
}

export type AdmissionStatus = 'admitted' | 'discharged' | 'lama' | 'dopr' | 'deceased'

export type DischargeOutcome = 'discharge' | 'lama' | 'dopr' | 'death'

export type Admission = {
  id: number
  hospital_id: number
  branch_id: number
  admission_number: string
  patient_id: number
  admitting_doctor_user_id: number | null
  department_id: number | null
  ward_id: number
  bed_id: number
  admitted_at: string
  provisional_diagnosis: string | null
  attendant_name: string | null
  attendant_mobile: string | null
  attendant_relation: string | null
  status: AdmissionStatus | string
  discharge_outcome: DischargeOutcome | string | null
  discharged_at: string | null
  discharge_summary: string | null
  discharge_package: {
    documents?: Array<{ id: number; document_type: string; title: string }>
    diagnostic_orders?: Array<{ id: number; order_number: string; category: string; status: string }>
    counts?: { documents: number; diagnostic_orders: number }
  } | null
  death_at: string | null
  invoice_id: number | null
  discharge_document_id: number | null
  clearances?: AdmissionClearance[]
  patient?: { id: number; uhid: string; name: string; mobile: string | null } | null
  ward?: Ward | null
  bed?: { id: number; bed_number: string; bed_type: string; status: string } | null
  admitting_doctor?: { id: number; name: string } | null
}

export type BedBoardEntry = {
  id: number
  hospital_id: number
  branch_id: number
  ward_id: number
  bed_number: string
  bed_type: string
  status: 'available' | 'occupied' | 'blocked' | 'maintenance' | string
  current_admission_id: number | null
  ward?: Ward | null
  current_admission?: Admission | null
}

export type AdmitPayload = {
  patient_id: number
  bed_id: number
  admitting_doctor_user_id?: number
  department_id?: number
  provisional_diagnosis?: string
  attendant_name?: string
  attendant_mobile?: string
  attendant_relation?: string
}

export type DischargePayload = {
  outcome: DischargeOutcome
  discharge_summary: string
  death_at?: string
  create_invoice?: boolean
}

export async function getWards() {
  const response = await apiClient.get<ApiEnvelope<Ward[]>>('/ipd/wards')
  return response.data
}

export async function getBedBoard(params: { ward_id?: number; status?: string } = {}) {
  const response = await apiClient.get<ApiEnvelope<BedBoardEntry[]>>('/ipd/beds/board', { params })
  return response.data
}

export async function getAdmissions(params: { status?: string; patient_id?: number } = {}) {
  const response = await apiClient.get<ApiEnvelope<Admission[]>>('/ipd/admissions', { params })
  return response.data
}

export async function admitPatient(payload: AdmitPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Admission>>('/ipd/admissions', payload)
  return response.data
}

export async function transferAdmission(id: number, payload: { bed_id: number; reason?: string }) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Admission>>(`/ipd/admissions/${id}/transfer`, payload)
  return response.data
}

export async function dischargeAdmission(id: number, payload: DischargePayload) {
  await csrfCookie()
  const response = await apiClient.post<
    ApiEnvelope<{ admission: Admission; invoice: Invoice | null }>
  >(`/ipd/admissions/${id}/discharge`, payload)
  return response.data
}

export type NursingNote = {
  id: number
  admission_id: number
  recorded_at: string
  vitals: Record<string, number | string | null> | null
  notes: string | null
  recorded_by?: { id: number; name: string } | null
}

export type IpdChargeLine = {
  id: number
  admission_id: number
  service_id: number | null
  charge_date: string
  description: string
  source: string
  quantity: number | string
  unit_rate: number | string
  amount: number | string
  status: string
  invoice_id: number | null
}

export type AdmissionClearance = {
  id: number
  admission_id: number
  clearance_type: 'nursing' | 'diagnostics' | 'billing' | 'ward' | string
  status: 'pending' | 'cleared' | 'waived' | string
  notes: string | null
  cleared_at: string | null
  cleared_by?: { id: number; name: string } | null
}

export async function getNursingNotes(admissionId: number) {
  const response = await apiClient.get<ApiEnvelope<NursingNote[]>>(`/ipd/admissions/${admissionId}/nursing-notes`)
  return response.data
}

export async function addNursingNote(
  admissionId: number,
  payload: { notes?: string; vitals?: Record<string, number | null>; recorded_at?: string },
) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<NursingNote>>(`/ipd/admissions/${admissionId}/nursing-notes`, payload)
  return response.data
}

export async function getIpdCharges(admissionId: number) {
  const response = await apiClient.get<ApiEnvelope<IpdChargeLine[]>>(`/ipd/admissions/${admissionId}/charges`)
  return response.data
}

export async function postDailyIpdCharges(admissionId: number) {
  await csrfCookie()
  const response = await apiClient.post<
    ApiEnvelope<{ created_count: number; charges: IpdChargeLine[] }>
  >(`/ipd/admissions/${admissionId}/charges/daily`)
  return response.data
}

export async function getClearances(admissionId: number) {
  const response = await apiClient.get<ApiEnvelope<AdmissionClearance[]>>(`/ipd/admissions/${admissionId}/clearances`)
  return response.data
}

export async function updateClearance(
  admissionId: number,
  payload: { clearance_type: string; status: 'cleared' | 'waived' | 'pending'; notes?: string },
) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AdmissionClearance>>(
    `/ipd/admissions/${admissionId}/clearances`,
    payload,
  )
  return response.data
}
