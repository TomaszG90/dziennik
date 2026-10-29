import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { defineComponent } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'
import { useAuthStore } from '@/stores/auth'
import HomeView from './HomeView.vue'

const Placeholder = defineComponent({ render: () => null })

async function mountHome(authenticated = false) {
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
    ],
  })

  const wrapper = mount(HomeView, {
    global: { plugins: [pinia, router] },
  })

  await router.isReady()
  return wrapper
}

describe('HomeView', () => {
  it('shows the introduction and account links to a guest', async () => {
    const wrapper = await mountHome()

    expect(wrapper.get('h1').text()).toContain('Wspomnienia z drogi')
    expect(wrapper.get('a[href="/register"]').text()).toContain('Załóż konto')
    expect(wrapper.get('a[href="/login"]').text()).toContain('Zaloguj się')
  })

  it('greets an authenticated user without showing account links', async () => {
    const wrapper = await mountHome(true)

    expect(wrapper.text()).toContain('Witaj ponownie, Ala Kowalska')
    expect(wrapper.find('a[href="/register"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/login"]').exists()).toBe(false)
  })
})
