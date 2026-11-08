import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as geocodingApi from '@/api/geocoding'
import * as tripsApi from '@/api/trips'
import TripDetailsView from '@/views/TripDetailsView.vue'

vi.mock('@/components/MapView.vue', () => ({
  default: {
    template: '<div data-testid="route-map-canvas" role="application" aria-label="Mapa podróży" />',
  },
}))

const trip: tripsApi.Trip = {
  id: 5,
  name: 'Podróż testowa',
  start_date: '2026-07-01',
  end_date: '2026-07-03',
  description: null,
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
}

const placeA: tripsApi.TripPlace = {
  id: 10,
  trip_id: trip.id,
  name: 'Kraków',
  latitude: 50.0647,
  longitude: 19.945,
  position: 1,
  country_code: 'PL',
}

const placeB: tripsApi.TripPlace = {
  id: 11,
  trip_id: trip.id,
  name: 'Wiedeń',
  latitude: 48.2082,
  longitude: 16.3738,
  position: 2,
  country_code: 'AT',
}

function createTestRouter() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'me', component: { template: '<div />' } },
      { path: '/trips/:id', name: 'trip-details', component: TripDetailsView },
      { path: '/trips/:id/edit', name: 'trip-edit', component: { template: '<div />' } },
    ],
  })
  return router
}

