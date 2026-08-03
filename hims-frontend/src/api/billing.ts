import type { ApiEnvelope } from '../types/api'
import { apiClient } from './client'
import { csrfCookie } from './csrf'

export type ServiceCategory = 'opd' | 'ipd' | 'pathology' | 'radiology' | 'procedure' | 'consultant_fee'

export type BillingService = {
  id: number
  hospital_id: number
  department_id: number | null
  name: string
  code: string
  service_type: string
  category: ServiceCategory | string
  hsn_sac_code: string | null
  base_rate: number | string
  cgst_rate: number | string
  sgst_rate: number | string
  igst_rate: number | string
  is_tax_exempt: boolean
  status: 'active' | 'inactive'
}

export type InvoiceStatus = 'draft' | 'issued' | 'billed' | 'partially_paid' | 'paid' | 'voided' | 'cancelled'

export type InvoiceItem = {
  id?: number
  service_id?: number | null
  description: string
  quantity: number
  unit_rate: number
  discount_amount?: number
  gross_amount?: number
  taxable_amount?: number
  net_amount?: number
}

export type Invoice = {
  id: number
  hospital_id: number
  branch_id: number
  patient_id: number
  invoice_number: string
  invoice_type: string
  payer_type: string
  status: InvoiceStatus | string
  subtotal: number | string
  discount_total: number | string
  taxable_total: number | string
  cgst_total: number | string
  sgst_total: number | string
  igst_total: number | string
  round_off: number | string
  grand_total: number | string
  paid_total: number | string
  balance_total: number | string
  billed_at: string | null
  patient?: { id: number; uhid: string; name: string; mobile: string | null } | null
  items?: InvoiceItem[]
  payments?: Payment[]
  created_at: string
  updated_at: string
}

export type InvoicePayload = {
  patient_id: number
  invoice_type?: string
  payer_type?: string
  items: InvoiceItem[]
}

export type InvoiceListParams = {
  patient_id?: number
  status?: string
}

export type Payment = {
  id: number
  invoice_id: number
  patient_id: number
  receipt_number: string
  payment_type: string
  payment_mode: string
  amount: number | string
  status: string
  reference_number: string | null
  bank_name: string | null
  paid_at: string | null
  created_at: string
}

export type PaymentPayload = {
  payment_mode: string
  amount: number
  reference_number?: string | null
  bank_name?: string | null
}

export type ReceiptPayload = {
  hospital: { name: string | null; gstin: string | null; phone: string | null }
  branch: { name: string | null; address: string | null }
  patient: { uhid: string; name: string; mobile: string | null } | null
  invoice: Invoice
  items: InvoiceItem[]
  payments: Payment[]
  gst_summary: {
    taxable_total: number | string
    cgst_total: number | string
    sgst_total: number | string
    igst_total: number | string
    grand_total: number | string
  }
}

export async function getServices(params: { category?: string; q?: string } = {}) {
  const response = await apiClient.get<ApiEnvelope<BillingService[]>>('/billing/services', { params })
  return response.data
}

export async function getInvoices(params: InvoiceListParams = {}) {
  const response = await apiClient.get<ApiEnvelope<Invoice[]>>('/billing/invoices', { params })
  return response.data
}

export async function getInvoice(id: number) {
  const response = await apiClient.get<ApiEnvelope<Invoice>>(`/billing/invoices/${id}`)
  return response.data
}

export async function createInvoice(payload: InvoicePayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Invoice>>('/billing/invoices', payload)
  return response.data
}

export async function finalizeInvoice(id: number) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Invoice>>(`/billing/invoices/${id}/finalize`)
  return response.data
}

export async function createPayment(invoiceId: number, payload: PaymentPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Payment>>(`/billing/invoices/${invoiceId}/payments`, payload)
  return response.data
}

export async function getInvoiceReceipt(invoiceId: number) {
  const response = await apiClient.get<ApiEnvelope<ReceiptPayload>>(`/billing/invoices/${invoiceId}/receipt`)
  return response.data
}
