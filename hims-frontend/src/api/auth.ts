import type { ApiEnvelope } from '../types/api'
import { apiClient } from './client'
import { csrfCookie } from './csrf'

export type AuthUser = {
  id: number
  name: string
  email: string
  mobile: string | null
  status: string
  roles: string[]
  permissions: string[]
}

export async function loginWithEmail(payload: { email: string; password: string }) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AuthUser>>('/auth/login', payload)
  return response.data
}

export async function requestOtp(payload: { mobile: string }) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<null>>('/auth/otp/request', payload)
  return response.data
}

export async function verifyOtp(payload: { mobile: string; otp: string }) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AuthUser>>('/auth/otp/verify', payload)
  return response.data
}

export async function getCurrentUser() {
  const response = await apiClient.get<ApiEnvelope<AuthUser>>('/auth/me')
  return response.data
}

export async function logout() {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<null>>('/auth/logout')
  return response.data
}

export async function forgotPassword(payload: { email: string }) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<null>>('/auth/password/forgot', payload)
  return response.data
}

export async function resetPassword(payload: {
  email: string
  token: string
  password: string
  password_confirmation: string
}) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<null>>('/auth/password/reset', payload)
  return response.data
}