async function mountDetails(places = [placeA, placeB], legs: tripsApi.TripLeg[] = []) {
  vi.spyOn(tripsApi, 'getTrip').mockResolvedValue(trip)
  vi.spyOn(tripsApi, 'getTripPlaces').mockResolvedValue(places)
  vi.spyOn(tripsApi, 'getTripLegs').mockResolvedValue(legs)

  const router = createTestRouter()
  await router.push('/trips/5')
  await router.isReady()
  const wrapper = mount(TripDetailsView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

describe('TripDetailsView route editing', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    localStorage.clear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the base map in the trip details view', async () => {
    const wrapper = await mountDetails([])

    expect(wrapper.get('#route-map-title').text()).toBe('Mapa podróży')
    expect(wrapper.get('[data-testid="route-map-canvas"]').attributes('role')).toBe('application')
    expect(wrapper.get('.itinerary-layout').find('.map-panel').exists()).toBe(true)
  })

  it('shows distinct visited country codes and invited guests in the trip summary', async () => {
    const wrapper = await mountDetails([placeA, placeB, { ...placeA, id: 12, position: 3 }])

    await wrapper.get('input[placeholder="Wyszukaj znajomych"]').setValue('Anna')
    await wrapper.get('.extra-card form').trigger('submit')

    expect(wrapper.get('.trip-stat-countries').text()).toContain('PL')
    expect(wrapper.get('.trip-stat-countries').text()).toContain('AT')
    expect(wrapper.findAll('.country-code-list span')).toHaveLength(2)
    expect(wrapper.get('.trip-stat-guests').text()).toContain('Goście')
    expect(wrapper.findAll('.guest-avatar')).toHaveLength(1)
    expect(wrapper.get('.guest-avatar').attributes('aria-label')).toBe('Anna')
    expect(wrapper.get('.trip-stats').text()).not.toContain('odcinków')
  })

  it('looks up a place, fills its location and adds it', async () => {
    vi.useFakeTimers()
    const searchPlaces = vi.spyOn(geocodingApi, 'searchPlaces').mockResolvedValue([
      {
        name: 'Graz',
        latitude: 47.0707,
        longitude: 15.4395,
        street: null,
        housenumber: null,
        postcode: null,
        city: 'Graz',
        district: null,
        county: null,
        state: 'Styria',
        country: 'Austria',
        country_code: 'at',
      },
    ])
    const createPlace = vi.spyOn(tripsApi, 'createTripPlace').mockResolvedValue({
      ...placeB,
      id: 12,
      name: 'Graz',
      latitude: 47.0707,
      longitude: 15.4395,
      position: 3,
      country_code: 'AT',
    })
    const createLeg = vi.spyOn(tripsApi, 'createTripLeg').mockResolvedValue({
      id: 22,
      trip_id: trip.id,
      from_place_id: placeA.id,
      to_place_id: 12,
      transport: 'other',
      distance: 331.8,
      position: 1,
    })
    const wrapper = await mountDetails([placeA])

    await wrapper.get('input[placeholder="Wpisz nazwę miasta lub miejsca"]').setValue('Graz')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(searchPlaces).toHaveBeenCalledWith('Graz')
    expect(wrapper.get('[data-testid="place-suggestion-0"]').text()).toContain('Graz')
    expect(wrapper.get('[data-testid="place-suggestion-0"]').text()).toContain('Styria')
    expect(wrapper.get('[data-testid="place-suggestion-0"]').text()).toContain('Austria')
    expect(wrapper.get('.suggestion-details').text()).toContain('Graz · Styria · Austria · AT')
    await wrapper.get('[data-testid="place-suggestion-0"]').trigger('click')
    expect(wrapper.get('[data-testid="selected-place-summary"]').text()).toContain('Graz')
    expect(wrapper.get('[data-testid="selected-place-summary"]').text()).toContain('Styria')
    expect(wrapper.get('[data-testid="selected-place-summary"]').text()).toContain('AT')
    expect(wrapper.get('[data-testid="selected-place-summary"]').text()).toContain(
      '47.0707, 15.4395',
    )
    expect(wrapper.find('.place-search-results').exists()).toBe(false)
    await wrapper.get('.place-form').trigger('submit')
    await flushPromises()

    expect(createPlace).toHaveBeenCalledWith('5', {
      name: 'Graz',
      latitude: 47.0707,
      longitude: 15.4395,
      country_code: 'AT',
    })
    expect(createLeg).toHaveBeenCalledWith('5', {
      from_place_id: placeA.id,
      to_place_id: 12,
      transport: 'other',
      distance: expect.any(Number),
    })
    expect(wrapper.find('[data-testid="route-leg"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Graz')
  })

  it('reorders route points through the API and deletes points', async () => {
    const reordered = vi.spyOn(tripsApi, 'reorderTripPlaces').mockResolvedValue([
      { ...placeB, position: 1 },
      { ...placeA, position: 2 },
    ])
    const leg: tripsApi.TripLeg = {
      id: 20,
      trip_id: trip.id,
      from_place_id: placeA.id,
      to_place_id: placeB.id,
      transport: 'train',
      distance: 400,
      position: 1,
    }
    const updateLeg = vi.spyOn(tripsApi, 'updateTripLeg').mockResolvedValue({
      ...leg,
      from_place_id: placeB.id,
      to_place_id: placeA.id,
      distance: 400,
    })
    const removePlace = vi.spyOn(tripsApi, 'deleteTripPlace').mockResolvedValue()
    const wrapper = await mountDetails([placeA, placeB], [leg])

    await wrapper.get('[aria-label="Przenieś Kraków niżej"]').trigger('click')
    await flushPromises()

    expect(reordered).toHaveBeenCalledWith('5', [placeB.id, placeA.id])
    expect(updateLeg).toHaveBeenCalledWith('5', leg.id, {
      from_place_id: placeB.id,
      to_place_id: placeA.id,
      distance: expect.any(Number),
    })
    expect(wrapper.findAll('.place-copy h3').map((heading) => heading.text())).toEqual([
      'Wiedeń',
      'Kraków',
    ])

    await wrapper.get('[aria-label="Usuń punkt Wiedeń"]').trigger('click')
    await flushPromises()

    expect(removePlace).toHaveBeenCalledWith('5', placeB.id)
    expect(wrapper.findAll('.place-copy h3').map((heading) => heading.text())).toEqual(['Kraków'])
  })

  it('adds a leg, updates its transport and removes it', async () => {
    const leg: tripsApi.TripLeg = {
      id: 20,
      trip_id: trip.id,
      from_place_id: placeA.id,
      to_place_id: placeB.id,
      transport: 'train',
      distance: 400,
      position: 1,
    }
    const createLeg = vi.spyOn(tripsApi, 'createTripLeg').mockResolvedValue(leg)
    const updateLeg = vi.spyOn(tripsApi, 'updateTripLeg').mockResolvedValue({
      ...leg,
      transport: 'plane',
    })
    const removeLeg = vi.spyOn(tripsApi, 'deleteTripLeg').mockResolvedValue()
    const wrapper = await mountDetails()

    expect((wrapper.get('.missing-leg select').element as HTMLSelectElement).value).toBe('other')
    expect(wrapper.get('.missing-leg select').text()).toContain('Inny')
    await wrapper.get('.missing-leg select').setValue('train')
    await wrapper.get('.missing-leg').trigger('submit')
    await flushPromises()

    expect(createLeg).toHaveBeenCalledWith('5', {
      from_place_id: placeA.id,
      to_place_id: placeB.id,
      transport: 'train',
      distance: expect.any(Number),
    })
    expect(wrapper.find('[data-testid="route-leg"]').exists()).toBe(true)

    await wrapper.get(`#transport-${leg.id}`).setValue('plane')
    await flushPromises()
    expect(updateLeg).toHaveBeenCalledWith('5', leg.id, { transport: 'plane' })

    await wrapper.get(`[aria-label="Usuń odcinek Kraków – Wiedeń"]`).trigger('click')
    await flushPromises()
    expect(removeLeg).toHaveBeenCalledWith('5', leg.id)
    expect(wrapper.find('[data-testid="route-leg"]').exists()).toBe(false)
  })

  it('estimates missing leg distances from coordinates across country borders', async () => {
    const leg: tripsApi.TripLeg = {
      id: 21,
      trip_id: trip.id,
      from_place_id: placeA.id,
      to_place_id: placeB.id,
      transport: 'train',
      distance: null,
      position: 1,
    }
    const wrapper = await mountDetails([placeA, placeB], [leg])

    expect(wrapper.get('.leg-distance').text()).toContain('331,8 km')
    expect(wrapper.get('.trip-stats').text()).toContain('331,8 km')
  })
})
