import axios from 'axios'
import { env } from '../config/env'
import { useTenantStore } from '../store/tenantStore'
import { csrfCookie } from './csrf'

export const apiClient = axios.create({
  baseURL: env.apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

apiClient.interceptors.request.use((config) => {
  config.headers['X-Request-Id'] = crypto.randomUUID()

  const { hospitalId, branchId } = useTenantStore.getState()
  if (hospitalId) {
    config.headers['X-Hospital-Id'] = String(hospitalId)
  }
  if (branchId) {
    config.headers['X-Branch-Id'] = String(branchId)
  }

  const xsrfToken = readCookie('XSRF-TOKEN')
  if (xsrfToken) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken)
  }

  return config
})

export const AUTH_UNAUTHORIZED_EVENT = 'hims:auth-unauthorized'

function isAuthEndpoint(url?: string) {
  if (!url) {
    return false
  }

  return ['/auth/login', '/auth/otp/', '/auth/logout', '/auth/password/'].some((path) => url.includes(path))
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (!axios.isAxiosError(error) || !error.response || !error.config) {
      return Promise.reject(error)
    }

    if (error.response.status === 401 && !isAuthEndpoint(error.config.url)) {
      if (typeof window !== 'undefined') {
        useTenantStore.getState().clearSelection()
        window.dispatchEvent(new CustomEvent(AUTH_UNAUTHORIZED_EVENT))

        if (window.location.pathname !== '/login') {
          window.location.assign('/login')
        }
      }

      return Promise.reject(error)
    }

    if (error.response.status !== 419) {
      return Promise.reject(error)
    }

    const config = error.config as typeof error.config & { _csrfRetry?: boolean }
    if (config._csrfRetry) {
      return Promise.reject(error)
    }

    config._csrfRetry = true
    await csrfCookie()

    return apiClient(config)
  },
)

function readCookie(name: string) {
  if (typeof document === 'undefined') {
    return null
  }

  return document.cookie
    .split('; ')
    .find((row) => row.startsWith(`${name}=`))
    ?.split('=')[1] ?? null
}
