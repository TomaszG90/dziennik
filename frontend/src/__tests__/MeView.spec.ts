import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import * as statsApi from '@/api/stats'
import MeView from '@/views/MeView.vue'
import * as tripsApi from '@/api/trips'

afterEach(() => {
  vi.restoreAllMocks()
})

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

  it('filters statistics by year and shows distance by transport', async () => {
    vi.spyOn(tripsApi, 'getTrips').mockResolvedValue([
      {
        id: 1,
        name: 'Podróż',
        start_date: '2024-06-01',
        end_date: null,
        description: null,
        created_at: '',
        updated_at: '',
      },
    ])
    const getTravelStats = vi.spyOn(statsApi, 'getTravelStats')
    getTravelStats
      .mockResolvedValueOnce({
        trips_count: 1,
        countries: ['PL'],
        total_distance: 120,
        distance_by_transport: { car: 80, train: 40 },
        places_count: 2,
      })
      .mockResolvedValueOnce({
        trips_count: 1,
        countries: ['PL'],
        total_distance: 30,
        distance_by_transport: { car: 30 },
        places_count: 1,
      })

    const { wrapper } = await mountMeView()
    await wrapper.get('#tab-stats').trigger('click')
    await flushPromises()

    expect(wrapper.get('#stats-year').text()).toContain('Cała historia')
    expect(wrapper.get('#stats-year').text()).toContain('2024')
    expect(getTravelStats).toHaveBeenNthCalledWith(1, undefined)
    expect(wrapper.get('.distance-chart').text()).toContain('Samochodem')
    expect(wrapper.get('.distance-chart').text()).toContain('Pociągiem')
    expect(wrapper.get('.transport-donut').attributes('aria-label')).toContain('Udział')
    expect(wrapper.findAll('.donut-segment')).toHaveLength(2)
    expect(wrapper.get('.distance-legend').text()).toContain('66,7%')
    expect(wrapper.get('.distance-legend').text()).toContain('33,3%')
    expect(wrapper.findAll('.chart-bar-track span').map((bar) => bar.attributes('style'))).toEqual(
      expect.arrayContaining([
        expect.stringContaining('width: 100%'),
        expect.stringContaining('width: 50%'),
      ]),
    )

    await wrapper.get('#stats-year').setValue('2024')
    await flushPromises()

    expect(getTravelStats).toHaveBeenNthCalledWith(2, 2024)
    expect(wrapper.get('.distance-chart').text()).toContain('30 km')
    expect(wrapper.get('.distance-chart').text()).not.toContain('Pociągiem')
  })
})
