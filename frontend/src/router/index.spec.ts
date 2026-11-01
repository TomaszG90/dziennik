import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from './authGuard'
import router from './index'
describe('auth route guard', () => {
  it('waits for session restoration and redirects a guest from a protected route', async () => {
    const authStore = {
      isInitialized: false,
      isAuthenticated: false,
      restoreSession: vi.fn<() => Promise<void>>(async () => {
        authStore.isInitialized = true
      }),
    }

    const guard = createAuthGuard(authStore)

    const result = await guard({
      meta: { requiresAuth: true },
      fullPath: '/trips',
    } as never)

    expect(authStore.restoreSession).toHaveBeenCalledOnce()
    expect(result).toEqual({ name: 'login', query: { redirect: '/trips' } })
  })

  it('redirects an authenticated user away from a guest-only route', async () => {
    const authStore = {
      isInitialized: true,
      isAuthenticated: true,
      restoreSession: vi.fn<() => Promise<void>>(async () => {}),
    }

    const guard = createAuthGuard(authStore)

    const result = await guard({
      meta: { guestOnly: true },
      fullPath: '/login',
    } as never)

    expect(authStore.restoreSession).not.toHaveBeenCalled()
    expect(result).toEqual({ name: 'home' })
  })
})
describe('router auth redirects', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('redirects a guest from the dashboard to login', async () => {
    const auth = useAuthStore()
    auth.isInitialized = true

    await router.push('/dashboard')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/dashboard')
  })

  it('redirects a guest from the profile to login', async () => {
    const auth = useAuthStore()
    auth.isInitialized = true

    await router.push('/me')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/me')
  })

  it('redirects an authenticated user from login to home', async () => {
    const auth = useAuthStore()
    auth.isInitialized = true
    auth.user = { id: 1, name: 'Ala Kowalska', email: 'ala@example.com' }
    auth.token = 'test-token'

    await router.push('/login')

    expect(router.currentRoute.value.name).toBe('home')
  })
})
