import type { ApiEnvelope } from '../types/api'
import { csrfCookie } from './csrf'
import { apiClient } from './client'

export type Hospital = {
  id: number
  name: string
  legal_name: string | null
  code: string
  registration_number: string | null
  gstin: string | null
  phone: string | null
  status: 'active' | 'inactive'
}

export type HospitalPayload = {
  name: string
  legal_name?: string | null
  registration_number?: string | null
  gstin?: string | null
  phone?: string | null
  status: Hospital['status']
}

export type UserAssignment = {
  id: number
  hospital_id: number
  branch_id: number | null
  department_id: number | null
  assignment_type: 'staff' | 'consultant' | 'visiting' | 'contract'
  is_default: boolean
  status: string
}

export type AdminUser = {
  id: number
  name: string
  email: string
  mobile: string | null
  status: 'active' | 'inactive'
  roles: string[]
  assignments?: UserAssignment[]
}

export type UserPayload = {
  name: string
  email: string
  mobile?: string | null
  password?: string
  status: AdminUser['status']
  role: string
  branch_id?: number | null
  department_id?: number | null
  assignment_type?: UserAssignment['assignment_type']
  is_default?: boolean
}

export type Role = {
  id: number
  name: string
  guard_name: string
  permissions: string[]
}

export type RolePayload = {
  name: string
  permissions: string[]
}

export type Permission = {
  id: number
  name: string
  guard_name: string
}

export async function getHospitals() {
  const response = await apiClient.get<ApiEnvelope<Hospital[]>>('/admin/hospitals')
  return response.data
}

export async function updateHospital(id: number, payload: HospitalPayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<Hospital>>(`/admin/hospitals/${id}`, payload)
  return response.data
}

export async function getUsers() {
  const response = await apiClient.get<ApiEnvelope<AdminUser[]>>('/admin/users')
  return response.data
}

export async function createUser(payload: UserPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<AdminUser>>('/admin/users', payload)
  return response.data
}

export async function updateUser(id: number, payload: UserPayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<AdminUser>>(`/admin/users/${id}`, payload)
  return response.data
}

export async function getRoles() {
  const response = await apiClient.get<ApiEnvelope<Role[]>>('/admin/roles')
  return response.data
}

export async function createRole(payload: RolePayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Role>>('/admin/roles', payload)
  return response.data
}

export async function updateRole(id: number, payload: RolePayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<Role>>(`/admin/roles/${id}`, payload)
  return response.data
}

export async function getPermissions() {
  const response = await apiClient.get<ApiEnvelope<Permission[]>>('/admin/permissions')
  return response.data
}

export type Branch = {
  id: number
  hospital_id: number
  name: string
  code: string
  facility_type: 'hospital' | 'clinic' | 'diagnostic_centre'
  city: string | null
  state: string | null
  pincode: string | null
  phone: string | null
  timezone: string
  status: 'active' | 'inactive'
}

export type BranchPayload = {
  name: string
  code: string
  facility_type: Branch['facility_type']
  address?: string
  city?: string
  state?: string
  pincode?: string
  phone?: string
  timezone: string
  status: Branch['status']
}

export type Department = {
  id: number
  hospital_id: number
  branch_id: number | null
  name: string
  code: string
  department_type: 'clinical' | 'diagnostic' | 'administrative' | 'support'
  status: 'active' | 'inactive'
}

export type DepartmentPayload = {
  branch_id?: number | null
  name: string
  code: string
  department_type: Department['department_type']
  status: Department['status']
}

export async function getBranches() {
  const response = await apiClient.get<ApiEnvelope<Branch[]>>('/admin/branches')
  return response.data
}

export async function createBranch(payload: BranchPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Branch>>('/admin/branches', payload)
  return response.data
}

export async function updateBranch(id: number, payload: BranchPayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<Branch>>(`/admin/branches/${id}`, payload)
  return response.data
}

export async function getDepartments() {
  const response = await apiClient.get<ApiEnvelope<Department[]>>('/admin/departments')
  return response.data
}

export async function createDepartment(payload: DepartmentPayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<Department>>('/admin/departments', payload)
  return response.data
}

export async function updateDepartment(id: number, payload: DepartmentPayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<Department>>(`/admin/departments/${id}`, payload)
  return response.data
}

export type DoctorSchedule = {
  id: number
  hospital_id: number
  branch_id: number | null
  doctor_user_id: number
  department_id: number | null
  day_of_week: number
  start_time: string
  end_time: string
  slot_duration_minutes: number
  status: 'active' | 'inactive'
}

export type DoctorSchedulePayload = {
  branch_id?: number | null
  department_id?: number | null
  day_of_week: number
  start_time: string
  end_time: string
  slot_duration_minutes?: number
  status?: DoctorSchedule['status']
}

export type DoctorLeave = {
  id: number
  hospital_id: number
  doctor_user_id: number
  start_date: string
  end_date: string
  reason: string | null
  status: 'active' | 'cancelled'
}

export type DoctorLeavePayload = {
  start_date: string
  end_date: string
  reason?: string | null
  status?: DoctorLeave['status']
}

export type DoctorFee = {
  id: number
  hospital_id: number
  doctor_user_id: number
  visit_type: 'first_visit' | 'follow_up' | 'emergency'
  fee_amount: number | string
  effective_from: string | null
  effective_to: string | null
  status: 'active' | 'inactive'
}

export type DoctorFeePayload = {
  visit_type: DoctorFee['visit_type']
  fee_amount: number
  effective_from?: string | null
  effective_to?: string | null
  status?: DoctorFee['status']
}

export async function getDoctorSchedules(doctorId: number) {
  const response = await apiClient.get<ApiEnvelope<DoctorSchedule[]>>(`/admin/doctors/${doctorId}/schedules`)
  return response.data
}

export async function createDoctorSchedule(doctorId: number, payload: DoctorSchedulePayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<DoctorSchedule>>(
    `/admin/doctors/${doctorId}/schedules`,
    payload,
  )
  return response.data
}

export async function updateDoctorSchedule(doctorId: number, scheduleId: number, payload: DoctorSchedulePayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<DoctorSchedule>>(
    `/admin/doctors/${doctorId}/schedules/${scheduleId}`,
    payload,
  )
  return response.data
}

export async function getDoctorLeaves(doctorId: number) {
  const response = await apiClient.get<ApiEnvelope<DoctorLeave[]>>(`/admin/doctors/${doctorId}/leaves`)
  return response.data
}

export async function createDoctorLeave(doctorId: number, payload: DoctorLeavePayload) {
  await csrfCookie()
  const response = await apiClient.post<ApiEnvelope<DoctorLeave>>(`/admin/doctors/${doctorId}/leaves`, payload)
  return response.data
}

export async function updateDoctorLeave(doctorId: number, leaveId: number, payload: DoctorLeavePayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<DoctorLeave>>(
    `/admin/doctors/${doctorId}/leaves/${leaveId}`,
    payload,
  )
  return response.data
}

export async function getDoctorFees(doctorId: number) {
  const response = await apiClient.get<ApiEnvelope<DoctorFee[]>>(`/admin/doctors/${doctorId}/fees`)
  return response.data
}

export async function upsertDoctorFee(doctorId: number, payload: DoctorFeePayload) {
  await csrfCookie()
  const response = await apiClient.put<ApiEnvelope<DoctorFee>>(`/admin/doctors/${doctorId}/fees`, payload)
  return response.data
}
