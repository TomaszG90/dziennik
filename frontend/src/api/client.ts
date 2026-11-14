import axios, { AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'
import { getAuthToken, clearAuthToken } from './token'
import { ApiError, type ApiValidationErrorPayload } from './types'
import { showErrorToast } from '@/services/toast'

export const apiClient: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 10000,
})

// Request interceptor: dodawanie tokena Bearer
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getAuthToken()
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error: unknown) => Promise.reject(error),
)

// Response interceptor: obsługa 401, 422 i normalizacja błędów
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiValidationErrorPayload>) => {
    const status = error.response?.status
    const data = error.response?.data

    if (status === 401) {
      clearAuthToken()
      window.dispatchEvent(new CustomEvent('auth:unauthorized'))
    } else if (status === 403) {
      window.dispatchEvent(new CustomEvent('api:forbidden'))
    } else if (status === undefined || status >= 500) {
      showErrorToast('Coś poszło nie tak. Spróbuj ponownie.')
    }

    const message = data?.message || error.message || 'Wystąpił nieoczekiwany błąd'
    const validationErrors = status === 422 ? data?.errors : undefined

    return Promise.reject(new ApiError(message, status, validationErrors))
  },
)
