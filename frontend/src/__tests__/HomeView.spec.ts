import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { defineComponent } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'
import { useAuthStore } from '@/stores/auth'
import HomeView from '@/views/HomeView.vue'

const Placeholder = defineComponent({ render: () => null })

async function mountHome(authenticated = false, qrCodeUrl?: string | null) {
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
    ],
  })

  const wrapper = mount(HomeView, {
    props: { qrCodeUrl },
    global: { plugins: [pinia, router] },
  })

  await router.isReady()
  return wrapper
}

describe('HomeView', () => {
  it('shows the introduction and account links to a guest', async () => {
    const wrapper = await mountHome()

    expect(wrapper.get('h1').text()).toContain('Wspomnienia z drogi')
    expect(wrapper.get('#mobile-app-title').text()).toBe('Aplikacja mobilna w budowie')
    expect(wrapper.get('.qr-placeholder').text()).toContain('Miejsce na kod QR')
    expect(wrapper.get('a[href="/auth?mode=register"]').text()).toContain('Załóż konto')
    expect(wrapper.get('a[href="/auth"]').text()).toContain('Zaloguj się')
  })

  it('greets an authenticated user without showing account links', async () => {
    const wrapper = await mountHome(true)

    expect(wrapper.text()).toContain('Witaj ponownie, Ala Kowalska')
    expect(wrapper.find('a[href="/auth?mode=register"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/auth"]').exists()).toBe(false)
  })

  it('shows a placeholder when no QR code is provided', async () => {
    const wrapper = await mountHome()

    expect(wrapper.find('.qr-code').exists()).toBe(false)
    expect(wrapper.get('.qr-placeholder').text()).toContain('Miejsce na kod QR')
  })

  it('shows the provided QR code and updates it when its URL changes', async () => {
    const wrapper = await mountHome(false, '/qr/app-v1.png')

    expect(wrapper.get('.qr-code').attributes('src')).toBe('/qr/app-v1.png')
    expect(wrapper.get('.qr-code').attributes('alt')).toBe('Kod QR do pobrania aplikacji mobilnej')
    expect(wrapper.find('.qr-placeholder').exists()).toBe(false)

    await wrapper.setProps({ qrCodeUrl: '/qr/app-v2.png' })

    expect(wrapper.get('.qr-code').attributes('src')).toBe('/qr/app-v2.png')
  })
})
