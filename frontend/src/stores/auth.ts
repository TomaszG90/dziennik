import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ApiError, apiClient, clearAuthToken, getAuthToken, setAuthToken } from '@/api'

export interface AuthUser {
  id: number | string
  name: string
  email: string
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface LoginPayload {
  token: string
  user: AuthUser
}

export interface LoginResponse {
  data: LoginPayload
}

const ENDPOINTS = {
  login: '/auth/login',
  logout: '/auth/logout',
  me: '/me',
} as const

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(getAuthToken())
  const isLoading = ref(false)
  // true, gdy próba odtworzenia sesji została zakończona (sukcesem lub nie).
  // Przydatne w guardzie routera, żeby nie przekierowywać przed sprawdzeniem tokenu.
  const isInitialized = ref(false)

  let restorePromise: Promise<void> | null = null

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  function setSession(newToken: string, newUser: AuthUser): void {
    token.value = newToken
    user.value = newUser
    setAuthToken(newToken)
  }

  function clearSession(): void {
    token.value = null
    user.value = null
    clearAuthToken()
  }

  // Interceptor w client.ts przy 401 czyści token w localStorage i emituje
  // to zdarzenie – synchronizujemy z nim stan store'a (np. wygaśnięcie sesji
  // w trakcie działania aplikacji). Router może zareagować na zmianę isAuthenticated.
  if (typeof window !== 'undefined') {
    window.addEventListener('auth:unauthorized', () => {
      token.value = null
      user.value = null
    })
  }

  async function login(credentials: LoginCredentials): Promise<void> {
    isLoading.value = true
    try {
      const { data } = await apiClient.post<LoginResponse | LoginPayload>(ENDPOINTS.login, credentials)
      const payload = 'data' in data && data.data ? data.data : (data as LoginPayload)
      setSession(payload.token, payload.user)
    } finally {
      isLoading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      if (token.value) {
        await apiClient.post(ENDPOINTS.logout)
      }
    } catch {
      // Wylogowanie lokalne ma się udać także przy braku sieci lub wygasłym tokenie.
    } finally {
      clearSession()
    }
  }

  /**
   * Odtwarza sesję po odświeżeniu strony: jeśli w localStorage jest token,
   * pobiera aktualnego użytkownika. Wywołania są deduplikowane, więc można ją
   * bezpiecznie wołać z guarda routera.
   */
  function restoreSession(): Promise<void> {
    if (isInitialized.value) return Promise.resolve()
    if (restorePromise) return restorePromise

    restorePromise = (async () => {
      const storedToken = getAuthToken()

      if (!storedToken) {
        clearSession()
        isInitialized.value = true
        return
      }

      token.value = storedToken
      isLoading.value = true
      try {
        const { data } = await apiClient.get<{ data: AuthUser } | AuthUser>(ENDPOINTS.me)
        user.value = 'data' in data && data.data ? data.data : (data as AuthUser)
      } catch (error) {
        if (error instanceof ApiError && error.isUnauthorized) {
          clearSession()
        } else {
          // Błąd sieci / 5xx: token zostaje w storage (można spróbować ponownie),
          // ale użytkownik nie jest uznany za zalogowanego.
          user.value = null
        }
      } finally {
        isLoading.value = false
        isInitialized.value = true
        restorePromise = null
      }
    })()

    return restorePromise
  }

  return {
    user,
    token,
    isLoading,
    isInitialized,
    isAuthenticated,
    login,
    logout,
    restoreSession,
  }
})
