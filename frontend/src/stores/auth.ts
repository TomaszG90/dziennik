import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ApiError, apiClient, clearAuthToken, getAuthToken, setAuthToken } from '@/api'

// ZAĹOĹ»ENIE: dopasuj do realnego modelu uĹĽytkownika z backendu.
export interface AuthUser {
  id: number | string
  name: string
  email: string
}

export interface LoginCredentials {
  email: string
  password: string
}

interface LoginResponse {
  token: string
  user: AuthUser
}

// ZAĹOĹ»ENIE: endpointy wzglÄ™dem baseURL (/api/v1) â€“ dopasuj do backendu.
// JeĹ›li backend opakowuje dane w { data: ... }, popraw odczyt w login/restoreSession.
const ENDPOINTS = {
  login: '/auth/login',
  logout: '/auth/logout',
  me: '/me',
} as const

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(getAuthToken())
  const isLoading = ref(false)
  // true, gdy prĂłba odtworzenia sesji zostaĹ‚a zakoĹ„czona (sukcesem lub nie).
  // Przydatne w guardzie routera, ĹĽeby nie przekierowywaÄ‡ przed sprawdzeniem tokenu.
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

  // Interceptor w client.ts przy 401 czyĹ›ci token w localStorage i emituje
  // to zdarzenie â€“ synchronizujemy z nim stan store'a (np. wygaĹ›niÄ™cie sesji
  // w trakcie dziaĹ‚ania aplikacji). Router moĹĽe zareagowaÄ‡ na zmianÄ™ isAuthenticated.
  if (typeof window !== 'undefined') {
    window.addEventListener('auth:unauthorized', () => {
      token.value = null
      user.value = null
    })
  }

  async function login(credentials: LoginCredentials): Promise<void> {
    isLoading.value = true
    try {
      const { data } = await apiClient.post<LoginResponse>(ENDPOINTS.login, credentials)
      setSession(data.token, data.user)
    } finally {
      isLoading.value = false
    }
    // BĹ‚Ä™dy to ApiError (422 -> validationErrors, 401 -> isUnauthorized)
    // i lecÄ… dalej do formularza logowania.
  }

  async function logout(): Promise<void> {
    try {
      if (token.value) {
        await apiClient.post(ENDPOINTS.logout)
      }
    } catch {
      // Wylogowanie lokalne ma siÄ™ udaÄ‡ takĹĽe przy braku sieci lub wygasĹ‚ym tokenie.
    } finally {
      clearSession()
    }
  }

  /**
   * Odtwarza sesjÄ™ po odĹ›wieĹĽeniu strony: jeĹ›li w localStorage jest token,
   * pobiera aktualnego uĹĽytkownika. WywoĹ‚ania sÄ… deduplikowane, wiÄ™c moĹĽna jÄ…
   * bezpiecznie woĹ‚aÄ‡ z guarda routera.
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
        const { data } = await apiClient.get<AuthUser>(ENDPOINTS.me)
        user.value = data
      } catch (error) {
        if (error instanceof ApiError && error.isUnauthorized) {
          clearSession()
        } else {
          // BĹ‚Ä…d sieci / 5xx: token zostaje w storage (moĹĽna sprĂłbowaÄ‡ ponownie),
          // ale uĹĽytkownik nie jest uznany za zalogowanego.
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

