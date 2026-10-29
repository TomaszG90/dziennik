import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { defineComponent } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'
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
      { path: '/login', name: 'login', component: Placeholder },
      { path: '/register', name: 'register', component: Placeholder },
      { path: '/dashboard', name: 'dashboard', component: Placeholder },
    ],
  })

  const wrapper = mount(AppHeader, {
    global: { plugins: [pinia, router] },
  })

  await router.isReady()
  return wrapper
}

describe('AppHeader', () => {
  it('shows login and registration links to a guest', async () => {
    const wrapper = await mountHeader()

    expect(wrapper.text()).toContain('Zaloguj')
    expect(wrapper.text()).toContain('Zarejestruj się')
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.find('a[href="/dashboard"]').exists()).toBe(false)
  })

  it('shows the user name and logout button when authenticated', async () => {
    const wrapper = await mountHeader(true)

    expect(wrapper.text()).toContain('Ala Kowalska')
    expect(wrapper.find('button').text()).toBe('Wyloguj')
    expect(wrapper.text()).not.toContain('Zarejestruj się')
    expect(wrapper.get('a[href="/dashboard"]').text()).toBe('Mój dziennik')
  })
})
