import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { defineComponent } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import { useAuthStore } from '@/stores/auth'
import AppHeader from '../AppHeader.vue'

const Placeholder = defineComponent({ render: () => null })

async function mountHeader(authenticated = false) {
  const pinia = createPinia()

  if (authenticated) {
    const auth = useAuthStore(pinia)
    auth.user = { id: 1, name: 'Ala Kowalska', email: 'ala@example.com' }
    auth.token = 'test-token'
  }

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: Placeholder },
      { path: '/auth', name: 'auth', component: Placeholder },
      { path: '/dashboard', name: 'dashboard', component: Placeholder },
      { path: '/me', name: 'me', component: Placeholder },
    ],
  })

  const wrapper = mount(AppHeader, {
    global: { plugins: [pinia, router] },
  })

  await router.isReady()
  return { wrapper, router, pinia }
}

describe('AppHeader', () => {
  it('shows the unified account link to a guest', async () => {
    const { wrapper } = await mountHeader()

    expect(wrapper.get('a[href="/auth"]').text()).toBe('Konto')
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.find('a[href="/dashboard"]').exists()).toBe(false)
  })

  it('shows the user name and logout button when authenticated', async () => {
    const { wrapper } = await mountHeader(true)

    expect(wrapper.text()).toContain('Ala Kowalska')
    expect(wrapper.find('button').text()).toBe('Wyloguj')
    expect(wrapper.find('a[href="/auth"]').exists()).toBe(false)
    expect(wrapper.get('a[href="/dashboard"]').text()).toBe('Mój dziennik')
  })

  it('logs out and redirects to home on logout button click', async () => {
    const { wrapper, router, pinia } = await mountHeader(true)
    const auth = useAuthStore(pinia)
    const logoutSpy = vi.spyOn(auth, 'logout').mockResolvedValue()

    await router.push('/dashboard')
    await router.isReady()

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(logoutSpy).toHaveBeenCalledOnce()
    expect(router.currentRoute.value.path).toBe('/')
  })
})
