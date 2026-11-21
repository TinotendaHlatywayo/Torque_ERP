import axios from 'axios'
import type { QueryClient } from '@tanstack/react-query'

const defaultBaseURL = import.meta.env.DEV
  ? `${window.location.protocol}//${window.location.hostname}:8000`
  : ""

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || defaultBaseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

let queryClient: QueryClient | null = null

export function setApiQueryClient(client: QueryClient) {
  queryClient = client
}

function isPublicOrAuthRequest(url: string | undefined): boolean {
  if (!url) return false

  const normalized = url.toLowerCase()
  return (
    normalized.includes('/sanctum/csrf-cookie') ||
    normalized.includes('/login') ||
    normalized.includes('/logout') ||
    normalized.includes('/forgot-password') ||
    normalized.includes('/reset-password') ||
    normalized.includes('/api/public/') ||
    normalized.includes('/up')
  )
}

api.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      const requestUrl = error.config?.url
      const onLoginPage = window.location.pathname === '/login'

      if (!isPublicOrAuthRequest(requestUrl) && !onLoginPage) {
        queryClient?.clear()
        const redirect = encodeURIComponent(`${window.location.pathname}${window.location.search}${window.location.hash}`)
        window.location.assign(`/login?redirect=${redirect}`)
      }
    }

    return Promise.reject(error)
  },
)

export default api
