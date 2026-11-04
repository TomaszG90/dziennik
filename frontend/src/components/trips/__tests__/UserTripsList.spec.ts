import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import UserTripsList from '../UserTripsList.vue'
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
