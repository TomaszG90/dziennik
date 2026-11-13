import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter as makeRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import * as publicProfilesApi from '@/api/publicProfiles'
import PublicProfilesView from '@/views/PublicProfilesView.vue'
import PublicProfileView from '@/views/PublicProfileView.vue'

vi.mock('@/components/MapView.vue', () => ({
  default: {
    name: 'MapView',
    props: ['places', 'legs'],
    template: '<div data-testid="public-profile-map" />',
  },
}))

afterEach(() => {
  vi.restoreAllMocks()
})

function createTestRouter() {
  return makeRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/users', name: 'public-profiles', component: PublicProfilesView },
      { path: '/users/:id', name: 'public-profile', component: PublicProfileView },
    ],
  })
}

async function mountAt(path: string) {
  const router = createTestRouter()
  await router.push(path)
  await router.isReady()
  const component =
    router.currentRoute.value.name === 'public-profile' ? PublicProfileView : PublicProfilesView
  const wrapper = mount(component, {
    global: { plugins: [router] },
  })
  await flushPromises()
  return wrapper
}

describe('PublicProfilesView', () => {
  it('lists public profiles with a link to each profile', async () => {
    vi.spyOn(publicProfilesApi, 'getPublicProfiles').mockResolvedValue({
      data: [
        {
          id: 8,
          name: 'Jan Kowalski',
          city: 'Warszawa',
          created_at: '2026-01-01T00:00:00Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })

    const wrapper = await mountAt('/users')

    expect(wrapper.text()).toContain('Jan Kowalski')
    expect(wrapper.text()).toContain('Warszawa')
    expect(wrapper.get('a[href="/users/8"]').text()).toContain('Zobacz profil')
  })

  it('shows a public profile with trips, stats, and a map', async () => {
    vi.spyOn(publicProfilesApi, 'getPublicProfile').mockResolvedValue({
      id: 8,
      name: 'Jan Kowalski',
      city: 'Warszawa',
      created_at: '2026-01-01T00:00:00Z',
    })
    vi.spyOn(publicProfilesApi, 'getPublicProfileTrips').mockResolvedValue([
      {
        id: 31,
        name: 'Podróż do Berlina',
        start_date: '2026-06-01',
        end_date: '2026-06-05',
        description: 'Letni wyjazd',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
      },
    ])
    vi.spyOn(publicProfilesApi, 'getPublicProfileMap').mockResolvedValue({
      trips: [
        { id: 31, name: 'Podróż do Berlina', start_date: '2026-06-01', end_date: '2026-06-05' },
      ],
      places: [
        {
          id: 41,
          trip_id: 31,
          trip_name: 'Podróż do Berlina',
          name: 'Berlin',
          latitude: '52.52',
          longitude: '13.405',
          position: 1,
          country_code: 'DE',
        },
      ],
      legs: [],
    })
    vi.spyOn(publicProfilesApi, 'getPublicProfileStats').mockResolvedValue({
      trips_count: 1,
      countries: ['DE'],
      total_distance: 450,
      distance_by_transport: { train: 450 },
      places_count: 1,
    })

    const wrapper = await mountAt('/users/8')

    expect(wrapper.get('h1').text()).toBe('Jan Kowalski')
    expect(wrapper.text()).toContain('Podróż do Berlina')
    expect(wrapper.text()).toContain('450 km')
    expect(wrapper.find('[data-testid="public-profile-map"]').exists()).toBe(true)
  })
})
