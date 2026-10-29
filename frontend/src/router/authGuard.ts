import type { RouteLocationNormalized } from 'vue-router'

interface AuthState {
  isInitialized: boolean
  isAuthenticated: boolean
  restoreSession: () => Promise<void>
}

export function createAuthGuard(auth: AuthState) {
  return async (to: RouteLocationNormalized) => {
    if (!auth.isInitialized) {
      await auth.restoreSession()
    }

    if (to.meta.requiresAuth === true && !auth.isAuthenticated) {
      return {
        name: 'login',
        query: { redirect: to.fullPath },
      }
    }

    if (to.meta.guestOnly === true && auth.isAuthenticated) {
      return { name: 'home' }
    }

    return true
  }
}
