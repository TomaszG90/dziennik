import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api'
import LoginView from './LoginView.vue'

const { authMock } = vi.hoisted(() => ({
  authMock: {
    isLoading: false,
    login: vi.fn<(credentials: { email: string; password: string }) => Promise<void>>(),
  },
}))

vi.mock('@/stores/auth', () => ({ useAuthStore: () => authMock }))

const Placeholder = { template: '<div />' }

async function mountLoginView(path = '/login') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: Placeholder },
      { path: '/login', name: 'login', component: Placeholder },
      { path: '/register', name: 'register', component: Placeholder },
      { path: '/dashboard', name: 'dashboard', component: Placeholder },
      { path: '/trips', name: 'trips', component: Placeholder },
    ],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(LoginView, { global: { plugins: [router] } })
  return { wrapper, router }
}

describe('LoginView', () => {
  beforeEach(() => {
    authMock.isLoading = false
    authMock.login.mockReset()
  })

  it('logs in and returns to the requested route', async () => {
    authMock.login.mockResolvedValue(undefined)
    const { wrapper, router } = await mountLoginView('/login?redirect=/trips')

    await wrapper.get('#email').setValue('ala@example.com')
    await wrapper.get('#password').setValue('Secret123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(authMock.login).toHaveBeenCalledWith({
      email: 'ala@example.com',
      password: 'Secret123!',
    })
    expect(router.currentRoute.value.fullPath).toBe('/trips')
  })

  it('redirects to the home page after login when no destination was requested', async () => {
    authMock.login.mockResolvedValue(undefined)
    const { wrapper, router } = await mountLoginView()

    await wrapper.get('#email').setValue('ala@example.com')
    await wrapper.get('#password').setValue('Secret123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('home')
  })

  it('rejects invalid email before submitting', async () => {
    const { wrapper } = await mountLoginView()

    await wrapper.get('#email').setValue('not-an-email')
    await wrapper.get('#password').setValue('Secret123!')
    await wrapper.get('form').trigger('submit')

    expect(authMock.login).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Podaj prawidłowy adres e-mail.')
  })

  it('rejects a missing password before submitting', async () => {
    const { wrapper } = await mountLoginView()

    await wrapper.get('#email').setValue('ala@example.com')
    await wrapper.get('form').trigger('submit')

    expect(authMock.login).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Podaj hasło.')
  })

  it('shows API validation errors on the matching field', async () => {
    authMock.login.mockRejectedValue(
      new ApiError('Validation failed', 422, {
        email: ['The email field is invalid.'],
      }),
    )
    const { wrapper } = await mountLoginView()

    await wrapper.get('#email').setValue('ala@example.com')
    await wrapper.get('#password').setValue('Secret123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('The email field is invalid.')
    expect(wrapper.get('#email').attributes('aria-invalid')).toBe('true')
  })

  it('shows a general error when credentials are rejected', async () => {
    authMock.login.mockRejectedValue(new ApiError('Unauthorized', 401))
    const { wrapper, router } = await mountLoginView()

    await wrapper.get('#email').setValue('ala@example.com')
    await wrapper.get('#password').setValue('wrong')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(router.currentRoute.value.name).toBe('login')
  })
})
