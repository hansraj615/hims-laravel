import type { ApiEnvelope } from '../types/api'
import type { Patient } from './patients'
import { apiClient } from './client'
import { csrfCookie } from './csrf'

export type AbdmStatus = {
  enabled: boolean
  provider: string
  mode: string
  demo_otp_hint: string | null
}

export type AbdmProfile = {
  abha_number?: string | null
  abha_address?: string | null
  abha_id?: string | null
  first_name?: string | null
  last_name?: string | null
  mobile?: string | null
  gender?: string | null
  date_of_birth?: string | null
}

export type AbdmTxnResult = {
  transaction_id: number
  external_txn_id: string
  status: string
  message?: string
  provider?: string
  profile?: AbdmProfile | null
  patient?: Patient | null
}

export async function getAbdmStatus() {
  const response = await apiClient.get<ApiEnvelope<AbdmStatus>>('/abdm/status')
  return response.data
}

export async function initAbhaVerify(payload: { abha_number?: string; mobile?: string; patient_id?: number }) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AbdmTxnResult>>('/abdm/abha/verify/init', payload)
  return response.data
}

export async function confirmAbhaVerify(payload: {
  external_txn_id: string
  otp: string
  abha_number?: string
  mobile?: string
  patient_id?: number
  link_patient?: boolean
}) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AbdmTxnResult>>('/abdm/abha/verify/confirm', payload)
  return response.data
}

export async function initAbhaCreate(payload: {
  aadhaar_number: string
  mobile: string
  first_name?: string
  last_name?: string
  patient_id?: number
}) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AbdmTxnResult>>('/abdm/abha/create/init', payload)
  return response.data
}

export async function confirmAbhaCreate(payload: {
  external_txn_id: string
  otp: string
  aadhaar_number?: string
  mobile?: string
  first_name?: string
  last_name?: string
  patient_id?: number
  link_patient?: boolean
}) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AbdmTxnResult>>('/abdm/abha/create/confirm', payload)
  return response.data
}

export async function resolveScanShare(payload: { share_code: string; register_patient?: boolean; counter_id?: string }) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AbdmTxnResult>>('/abdm/scan-share', payload)
  return response.data
}
