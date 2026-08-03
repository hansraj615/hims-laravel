import { Box, CircularProgress } from '@mui/material'
import { useQuery } from '@tanstack/react-query'
import type { PropsWithChildren } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { getCurrentUser } from '../../api/auth'

export function RequireAuth({ children }: PropsWithChildren) {
  const location = useLocation()
  const userQuery = useQuery({
    queryKey: ['auth-user'],
    queryFn: getCurrentUser,
    retry: false,
  })

  if (userQuery.isLoading) {
    return (
      <Box sx={{ display: 'grid', minHeight: '100vh', placeItems: 'center' }}>
        <CircularProgress aria-label="Loading session" />
      </Box>
    )
  }

  if (userQuery.isError) {
    return <Navigate replace state={{ from: location }} to="/login" />
  }

  return children
}
