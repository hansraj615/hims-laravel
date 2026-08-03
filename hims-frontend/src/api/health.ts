import type { ApiEnvelope } from '../types/api'
import { apiClient } from './client'

export type HealthPayload = {
  service: string
  status: 'ok'
  version: string
}

export async function getHealth() {
  const response = await apiClient.get<ApiEnvelope<HealthPayload>>('/health')
  return response.data
}
