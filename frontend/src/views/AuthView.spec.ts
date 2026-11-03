import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api'
import AuthView from './AuthView.vue'

const { apiMock, authMock } = vi.hoisted(() => ({
  apiMock: {
    requestPasswordReset: vi.fn<(payload: { email: string }) => Promise<void>>(),
    resetPassword:
      vi.fn<
        (payload: {
          email: string
          token: string
          password: string
          password_confirmation: string
        }) => Promise<void>
      >(),
    registerUser:
      vi.fn<
        (payload: {
          name: string
          email: string
          password: string
          password_confirmation: string
          profile_visibility: 'public' | 'private'
        }) => Promise<void>
      >(),
  },
  authMock: {
    isLoading: false,
    login: vi.fn<(credentials: { email: string; password: string }) => Promise<void>>(),
  },
}))

vi.mock('@/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api')>()

  return {
    ...actual,
    requestPasswordReset: apiMock.requestPasswordReset,
    resetPassword: apiMock.resetPassword,
    registerUser: apiMock.registerUser,
  }
})

vi.mock('@/stores/auth', () => ({ useAuthStore: () => authMock }))

async function mountAuthView(path: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/auth', name: 'auth', component: AuthView },
      { path: '/reset-password', name: 'reset-password', component: AuthView },
    ],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(AuthView, { global: { plugins: [router] } })

  return { wrapper, router }
}

describe('AuthView password reset', () => {
  beforeEach(() => {
    apiMock.requestPasswordReset.mockReset()
    apiMock.resetPassword.mockReset()
    apiMock.registerUser.mockReset()
    authMock.login.mockReset()
    authMock.isLoading = false
  })

  it('prefills the reset email from the link and submits its token', async () => {
    apiMock.resetPassword.mockResolvedValue(undefined)
    const { wrapper, router } = await mountAuthView(
      '/reset-password?token=reset-token&email=alice%40example.com',
    )

    expect(wrapper.get('#auth-title').text()).toBe('Ustaw nowe hasło')
    expect((wrapper.get('#email').element as HTMLInputElement).value).toBe('alice@example.com')

    await wrapper.get('#new-password').setValue('NewPassword123!')
    await wrapper.get('#new-password-confirmation').setValue('NewPassword123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(apiMock.resetPassword).toHaveBeenCalledWith({
      email: 'alice@example.com',
      token: 'reset-token',
      password: 'NewPassword123!',
      password_confirmation: 'NewPassword123!',
    })
    expect(router.currentRoute.value.name).toBe('auth')
    expect(router.currentRoute.value.query.token).toBeUndefined()
    expect(wrapper.text()).toContain('Hasło zostało zmienione. Możesz się zalogować.')
  })

  it('does not submit a reset with a short or mismatched password', async () => {
    const { wrapper } = await mountAuthView('/reset-password?token=reset-token&email=a%40b.com')

    await wrapper.get('#new-password').setValue('short')
    await wrapper.get('#new-password-confirmation').setValue('different')
    await wrapper.get('form').trigger('submit')

    expect(apiMock.resetPassword).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Hasło musi mieć co najmniej 8 znaków.')

    await wrapper.get('#new-password').setValue('LongPassword123!')
    await wrapper.get('#new-password-confirmation').setValue('DifferentPassword123!')
    await wrapper.get('form').trigger('submit')

    expect(apiMock.resetPassword).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Hasła muszą być takie same.')
  })

  it('shows API validation errors for an expired or invalid reset link', async () => {
    apiMock.resetPassword.mockRejectedValue(
      new ApiError('Validation failed', 422, {
        email: ['The provided token is invalid or has expired.'],
      }),
    )
    const { wrapper, router } = await mountAuthView(
      '/reset-password?token=expired-token&email=alice%40example.com',
    )

    await wrapper.get('#new-password').setValue('NewPassword123!')
    await wrapper.get('#new-password-confirmation').setValue('NewPassword123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('The provided token is invalid or has expired.')
    expect(wrapper.get('#email').attributes('aria-invalid')).toBe('true')
    expect(router.currentRoute.value.name).toBe('reset-password')
  })

  it('requests a reset link and shows a generic confirmation', async () => {
    apiMock.requestPasswordReset.mockResolvedValue(undefined)
    const { wrapper } = await mountAuthView('/auth?mode=forgot')

    await wrapper.get('#email').setValue('alice@example.com')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(apiMock.requestPasswordReset).toHaveBeenCalledWith({ email: 'alice@example.com' })
    expect(wrapper.text()).toContain(
      'Jeśli konto z tym adresem istnieje, wyślemy na niego link do zmiany hasła.',
    )
  })

  it('shows forgot-password validation errors and does not reveal account existence', async () => {
    apiMock.requestPasswordReset.mockRejectedValue(
      new ApiError('Validation failed', 422, {
        email: ['The email field must be a valid email address.'],
      }),
    )
    const { wrapper } = await mountAuthView('/auth?mode=forgot')

    await wrapper.get('#email').setValue('alice@example.com')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('The email field must be a valid email address.')
    expect(wrapper.get('#email').attributes('aria-invalid')).toBe('true')
  })
})
