import axios from 'axios'

export function getApiErrorMessage(error: unknown, fallback: string) {
  if (!axios.isAxiosError(error)) {
    return fallback
  }

  const data = error.response?.data as
    | { message?: string; errors?: Record<string, string[] | string> }
    | undefined

  const firstError = data?.errors ? Object.values(data.errors)[0] : null
  const firstErrorText = Array.isArray(firstError) ? firstError[0] : firstError

  return firstErrorText || data?.message || fallback
}
