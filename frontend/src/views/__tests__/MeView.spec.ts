import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'
import MeView from '../MeView.vue'
import * as tripsApi from '@/api/trips'

async function mountMeView() {
  const pinia = createPinia()
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/me', name: 'me', component: MeView },
      { path: '/trips/new', name: 'trip-create', component: { template: '<div>New Trip</div>' } },
    ],
  })

  await router.push('/me')
  await router.isReady()

  const wrapper = mount(MeView, {
    global: { plugins: [pinia, router] },
  })

  await flushPromises()
  return { wrapper, router }
}

describe('MeView', () => {
  it('renders UserTripsList in place of the trips tab placeholder', async () => {
    vi.spyOn(tripsApi, 'getTrips').mockResolvedValue([])

    const { wrapper } = await mountMeView()

    // It should NOT contain the old static text
    expect(wrapper.text()).not.toContain('Lista wycieczek pojawi się tutaj.')

    // It should render the UserTripsList component with section headings and actions
    expect(wrapper.find('[data-testid="create-trip-button"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Stwórz nową podróż')
    expect(wrapper.text()).toContain('Zaplanowane wycieczki')
    expect(wrapper.text()).toContain('Pora zaplanować nową wycieczkę')
    expect(wrapper.text()).toContain('Odbyte wycieczki')
  })

  it('switches tabs and hides UserTripsList when on friends tab', async () => {
    vi.spyOn(tripsApi, 'getTrips').mockResolvedValue([])

    const { wrapper } = await mountMeView()

    const friendsTab = wrapper.get('#tab-friends')
    await friendsTab.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Lista znajomych pojawi się tutaj.')
    expect(wrapper.find('[data-testid="create-trip-button"]').exists()).toBe(false)
  })
})
