import axios from 'axios'
import { env } from '../config/env'

const apiRootUrl = env.apiBaseUrl.replace(/\/api\/v1\/?$/, '')

export async function csrfCookie() {
  await axios.get(`${apiRootUrl}/sanctum/csrf-cookie`, {
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    timeout: 15000,
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
}
