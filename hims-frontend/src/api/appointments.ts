import type { ApiEnvelope } from '../types/api'
import { apiClient } from './client'
import { csrfCookie } from './csrf'

export type AppointmentStatus = 'booked' | 'confirmed' | 'checked_in' | 'cancelled' | 'no_show' | 'completed'

export type AppointmentPartyRef = {
  id: number
  uhid?: string
  name: string
  mobile?: string | null
}

export type Appointment = {
  id: number
  hospital_id: number
  branch_id: number | null
  appointment_number: string
  appointment_date: string
  slot_start: string | null
  slot_end: string | null
  visit_type: 'first_visit' | 'follow_up' | 'emergency'
  source: 'walk_in' | 'phone' | 'online' | 'referral'
  priority: 'normal' | 'urgent' | 'vip'
  status: AppointmentStatus
  fee_amount: number | string
  payment_status: string
  reason: string | null
  cancellation_reason: string | null
  checked_in_at: string | null
  patient: AppointmentPartyRef | null
  doctor: { id: number; name: string } | null
  department: { id: number; name: string; code: string } | null
  created_by: number | null
  updated_by: number | null
  created_at: string
  updated_at: string
}

export type AppointmentPayload = {
  branch_id?: number | null
  patient_id: number
  department_id?: number | null
  doctor_user_id?: number | null
  appointment_date: string
  slot_start?: string | null
  slot_end?: string | null
  visit_type?: Appointment['visit_type']
  source?: Appointment['source']
  priority?: Appointment['priority']
  fee_amount?: number | null
  reason?: string | null
}

export type AppointmentListParams = {
  date?: string
  status?: string
  patient_id?: number
  doctor_user_id?: number
  branch_id?: number
}

export type AppointmentBookingOptions = {
  departments: Array<{ id: number; name: string; code: string; branch_id: number | null }>
  doctors: Array<{ id: number; name: string; email: string }>
}

export type AppointmentSlot = {
  slot_start: string
  slot_end: string
  available: boolean
}

export type AppointmentSlotsResponse = {
  slots: AppointmentSlot[]
  on_leave: boolean
  leave_reason: string | null
  fee_amount: string | number | null
}

export async function getAppointments(params: AppointmentListParams = {}) {
  const response = await apiClient.get<ApiEnvelope<Appointment[]>>('/appointments', { params })
  return response.data
}

export async function getAppointmentOptions() {
  const response = await apiClient.get<ApiEnvelope<AppointmentBookingOptions>>('/appointments/options')
  return response.data
}

export async function getAppointmentSlots(params: {
  doctor_user_id: number
  date: string
  visit_type?: Appointment['visit_type']
  branch_id?: number
}) {
  const response = await apiClient.get<ApiEnvelope<AppointmentSlotsResponse>>('/appointments/slots', { params })
  return response.data
}

export async function bookAppointment(payload: AppointmentPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Appointment>>('/appointments', payload)
  return response.data
}

export async function updateAppointment(id: number, payload: Partial<AppointmentPayload> & { status?: string }) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<Appointment>>(`/appointments/${id}`, payload)
  return response.data
}

export async function cancelAppointment(id: number, cancellation_reason: string) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Appointment>>(`/appointments/${id}/cancel`, {
    cancellation_reason,
  })
  return response.data
}

export async function checkInAppointment(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Appointment>>(`/appointments/${id}/check-in`)
  return response.data
}
