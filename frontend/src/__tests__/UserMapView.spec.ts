import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import * as tripsApi from '@/api/trips'
import UserMapView from '@/views/UserMapView.vue'

vi.mock('@/components/MapView.vue', () => ({
  default: {
    name: 'MapView',
    props: ['places', 'legs'],
    template: '<div data-testid="user-map-canvas" />',
  },
}))

afterEach(() => {
  vi.restoreAllMocks()
})

async function mountUserMap() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/map', name: 'user-map', component: UserMapView },
      { path: '/me', name: 'me', component: { template: '<div />' } },
      { path: '/trips/:id', name: 'trip-details', component: { template: '<div />' } },
    ],
  })
  await router.push('/map')
  await router.isReady()
  const wrapper = mount(UserMapView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('UserMapView', () => {
  it('shows completed trips and filters the map by year and selected trips', async () => {
    vi.spyOn(tripsApi, 'getUserMap').mockResolvedValue({
      trips: [
        { id: 1, name: 'Podróż A', start_date: '2024-05-01', end_date: '2024-05-03' },
        { id: 2, name: 'Podróż B', start_date: '2024-07-01', end_date: '2024-07-03' },
        { id: 3, name: 'Podróż C', start_date: '2025-02-01', end_date: '2025-02-03' },
        { id: 4, name: 'Planowana', start_date: '2099-02-01', end_date: '2099-02-03' },
      ],
      places: [1, 2, 3, 4].map((tripId) => ({
        id: tripId * 10,
        trip_id: tripId,
        name: `Miejsce ${tripId}`,
        latitude: 50,
        longitude: 20,
        position: 1,
        country_code: 'PL',
        trip_name: `Podróż ${tripId}`,
      })),
      legs: [
        {
          id: 1,
          trip_id: 1,
          from_place_id: 10,
          to_place_id: 10,
          transport: 'car',
          distance: 0,
          position: 1,
          trip_name: 'Podróż 1',
        },
        {
          id: 2,
          trip_id: 2,
          from_place_id: 20,
          to_place_id: 20,
          transport: 'train',
          distance: 0,
          position: 1,
          trip_name: 'Podróż 2',
        },
        {
          id: 3,
          trip_id: 3,
          from_place_id: 30,
          to_place_id: 30,
          transport: 'plane',
          distance: 0,
          position: 1,
          trip_name: 'Podróż 3',
        },
        {
          id: 4,
          trip_id: 4,
          from_place_id: 40,
          to_place_id: 40,
          transport: 'car',
          distance: 0,
          position: 1,
          trip_name: 'Planowana',
        },
      ],
    })

    const wrapper = await mountUserMap()

    expect(wrapper.findAll('.trip-filter-list input')).toHaveLength(3)
    expect(wrapper.get('a[aria-label="Szczegóły podróży Podróż A"]').attributes('href')).toBe(
      '/trips/1',
    )
    expect(wrapper.text()).not.toContain('Planowana')
    expect(wrapper.get('.user-map-summary').text()).toContain('3 podróży')
    expect(wrapper.findComponent({ name: 'MapView' }).props('places')).toHaveLength(3)

    await wrapper.get('#map-year').setValue('2024')

    expect(wrapper.get('.user-map-summary').text()).toContain('2 podróży')
    expect(wrapper.findComponent({ name: 'MapView' }).props('places')).toEqual([
      expect.objectContaining({ trip_id: 1 }),
      expect.objectContaining({ trip_id: 2 }),
    ])

    await wrapper.findAll('.trip-filter-list input')[0]!.setValue(false)

    expect(wrapper.get('.user-map-summary').text()).toContain('1 podróż')
    expect(wrapper.findComponent({ name: 'MapView' }).props('places')).toEqual([
      expect.objectContaining({ trip_id: 2 }),
    ])
    expect(wrapper.findComponent({ name: 'MapView' }).props('legs')).toEqual([
      expect.objectContaining({ trip_id: 2 }),
    ])
  })
})
