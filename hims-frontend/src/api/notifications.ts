import type { ApiEnvelope } from '../types/api'
import { apiClient } from './client'

export type NotificationLog = {
  id: number
  hospital_id: number | null
  branch_id: number | null
  patient_id: number | null
  user_id: number | null
  template_code: string
  channel: string
  recipient: string | null
  subject: string | null
  body: string | null
  status: string
  created_at: string
}

export async function getNotifications() {
  const response = await apiClient.get<ApiEnvelope<NotificationLog[]>>('/notifications')
  return response.data
}
