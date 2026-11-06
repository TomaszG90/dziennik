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

describe('UserTripsList', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows loading state while fetching trips', async () => {
    // Hang promise to keep loading state
    vi.spyOn(tripsApi, 'getTrips').mockImplementation(() => new Promise(() => {}))
    const router = createTestRouter()

    const wrapper = mount(UserTripsList, {
      global: { plugins: [router] },
      props: { autoFetch: true },
    })

    expect(wrapper.find('[data-testid="trips-loading"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Ładowanie podróży...')
  })

  it('shows error state when API fails and retries on button click', async () => {
    const getTripsSpy = vi
      .spyOn(tripsApi, 'getTrips')
      .mockRejectedValueOnce(new Error('Serwer niedostępny'))
      .mockResolvedValueOnce([])
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

    expect(getTripsSpy).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('renders section 1 with "Stwórz nową podróż" button and handles click', async () => {
    vi.spyOn(tripsApi, 'getTrips').mockResolvedValue([])
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
    vi.spyOn(tripsApi, 'getTrips').mockResolvedValue([])
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

    vi.spyOn(tripsApi, 'getTrips').mockResolvedValue(mockTrips)
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

    await wrapper.get('[aria-label="Pokaż termin podróży Wypad w góry w kalendarzu"]').trigger('click')
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

    vi.spyOn(tripsApi, 'getTrips').mockResolvedValue(mockTrips)
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
