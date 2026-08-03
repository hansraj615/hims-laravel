import { useQuery } from '@tanstack/react-query'
import { getCurrentUser } from '../../api/auth'

export function hasPermission(permissions: string[], required?: string | string[]) {
  if (!required || (Array.isArray(required) && required.length === 0)) {
    return true
  }

  const list = Array.isArray(required) ? required : [required]

  return list.some((permission) => permissions.includes(permission))
}

export function usePermissions() {
  const userQuery = useQuery({ queryKey: ['auth-user'], queryFn: getCurrentUser, retry: false })
  const permissions = userQuery.data?.data.permissions ?? []

  return {
    permissions,
    user: userQuery.data?.data ?? null,
    isLoading: userQuery.isLoading,
    isError: userQuery.isError,
    can: (required?: string | string[]) => hasPermission(permissions, required),
  }
}
