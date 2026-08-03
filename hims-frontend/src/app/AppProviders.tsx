import { CssBaseline, ThemeProvider } from '@mui/material'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useEffect } from 'react'
import type { PropsWithChildren } from 'react'
import { BrowserRouter } from 'react-router-dom'
import { AUTH_UNAUTHORIZED_EVENT } from '../api/client'
import { theme } from '../theme/theme'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

export function AppProviders({ children }: PropsWithChildren) {
  useEffect(() => {
    const handleUnauthorized = () => queryClient.clear()
    window.addEventListener(AUTH_UNAUTHORIZED_EVENT, handleUnauthorized)

    return () => window.removeEventListener(AUTH_UNAUTHORIZED_EVENT, handleUnauthorized)
  }, [])

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <BrowserRouter>{children}</BrowserRouter>
      </ThemeProvider>
    </QueryClientProvider>
  )
}
