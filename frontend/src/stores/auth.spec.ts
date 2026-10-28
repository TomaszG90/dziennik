import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiClient, clearAuthToken, getAuthToken, setAuthToken } from '@/api'
import { useAuthStore, type AuthUser } from './auth'

vi.mock('@/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api')>()

  return {
    ...actual,
    apiClient: { get: vi.fn<typeof actual.apiClient.get>(), post: vi.fn<typeof actual.apiClient.post>() },
    clearAuthToken: vi.fn<typeof actual.clearAuthToken>(),
    getAuthToken: vi.fn<typeof actual.getAuthToken>(),
    setAuthToken: vi.fn<typeof actual.setAuthToken>(),
  }
})

const user: AuthUser = {
  id: 7,
  name: 'Ala Kowalska',
  email: 'ala@example.com',
}

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.mocked(apiClient.get).mockReset()
    vi.mocked(apiClient.post).mockReset()
    vi.mocked(getAuthToken).mockReset().mockReturnValue(null)
    vi.mocked(setAuthToken).mockReset()
    vi.mocked(clearAuthToken).mockReset()
  })

  it('loguje użytkownika, zapisuje token i kończy stan ładowania', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: { token: 'session-token', user } } as never)
    const store = useAuthStore()

    await store.login({ email: user.email, password: 'secret' })

    expect(apiClient.post).toHaveBeenCalledWith('/auth/login', { email: user.email, password: 'secret' })
    expect(store.user).toEqual(user)
    expect(store.token).toBe('session-token')
    expect(store.isAuthenticated).toBe(true)
    expect(setAuthToken).toHaveBeenCalledWith('session-token')
    expect(store.isLoading).toBe(false)
  })

  it('przekazuje błąd logowania i nie tworzy sesji', async () => {
    const error = new ApiError('Nieprawidłowe dane logowania', 401)
    vi.mocked(apiClient.post).mockRejectedValue(error)
    const store = useAuthStore()

    await expect(store.login({ email: user.email, password: 'wrong' })).rejects.toBe(error)

    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(setAuthToken).not.toHaveBeenCalled()
    expect(store.isLoading).toBe(false)
  })

  it('czyści lokalną sesję, nawet gdy żądanie wylogowania się nie powiedzie', async () => {
    vi.mocked(apiClient.post).mockResolvedValueOnce({ data: { token: 'session-token', user } } as never)
    const store = useAuthStore()
    await store.login({ email: user.email, password: 'secret' })
    vi.mocked(apiClient.post).mockRejectedValueOnce(new Error('Network Error'))

    await store.logout()

    expect(apiClient.post).toHaveBeenLastCalledWith('/auth/logout')
    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(clearAuthToken).toHaveBeenCalledOnce()
  })

  it('nie wysyła żądania przy odtwarzaniu sesji bez tokenu', async () => {
    const store = useAuthStore()

    await store.restoreSession()

    expect(apiClient.get).not.toHaveBeenCalled()
    expect(clearAuthToken).toHaveBeenCalledOnce()
    expect(store.isInitialized).toBe(true)
    expect(store.isAuthenticated).toBe(false)
  })

  it('odtwarza użytkownika z GET /me, gdy token jest zapisany', async () => {
    vi.mocked(getAuthToken).mockReturnValue('saved-token')
    vi.mocked(apiClient.get).mockResolvedValue({ data: user } as never)
    const store = useAuthStore()

    await store.restoreSession()

    expect(apiClient.get).toHaveBeenCalledWith('/me')
    expect(store.user).toEqual(user)
    expect(store.token).toBe('saved-token')
    expect(store.isAuthenticated).toBe(true)
    expect(store.isInitialized).toBe(true)
  })

  it('usuwa token i stan użytkownika, gdy odtwarzanie zwraca 401', async () => {
    vi.mocked(getAuthToken).mockReturnValue('expired-token')
    vi.mocked(apiClient.get).mockRejectedValue(new ApiError('Unauthenticated.', 401))
    const store = useAuthStore()

    await store.restoreSession()

    expect(clearAuthToken).toHaveBeenCalledOnce()
    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(store.isInitialized).toBe(true)
  })

  it('zachowuje token przy błędzie serwera i uznaje użytkownika za niezalogowanego', async () => {
    vi.mocked(getAuthToken).mockReturnValue('saved-token')
    vi.mocked(apiClient.get).mockRejectedValue(new ApiError('Server error', 500))
    const store = useAuthStore()

    await store.restoreSession()

    expect(clearAuthToken).not.toHaveBeenCalled()
    expect(store.token).toBe('saved-token')
    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(store.isInitialized).toBe(true)
  })

  it('scala równoczesne wywołania odtwarzania sesji w jedno żądanie', async () => {
    vi.mocked(getAuthToken).mockReturnValue('saved-token')
    let resolveRequest!: (value: { data: AuthUser }) => void
    vi.mocked(apiClient.get).mockReturnValueOnce(
      new Promise((resolve) => { resolveRequest = resolve }) as never,
    )
    const store = useAuthStore()

    const firstRestore = store.restoreSession()
    const secondRestore = store.restoreSession()
    expect(apiClient.get).toHaveBeenCalledOnce()
    resolveRequest({ data: user })
    await Promise.all([firstRestore, secondRestore])

    expect(store.user).toEqual(user)
    expect(store.isInitialized).toBe(true)
  })

  it('czyści stan store po zdarzeniu auth:unauthorized', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: { token: 'session-token', user } } as never)
    const store = useAuthStore()
    await store.login({ email: user.email, password: 'secret' })

    window.dispatchEvent(new Event('auth:unauthorized'))

    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(store.isAuthenticated).toBe(false)
  })
})



