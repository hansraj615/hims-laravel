function defaultApiBaseUrl() {
  if (typeof window === 'undefined') {
    return 'http://127.0.0.1:8000/api/v1'
  }

  const host = window.location.hostname || '127.0.0.1'

  return `http://${host}:8000/api/v1`
}

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? defaultApiBaseUrl()

export const env = {
  apiBaseUrl,
}
