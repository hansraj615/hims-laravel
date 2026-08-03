import type { ApiEnvelope } from '../types/api'
import type { ConsultationVitals } from './consultations'
import { apiClient } from './client'
import { csrfCookie } from './csrf'

export type OpdQueueStatus = 'waiting' | 'called' | 'skipped' | 'in_consultation' | 'completed'

export type OpdQueueEntry = {
  id: number
  hospital_id: number
  branch_id: number
  appointment_id: number | null
  queue_date: string
  token_number: number
  token_prefix: string | null
  token_code: string
  status: OpdQueueStatus
  vitals: ConsultationVitals | null
  has_vitals: boolean
  vitals_recorded_at: string | null
  vitals_recorded_by: number | null
  called_at: string | null
  started_at: string | null
  completed_at: string | null
  patient: { id: number; uhid: string; name: string; mobile: string | null } | null
  doctor: { id: number; name: string } | null
  department: { id: number; name: string; code: string } | null
  created_at: string
  updated_at: string
}

export type OpdQueueListParams = {
  date?: string
  department_id?: number
  doctor_user_id?: number
  status?: string
}

export async function getOpdQueue(params: OpdQueueListParams = {}) {
  const response = await apiClient.get<ApiEnvelope<OpdQueueEntry[]>>('/opd/queue', { params })
  return response.data
}

export async function callOpdToken(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<OpdQueueEntry>>(`/opd/queue/${id}/call`)
  return response.data
}

export async function startOpdConsultation(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<OpdQueueEntry>>(`/opd/queue/${id}/start`)
  return response.data
}

export async function completeOpdConsultation(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<OpdQueueEntry>>(`/opd/queue/${id}/complete`)
  return response.data
}

export async function skipOpdToken(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<OpdQueueEntry>>(`/opd/queue/${id}/skip`)
  return response.data
}

export async function requeueOpdToken(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<OpdQueueEntry>>(`/opd/queue/${id}/requeue`)
  return response.data
}

export async function updateOpdQueueVitals(id: number, vitals: ConsultationVitals) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<OpdQueueEntry>>(`/opd/queue/${id}/vitals`, { vitals })
  return response.data
}
