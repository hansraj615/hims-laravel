import { Box, CircularProgress } from '@mui/material'
import type { PropsWithChildren } from 'react'
import { Navigate } from 'react-router-dom'
import { usePermissions } from './usePermissions'

type RequirePermissionProps = PropsWithChildren<{
  permission: string | string[]
}>

export function RequirePermission({ permission, children }: RequirePermissionProps) {
  const { isLoading, can } = usePermissions()

  if (isLoading) {
    return (
      <Box sx={{ display: 'grid', minHeight: '40vh', placeItems: 'center' }}>
        <CircularProgress aria-label="Checking permissions" />
      </Box>
    )
  }

  if (!can(permission)) {
    return <Navigate replace to="/dashboard" />
  }

  return children
}
