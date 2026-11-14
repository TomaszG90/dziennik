import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import UserTripsList from '@/components/trips/UserTripsList.vue'
import * as tripsApi from '@/api/trips'

function createTestRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
      { path: '/trips/new', name: 'trip-create', component: { template: '<div>Create</div>' } },
      { path: '/trips/:id', name: 'trip-details', component: { template: '<div>Details</div>' } },
    ],
  })
}

function tripsPage(
  data: tripsApi.Trip[],
  currentPage = 1,
  total = data.length,
  lastPage = Math.max(1, Math.ceil(total / 5)),
) {
  return {
    data,
    meta: {
      current_page: currentPage,
      last_page: lastPage,
      per_page: 5,
      total,
    },
  }
}

describe('UserTripsList', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows loading state while fetching trips', async () => {
    // Hang promise to keep loading state
    vi.spyOn(tripsApi, 'getTripsPage').mockImplementation(() => new Promise(() => {}))
    const router = createTestRouter()

    const wrapper = mount(UserTripsList, {
      global: { plugins: [router] },
      props: { autoFetch: true },
    })

    expect(wrapper.find('[data-testid="trips-loading"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Ładowanie podróży...')
  })

  it('appends five more trips when requesting the next batch', async () => {
    const firstPageTrips: tripsApi.Trip[] = Array.from({ length: 5 }, (_, index) => ({
      id: index + 1,
      name: `Wycieczka ${index + 1}`,
      start_date: '2030-08-01',
      end_date: '2030-08-05',
      description: null,
      created_at: '',
      updated_at: '',
    }))
    const getTripsPageSpy = vi
      .spyOn(tripsApi, 'getTripsPage')
      .mockImplementation((query = {}) => {
        const { page, status } = query
        if (status === 'planned' && page === 1) {
          return Promise.resolve(tripsPage(firstPageTrips, 1, 6, 2))
        }
        if (status === 'planned') {
          return Promise.resolve(
            tripsPage([{ ...firstPageTrips[0]!, id: 6, name: 'Wycieczka 6' }], 2, 6),
          )
        }
        return Promise.resolve(tripsPage([]))
      })
    const wrapper = mount(UserTripsList, {
      global: { plugins: [createTestRouter()] },
      props: { autoFetch: true },
    })

    await flushPromises()
    expect(getTripsPageSpy).toHaveBeenNthCalledWith(1, { page: 1, status: 'planned' })
    expect(getTripsPageSpy).toHaveBeenNthCalledWith(2, { page: 1, status: 'past' })
    expect(wrapper.findAll('[data-testid="trip-card"]')).toHaveLength(5)
    expect(wrapper.text()).toContain('Wyświetlono 5 z 6 zaplanowanych wycieczek')

    await wrapper.get('.planned-section button.load-more-button').trigger('click')
    await flushPromises()

    expect(getTripsPageSpy).toHaveBeenNthCalledWith(3, { page: 2, status: 'planned' })
    expect(wrapper.findAll('[data-testid="trip-card"]')).toHaveLength(6)
    expect(wrapper.text()).toContain('Wycieczka 1')
    expect(wrapper.text()).toContain('Wycieczka 6')
    expect(wrapper.text()).toContain('Wyświetlono 6 z 6 zaplanowanych wycieczek')
  })

  it('shows error state when API fails and retries on button click', async () => {
    let plannedRequestCount = 0
    const getTripsPageSpy = vi
      .spyOn(tripsApi, 'getTripsPage')
      .mockImplementation((query = {}) => {
        if (query.status === 'planned' && plannedRequestCount++ === 0) {
          return Promise.reject(new Error('Serwer niedostępny'))
        }
        return Promise.resolve(tripsPage([]))
      })
    const router = createTestRouter()

    const wrapper = mount(UserTripsList, {
      global: { plugins: [router] },
      props: { autoFetch: true },
    })

    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Serwer niedostępny')

    const retryBtn = wrapper.get('.retry-button')
    await retryBtn.trigger('click')
    await flushPromises()

    expect(getTripsPageSpy).toHaveBeenCalledTimes(3)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('renders section 1 with "Stwórz nową podróż" button and handles click', async () => {
    vi.spyOn(tripsApi, 'getTripsPage').mockResolvedValue(tripsPage([]))
    const router = createTestRouter()

    const wrapper = mount(UserTripsList, {
      global: { plugins: [router] },
      props: { autoFetch: true },
    })

    await flushPromises()

    const createBtn = wrapper.find('[data-testid="create-trip-button"]')
    expect(createBtn.exists()).toBe(true)
    expect(createBtn.text()).toContain('Stwórz nową podróż')

    await createBtn.trigger('click')
    await flushPromises()

    expect(wrapper.emitted('create')).toBeTruthy()
    expect(router.currentRoute.value.path).toBe('/trips/new')
  })

  it('displays empty state "Pora zaplanować nową wycieczkę" when there are no planned trips', async () => {
    vi.spyOn(tripsApi, 'getTripsPage').mockResolvedValue(tripsPage([]))
    const router = createTestRouter()

    const wrapper = mount(UserTripsList, {
      global: { plugins: [router] },
      props: { autoFetch: true },
    })

    await flushPromises()

    const emptyPlanned = wrapper.find('[data-testid="planned-empty-state"]')
    expect(emptyPlanned.exists()).toBe(true)
    expect(emptyPlanned.text()).toContain('Pora zaplanować nową wycieczkę')

    const emptyPast = wrapper.find('[data-testid="past-empty-state"]')
    expect(emptyPast.exists()).toBe(true)
    expect(emptyPast.text()).toContain('Brak odbytych wycieczek')
  })

  it('correctly partitions trips into planned and past trips', async () => {
    const mockTrips: tripsApi.Trip[] = [
      {
        id: 1,
        name: 'Tatry Wysokie 2030',
        start_date: '2030-08-01',
        end_date: '2030-08-10',
        description: 'Wspinaczka na Rysy',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        is_invitation: false,
      },
      {
        id: 2,
        name: 'Rzym ze znajomymi 2030',
        start_date: '2030-09-15',
        end_date: '2030-09-20',
        description: 'Zaproszenie od Marka na wycieczkę do Rzymu',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        is_invitation: true,
        inviter: { name: 'Marek' },
      },
      {
        id: 3,
        name: 'Mazury 2020',
        start_date: '2020-07-01',
        end_date: '2020-07-14',
        description: 'Żeglowanie po jeziorach',
        created_at: '2020-01-01T00:00:00Z',
        updated_at: '2020-01-01T00:00:00Z',
        is_invitation: false,
      },
    ]

    vi.spyOn(tripsApi, 'getTripsPage').mockResolvedValue(tripsPage(mockTrips))
    const router = createTestRouter()

    const wrapper = mount(UserTripsList, {
      global: { plugins: [router] },
      props: { autoFetch: true },
    })

    await flushPromises()

    // Planned section (Tatry and Rzym)
    const plannedSection = wrapper.get('[data-testid="planned-section"]')
    expect(plannedSection.text()).toContain('Tatry Wysokie 2030')
    expect(plannedSection.text()).toContain('Rzym ze znajomymi 2030')
    expect(plannedSection.text()).toContain('Własna')
    expect(plannedSection.text()).toContain('Zaproszenie: Marek')
    expect(plannedSection.find('[data-testid="planned-empty-state"]').exists()).toBe(false)

    // Past section (Mazury)
    const pastSection = wrapper.get('[data-testid="past-section"]')
    expect(pastSection.text()).toContain('Mazury 2020')
    expect(pastSection.find('[data-testid="past-empty-state"]').exists()).toBe(false)
  })

  it('sorts planned trips newest-first and past trips oldest-first', async () => {
    const trips: tripsApi.Trip[] = [
      {
        id: 1,
        name: 'Planowana starsza',
        start_date: '2030-05-01',
        end_date: '2030-05-03',
        description: null,
        created_at: '',
        updated_at: '',
      },
      {
        id: 2,
        name: 'Planowana nowsza',
        start_date: '2031-05-01',
        end_date: '2031-05-03',
        description: null,
        created_at: '',
        updated_at: '',
      },
      {
        id: 3,
        name: 'Odbyta starsza',
        start_date: '2020-05-01',
        end_date: '2020-05-03',
        description: null,
        created_at: '',
        updated_at: '',
      },
      {
        id: 4,
        name: 'Odbyta nowsza',
        start_date: '2021-05-01',
        end_date: '2021-05-03',
        description: null,
        created_at: '',
        updated_at: '',
      },
    ]

    const wrapper = mount(UserTripsList, {
      global: { plugins: [createTestRouter()] },
      props: { initialTrips: trips, autoFetch: false },
    })

    expect(
      wrapper.findAll('[data-testid="trip-card"] .trip-name').map((item) => item.text()),
    ).toEqual(['Planowana starsza', 'Planowana nowsza'])
    expect(
      wrapper.findAll('[data-testid="past-trip-card"] .trip-name').map((item) => item.text()),
    ).toEqual(['Odbyta nowsza', 'Odbyta starsza'])
  })

  it('highlights planned and past trip dates and their matching list cards', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date(2026, 5, 15, 12))

    const plannedTrip: tripsApi.Trip = {
      id: 21,
      name: 'Wypad w góry',
      start_date: '2026-06-20T00:00:00.000000Z',
      end_date: '2026-06-22T00:00:00.000000Z',
      description: null,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }
    const pastTrip: tripsApi.Trip = {
      id: 22,
      name: 'Majówka',
      start_date: '2026-05-10',
      end_date: '2026-05-12',
      description: null,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }
    const wrapper = mount(UserTripsList, {
      global: { plugins: [createTestRouter()] },
      props: { initialTrips: [plannedTrip, pastTrip], autoFetch: false },
    })

    expect(wrapper.get('.calendar-month').text()).toBe('czerwiec 2026')
    expect(wrapper.findAll('.calendar-weekday').map((day) => day.text())).toEqual([
      'Pn',
      'Wt',
      'Śr',
      'Cz',
      'Pt',
      'So',
      'Nd',
    ])
    expect(wrapper.get('[data-date="2026-06-20"]').classes()).toContain('calendar-day--planned')
    expect(wrapper.get('[data-date="2026-06-20"]').attributes('aria-label')).toContain(
      'Wypad w góry',
    )
    expect(wrapper.get('.trip-date').text()).toBe('20.06.2026 – 22.06.2026')

    await wrapper.get('[data-date="2026-06-21"]').trigger('click')
    expect(wrapper.get('[data-testid="trip-card"]').classes()).toContain(
      'trip-item--calendar-highlighted',
    )

    await wrapper.get('[aria-label="Poprzedni miesiąc"]').trigger('click')
    expect(wrapper.get('.calendar-month').text()).toBe('maj 2026')
    expect(wrapper.get('[data-date="2026-05-11"]').classes()).toContain('calendar-day--past')
    await wrapper.get('[data-date="2026-05-11"]').trigger('click')
    expect(wrapper.get('[data-testid="past-trip-card"]').classes()).toContain(
      'trip-item--calendar-highlighted',
    )

    await wrapper
      .get('[aria-label="Pokaż termin podróży Wypad w góry w kalendarzu"]')
      .trigger('click')
    expect(wrapper.get('.calendar-month').text()).toBe('czerwiec 2026')
    expect(wrapper.get('[data-date="2026-06-20"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.get('[data-testid="trip-card"]').classes()).toContain(
      'trip-item--calendar-highlighted',
    )
  })

  it('navigates to trip details when clicking details button', async () => {
    const mockTrips: tripsApi.Trip[] = [
      {
        id: 42,
        name: 'Góry Świętokrzyskie',
        start_date: '2030-05-01',
        end_date: '2030-05-03',
        description: 'Łysica i okolice',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
      },
    ]

    vi.spyOn(tripsApi, 'getTripsPage').mockResolvedValue(tripsPage(mockTrips))
    const router = createTestRouter()

    const wrapper = mount(UserTripsList, {
      global: { plugins: [router] },
      props: { autoFetch: true },
    })

    await flushPromises()

    const detailsBtn = wrapper.get('.details-button')
    await detailsBtn.trigger('click')
    await flushPromises()

    expect(wrapper.emitted('select')).toEqual([[42]])
    expect(router.currentRoute.value.path).toBe('/trips/42')
  })
})
