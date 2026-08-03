import type { ApiEnvelope } from '../types/api'
import type { BillingService, Invoice } from './billing'
import { apiClient } from './client'
import { csrfCookie } from './csrf'

export type DiagnosticCategory = 'pathology' | 'radiology' | 'procedure'

export type DiagnosticOrderStatus =
  | 'ordered'
  | 'sample_collected'
  | 'in_progress'
  | 'result_ready'
  | 'billed'
  | 'cancelled'

export type DiagnosticOrderItem = {
  id: number
  diagnostic_order_id: number
  service_id: number | null
  service_code: string | null
  service_name: string
  category: DiagnosticCategory | string
  quantity: number | string
  unit_rate: number | string
  status: string
}

export type DiagnosticOrder = {
  id: number
  hospital_id: number
  branch_id: number
  order_number: string
  patient_id: number
  encounter_id: number | null
  appointment_id: number | null
  category: DiagnosticCategory | string
  priority: 'routine' | 'urgent' | string
  status: DiagnosticOrderStatus | string
  clinical_notes: string | null
  result_summary: string | null
  result_payload: Record<string, unknown> | null
  ordered_at: string | null
  collected_at: string | null
  resulted_at: string | null
  invoice_id: number | null
  patient_document_id: number | null
  patient?: { id: number; uhid: string; name: string; mobile: string | null } | null
  ordered_by_user?: { id: number; name: string } | null
  items?: DiagnosticOrderItem[]
  created_at: string
  updated_at: string
}

export type DiagnosticOrderPayload = {
  patient_id: number
  category: DiagnosticCategory
  priority?: 'routine' | 'urgent'
  clinical_notes?: string
  encounter_id?: number
  appointment_id?: number
  items: Array<{ service_id: number; quantity?: number }>
}

export type DiagnosticOrderListParams = {
  category?: DiagnosticCategory | string
  status?: string
  patient_id?: number
  date?: string
}

export async function getDiagnosticOrders(params: DiagnosticOrderListParams = {}) {
  const response = await apiClient.get<ApiEnvelope<DiagnosticOrder[]>>('/diagnostics/orders', { params })
  return response.data
}

export async function getDiagnosticCatalog(category?: DiagnosticCategory) {
  const response = await apiClient.get<ApiEnvelope<BillingService[]>>('/diagnostics/orders/catalog', {
    params: category ? { category } : undefined,
  })
  return response.data
}

export async function createDiagnosticOrder(payload: DiagnosticOrderPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<DiagnosticOrder>>('/diagnostics/orders', payload)
  return response.data
}

export async function cancelDiagnosticOrder(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<DiagnosticOrder>>(`/diagnostics/orders/${id}/cancel`)
  return response.data
}

export async function collectDiagnosticOrder(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<DiagnosticOrder>>(`/diagnostics/orders/${id}/collect`)
  return response.data
}

export async function resultDiagnosticOrder(id: number, payload: { result_summary: string; result_payload?: Record<string, unknown> }) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<DiagnosticOrder>>(`/diagnostics/orders/${id}/result`, payload)
  return response.data
}

export async function billDiagnosticOrder(id: number) {
  await csrfCookie()
  const response = await apiClient.post<
    ApiEnvelope<{ order: DiagnosticOrder; invoice: Invoice }>
  >(`/diagnostics/orders/${id}/bill`)
  return response.data
}
