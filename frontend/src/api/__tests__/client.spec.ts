import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { AxiosError, type InternalAxiosRequestConfig, type AxiosResponse } from 'axios'

vi.mock('../token', () => ({
  getAuthToken: vi.fn<() => string | null>(),
  clearAuthToken: vi.fn<() => void>(),
}))

import { getAuthToken, clearAuthToken } from '../token'
import { apiClient } from '../client'
import { ApiError } from '../types'

// ── helpers ───────────────────────────────────────────────────
let lastConfig: InternalAxiosRequestConfig | undefined
const originalAdapter = apiClient.defaults.adapter

/** Adapter zwracający sukces (bez prawdziwego HTTP). */
function mockSuccess(data: unknown = { ok: true }) {
  apiClient.defaults.adapter = async (config) => {
    lastConfig = config
    return { data, status: 200, statusText: 'OK', headers: {}, config } as AxiosResponse
  }
}

/** Adapter zwracający błąd HTTP z danym statusem i body. */
function mockHttpError(status: number, data: unknown) {
  apiClient.defaults.adapter = async (config) => {
    lastConfig = config
    const response = { data, status, statusText: String(status), headers: {}, config } as AxiosResponse
    throw new AxiosError(
      `Request failed with status code ${status}`,
      status >= 500 ? AxiosError.ERR_BAD_RESPONSE : AxiosError.ERR_BAD_REQUEST,
      config,
      null,
      response,
    )
  }
}

/** Adapter symulujący błąd sieciowy (brak odpowiedzi z serwera). */
function mockNetworkError() {
  apiClient.defaults.adapter = async (config) => {
    lastConfig = config
    throw new AxiosError('Network Error', AxiosError.ERR_NETWORK, config)
  }
}

// ── testy ─────────────────────────────────────────────────────
describe('apiClient', () => {
  beforeEach(() => {
    lastConfig = undefined
    vi.mocked(getAuthToken).mockReset()
    vi.mocked(clearAuthToken).mockReset()
  })

  afterEach(() => {
    apiClient.defaults.adapter = originalAdapter
    vi.restoreAllMocks()
  })

  it('zwraca dane przy pomyślnej odpowiedzi 200', async () => {
    mockSuccess({ user: { id: 1, name: 'Jan' } })

    const response = await apiClient.get('/me')

    expect(response.status).toBe(200)
    expect(response.data).toEqual({ user: { id: 1, name: 'Jan' } })
  })

  it('dodaje nagłówek Authorization: Bearer <token>, gdy token istnieje w storage', async () => {
    vi.mocked(getAuthToken).mockReturnValue('abc123')
    mockSuccess()

    await apiClient.get('/ping')

    expect(lastConfig?.headers.Authorization).toBe('Bearer abc123')
  })

  it('nie dodaje nagłówka Authorization, gdy tokena nie ma', async () => {
    vi.mocked(getAuthToken).mockReturnValue(null)
    mockSuccess()

    await apiClient.get('/ping')

    expect(lastConfig?.headers.Authorization).toBeUndefined()
  })

  it('odpowiedź 401 wywołuje clearAuthToken() i rzuca ApiError ze status === 401', async () => {
    vi.mocked(getAuthToken).mockReturnValue('expired-token')
    const dispatchSpy = vi.spyOn(window, 'dispatchEvent')
    mockHttpError(401, { message: 'Unauthenticated.' })

    const error = await apiClient.get('/secure').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(401)
    expect((error as ApiError).isUnauthorized).toBe(true)
    expect((error as ApiError).isValidationError).toBe(false)
    expect(clearAuthToken).toHaveBeenCalledTimes(1)

    // dodatkowo: event dla reszty aplikacji
    const event = dispatchSpy.mock.calls[0]?.[0] as Event
    expect(event.type).toBe('auth:unauthorized')
  })

  it('odpowiedź 422 mapuje validationErrors na instancję ApiError', async () => {
    const errors = {
      email: ['Email jest nieprawidłowy'],
      password: ['Hasło jest za krótkie'],
    }
    vi.mocked(getAuthToken).mockReturnValue('abc123')
    mockHttpError(422, { message: 'The given data was invalid.', errors })

    const error = await apiClient.post('/register', {}).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).isValidationError).toBe(true)
    expect((error as ApiError).isUnauthorized).toBe(false)
    expect((error as ApiError).message).toBe('The given data was invalid.')
    expect((error as ApiError).validationErrors).toEqual(errors)
    expect(clearAuthToken).not.toHaveBeenCalled()
  })

  it('odpowiedź 500 zachowuje status i nie usuwa tokena', async () => {
    vi.mocked(getAuthToken).mockReturnValue('valid-token')
    mockHttpError(500, { message: 'Błąd serwera' })

    const error = await apiClient.get('/trips').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(500)
    expect((error as ApiError).message).toBe('Błąd serwera')
    expect(clearAuthToken).not.toHaveBeenCalled()
  })

  it('błąd sieciowy (brak odpowiedzi z serwera) rzuca ApiError bez statusu', async () => {
    mockNetworkError()

    const error = await apiClient.get('/trips').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBeUndefined()
    expect((error as ApiError).message).toBe('Network Error')
    expect(clearAuthToken).not.toHaveBeenCalled()
  })
})
