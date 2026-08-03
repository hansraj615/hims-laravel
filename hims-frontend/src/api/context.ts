import type { ApiEnvelope } from '../types/api'
import { apiClient } from './client'

export type ContextHospital = {
  id: number
  name: string
  code: string
  status: string
}

export type ContextBranch = {
  id: number
  name: string
  code: string
  timezone: string
  status: string
} | null

export type ContextAssignmentOption = {
  id: number
  hospital: ContextHospital | null
  branch: ContextBranch
  is_default: boolean
}

export type CurrentContext = {
  hospital: ContextHospital
  branch: ContextBranch
  assignment: {
    id: number
    type: string
    is_default: boolean
  }
  available_assignments: ContextAssignmentOption[]
  user: {
    id: number
    name: string
    email: string
    permissions: string[]
    roles: string[]
  }
}

export async function getCurrentContext() {
  const response = await apiClient.get<ApiEnvelope<CurrentContext>>('/context')
  return response.data
}
