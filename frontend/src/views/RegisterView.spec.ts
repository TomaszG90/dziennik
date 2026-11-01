import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiClient } from '@/api'
import RegisterView from './RegisterView.vue'

const Placeholder = { template: '<div />' }

async function mountRegisterView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/register', name: 'register', component: Placeholder },
      { path: '/login', name: 'login', component: Placeholder },
    ],
  })
  await router.push('/register')
  await router.isReady()
  const wrapper = mount(RegisterView, { global: { plugins: [router] } })
  return { wrapper, router }
}

async function fillForm(wrapper: ReturnType<typeof mount>) {
  await wrapper.get('#name').setValue('Ala Kowalska')
  await wrapper.get('#email').setValue('ala@example.com')
  await wrapper.get('#password').setValue('Secret123!')
  await wrapper.get('#password_confirmation').setValue('Secret123!')
}

describe('RegisterView', () => {
  beforeEach(() => vi.restoreAllMocks())

  it('renders registration fields without a profile visibility selector', async () => {
    const { wrapper } = await mountRegisterView()

    expect(wrapper.find('#name').exists()).toBe(true)
    expect(wrapper.find('#email').exists()).toBe(true)
    expect(wrapper.find('#password').exists()).toBe(true)
    expect(wrapper.find('#password_confirmation').exists()).toBe(true)
    expect(wrapper.find('input[type="radio"]').exists()).toBe(false)
  })

  it('registers with private visibility by default and navigates to login', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({} as never)
    const { wrapper, router } = await mountRegisterView()
    await fillForm(wrapper)

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/auth/register', {
      name: 'Ala Kowalska',
      email: 'ala@example.com',
      password: 'Secret123!',
      password_confirmation: 'Secret123!',
      profile_visibility: 'private',
    })
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('shows API validation errors on the matching field', async () => {
    vi.spyOn(apiClient, 'post').mockRejectedValue(
      new ApiError('Validation failed', 422, {
        email: ['This email has already been taken.'],
      }),
    )
    const { wrapper } = await mountRegisterView()
    await fillForm(wrapper)

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('This email has already been taken.')
    expect(wrapper.get('#email').attributes('aria-invalid')).toBe('true')
  })

  it('does not submit when password confirmation differs', async () => {
    const post = vi.spyOn(apiClient, 'post')
    const { wrapper } = await mountRegisterView()
    await fillForm(wrapper)
    await wrapper.get('#password_confirmation').setValue('Different123!')

    await wrapper.get('form').trigger('submit')

    expect(post).not.toHaveBeenCalled()
    expect(wrapper.find('.field-error').exists()).toBe(true)
  })
})
