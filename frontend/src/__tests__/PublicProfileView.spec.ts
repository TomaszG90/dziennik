import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import * as publicProfilesApi from '@/api/publicProfiles'
import type { Trip, TripsPage } from '@/api/trips'
import PublicProfileView from '@/views/PublicProfileView.vue'

vi.mock('@/components/MapView.vue', () => ({
  default: {
    name: 'MapView',
    props: ['places', 'legs'],
    template: '<div data-testid="profile-map" />',
  },
}))

function createPage(page: number, year?: number): TripsPage {
  const trip: Trip = {
    id: year ? year + page : page,
    name: `Podróż ${year ?? 'wszystkie'} ${page}`,
    start_date: `${year ?? 2025}-06-01`,
    end_date: `${year ?? 2025}-06-05`,
    description: null,
    created_at: '',
    updated_at: '',
  }

  return {
    data: [trip],
    meta: { current_page: page, last_page: year ? 1 : 2, per_page: 5, total: year ? 1 : 2 },
  }
}

async function mountPublicProfile() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/users/:id', name: 'public-profile', component: PublicProfileView }],
  })
  await router.push('/users/7')
  await router.isReady()
  const wrapper = mount(PublicProfileView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

afterEach(() => {
  vi.restoreAllMocks()
})

describe('PublicProfileView trips', () => {
  it('appends five-item API pages to the public trip list', async () => {
    const getTrips = vi
      .spyOn(publicProfilesApi, 'getPublicProfileTrips')
      .mockResolvedValueOnce(createPage(1))
      .mockResolvedValueOnce(createPage(2))
    vi.spyOn(publicProfilesApi, 'getPublicProfile').mockResolvedValue({
      id: 7,
      name: 'Ala',
      created_at: '',
    })
    vi.spyOn(publicProfilesApi, 'getPublicProfileMap').mockResolvedValue({
      trips: [
        { id: 1, name: 'Podróż', start_date: '2025-06-01', end_date: '2025-06-05' },
        { id: 2, name: 'Inna podróż', start_date: '2024-06-01', end_date: null },
      ],
      places: [],
      legs: [],
    })
    vi.spyOn(publicProfilesApi, 'getPublicProfileStats').mockResolvedValue({
      trips_count: 2,
      countries: [],
      total_distance: 0,
      distance_by_transport: {},
      places_count: 0,
    })

    const wrapper = await mountPublicProfile()

    expect(wrapper.text()).toContain('Wyświetlono 1 z 2 podróży')
    expect(wrapper.text()).toContain('Podróż wszystkie 1')
    expect(wrapper.get('.trip-year-filter select').element).toHaveProperty('value', '')

    await wrapper.get('.trips-pagination button').trigger('click')
    await flushPromises()

    expect(getTrips).toHaveBeenLastCalledWith('7', 2, undefined)
    expect(wrapper.text()).toContain('Podróż wszystkie 1')
    expect(wrapper.text()).toContain('Podróż wszystkie 2')
    expect(wrapper.text()).toContain('Wyświetlono 2 z 2 podróży')
  })

  it('filters public trips by selected year and starts again from the first batch', async () => {
    const getTrips = vi
      .spyOn(publicProfilesApi, 'getPublicProfileTrips')
      .mockResolvedValueOnce(createPage(1))
      .mockResolvedValueOnce(createPage(1, 2024))
    vi.spyOn(publicProfilesApi, 'getPublicProfile').mockResolvedValue({
      id: 7,
      name: 'Ala',
      created_at: '',
    })
    vi.spyOn(publicProfilesApi, 'getPublicProfileMap').mockResolvedValue({
      trips: [
        { id: 2, name: 'Podróż 2024', start_date: '2024-06-01', end_date: null },
        { id: 3, name: 'Podróż 2025', start_date: '2025-06-01', end_date: null },
      ],
      places: [
        {
          id: 21,
          trip_id: 2,
          name: 'Kraków',
          latitude: 50,
          longitude: 20,
          position: 1,
          country_code: 'PL',
          trip_name: 'Podróż 2024',
        },
        {
          id: 22,
          trip_id: 3,
          name: 'Praga',
          latitude: 50,
          longitude: 14,
          position: 1,
          country_code: 'CZ',
          trip_name: 'Podróż 2025',
        },
      ],
      legs: [
        {
          id: 1,
          trip_id: 2,
          from_place_id: 21,
          to_place_id: 21,
          transport: 'car',
          distance: 0,
          position: 1,
          trip_name: 'Podróż 2024',
        },
        {
          id: 2,
          trip_id: 3,
          from_place_id: 22,
          to_place_id: 22,
          transport: 'car',
          distance: 0,
          position: 1,
          trip_name: 'Podróż 2025',
        },
      ],
    })
    vi.spyOn(publicProfilesApi, 'getPublicProfileStats').mockResolvedValue({
      trips_count: 1,
      countries: [],
      total_distance: 0,
      distance_by_transport: {},
      places_count: 0,
    })

    const wrapper = await mountPublicProfile()
    const mapView = wrapper.findComponent({ name: 'MapView' })
    expect(mapView.props('places')).toHaveLength(2)

    await wrapper.get('.trip-year-filter select').setValue('2024')
    await flushPromises()

    expect(getTrips).toHaveBeenLastCalledWith('7', 1, 2024)
    expect(wrapper.text()).toContain('Podróż 2024 1')
    expect(wrapper.text()).toContain('Wyświetlono 1 z 1 podróży')
    expect(mapView.props('places')).toEqual([expect.objectContaining({ trip_id: 2 })])
    expect(mapView.props('legs')).toEqual([expect.objectContaining({ trip_id: 2 })])
  })
})
