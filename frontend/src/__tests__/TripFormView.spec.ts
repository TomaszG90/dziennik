import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import TripFormView from '@/views/TripFormView.vue'
import * as tripsApi from '@/api/trips'
import { ApiError } from '@/api/types'

function createTestRouter(initialPath = '/trips/new') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/me', name: 'me', component: { template: '<div>Profile</div>' } },
      { path: '/trips/new', name: 'trip-create', component: TripFormView },
      { path: '/trips/:id/edit', name: 'trip-edit', component: TripFormView },
    ],
  })
  router.push(initialPath)
  return router
}

describe('TripFormView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders create mode with title and empty fields', async () => {
    const router = createTestRouter('/trips/new')
    await router.isReady()

    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
    })

    expect(wrapper.get('.page-title').text()).toBe('Stwórz nową podróż')
    expect(wrapper.get('[data-testid="submit-trip-button"]').text()).toBe('Utwórz podróż')
    expect(wrapper.find('[data-testid="delete-trip-trigger"]').exists()).toBe(false)
  })

  it('validates client-side required fields', async () => {
    const router = createTestRouter('/trips/new')
    await router.isReady()

    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
    })

    const createSpy = vi.spyOn(tripsApi, 'createTrip')

    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(createSpy).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="name-error"]').text()).toContain('Nazwa podróży jest wymagana')
    expect(wrapper.find('[data-testid="start-date-error"]').text()).toContain('Data rozpoczęcia jest wymagana')
  })

  it('rejects a trip name longer than 255 characters before calling the API', async () => {
    const router = createTestRouter('/trips/new')
    await router.isReady()
    const createSpy = vi.spyOn(tripsApi, 'createTrip')
    const wrapper = mount(TripFormView, { global: { plugins: [router] } })

    await wrapper.get('[data-testid="trip-name-input"]').setValue('a'.repeat(256))
    await wrapper.get('[data-testid="trip-start-date-input"]').setValue('2026-08-10')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(createSpy).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="name-error"]').text()).toContain('maksymalnie 255 znaków')
  })

  it('validates that end_date cannot be earlier than start_date', async () => {
    const router = createTestRouter('/trips/new')
    await router.isReady()

    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
    })

    await wrapper.get('[data-testid="trip-name-input"]').setValue('Weekend w górach')
    await wrapper.get('[data-testid="trip-start-date-input"]').setValue('2026-08-10')
    await wrapper.get('[data-testid="trip-end-date-input"]').setValue('2026-08-05')

    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.find('[data-testid="end-date-error"]').text()).toContain(
      'Data zakończenia nie może być wcześniejsza niż data rozpoczęcia',
    )
  })

  it('allows an end date equal to the start date and normalizes empty optional fields', async () => {
    const router = createTestRouter('/trips/new')
    await router.isReady()
    const createSpy = vi.spyOn(tripsApi, 'createTrip').mockResolvedValue({
      id: 11,
      name: 'Jednodniowa wycieczka',
      start_date: '2026-08-10',
      end_date: '2026-08-10',
      description: null,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    })

    const wrapper = mount(TripFormView, { global: { plugins: [router] } })
    await wrapper.get('[data-testid="trip-name-input"]').setValue('  Jednodniowa wycieczka  ')
    await wrapper.get('[data-testid="trip-start-date-input"]').setValue('2026-08-10')
    await wrapper.get('[data-testid="trip-end-date-input"]').setValue('2026-08-10')
    await wrapper.get('[data-testid="trip-description-input"]').setValue('   ')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(createSpy).toHaveBeenCalledWith({
      name: 'Jednodniowa wycieczka',
      start_date: '2026-08-10',
      end_date: '2026-08-10',
      description: null,
    })
  })

  it('shows a not-found message when the trip cannot be loaded', async () => {
    vi.spyOn(tripsApi, 'getTrip').mockRejectedValue(new ApiError('Not found', 404))
    const router = createTestRouter('/trips/404/edit')
    await router.isReady()

    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
      props: { tripId: 404 },
    })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Nie znaleziono podróży')
    expect(wrapper.find('[data-testid="trip-name-input"]').exists()).toBe(false)
  })

  it('creates trip on valid submit and navigates to /me', async () => {
    const router = createTestRouter('/trips/new')
    await router.isReady()

    const createSpy = vi.spyOn(tripsApi, 'createTrip').mockResolvedValue({
      id: 10,
      name: 'Mazury 2026',
      start_date: '2026-07-01',
      end_date: '2026-07-10',
      description: 'Rejs żaglówką',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    })

    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
    })

    await wrapper.get('[data-testid="trip-name-input"]').setValue('Mazury 2026')
    await wrapper.get('[data-testid="trip-start-date-input"]').setValue('2026-07-01')
    await wrapper.get('[data-testid="trip-end-date-input"]').setValue('2026-07-10')
    await wrapper.get('[data-testid="trip-description-input"]').setValue('Rejs żaglówką')

    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(createSpy).toHaveBeenCalledWith({
      name: 'Mazury 2026',
      start_date: '2026-07-01',
      end_date: '2026-07-10',
      description: 'Rejs żaglówką',
    })

    expect(router.currentRoute.value.path).toBe('/me')
  })

  it('handles backend 422 validation errors properly', async () => {
    const router = createTestRouter('/trips/new')
    await router.isReady()

    vi.spyOn(tripsApi, 'createTrip').mockRejectedValue(
      new ApiError('Niepoprawne dane formularza', 422, {
        name: ['Nazwa jest już zajęta.'],
      }),
    )

    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
    })

    await wrapper.get('[data-testid="trip-name-input"]').setValue('Zajęta nazwa')
    await wrapper.get('[data-testid="trip-start-date-input"]').setValue('2026-07-01')

    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.find('[data-testid="name-error"]').text()).toContain('Nazwa jest już zajęta.')
    expect(wrapper.find('[data-testid="form-error-banner"]').text()).toContain('Niepoprawne dane formularza')
  })

  it('loads trip data in edit mode and updates trip on submit', async () => {
    const mockTrip: tripsApi.Trip = {
      id: 5,
      name: 'Bieszczady 2026',
      start_date: '2026-09-01',
      end_date: '2026-09-05',
      description: 'Szlak graniczny',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    vi.spyOn(tripsApi, 'getTrip').mockResolvedValue(mockTrip)
    const updateSpy = vi.spyOn(tripsApi, 'updateTrip').mockResolvedValue(mockTrip)

    const router = createTestRouter('/trips/5/edit')
    await router.isReady()

    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
      props: { tripId: 5 },
    })

    await flushPromises()

    expect(wrapper.get('.page-title').text()).toBe('Edytuj podróż')
    expect((wrapper.get('[data-testid="trip-name-input"]').element as HTMLInputElement).value).toBe(
      'Bieszczady 2026',
    )
    expect((wrapper.get('[data-testid="trip-start-date-input"]').element as HTMLInputElement).value).toBe(
      '2026-09-01',
    )
    expect((wrapper.get('[data-testid="trip-end-date-input"]').element as HTMLInputElement).value).toBe(
      '2026-09-05',
    )

    // Change name and submit
    await wrapper.get('[data-testid="trip-name-input"]').setValue('Bieszczady Jesień 2026')
    await wrapper.get('form').trigger('submit.prevent')
    await flushPromises()

    expect(updateSpy).toHaveBeenCalledWith(5, {
      name: 'Bieszczady Jesień 2026',
      start_date: '2026-09-01',
      end_date: '2026-09-05',
      description: 'Szlak graniczny',
    })
    expect(router.currentRoute.value.path).toBe('/me')
  })

  it('deletes trip with confirmation modal in edit mode', async () => {
    const mockTrip: tripsApi.Trip = {
      id: 5,
      name: 'Podróż do skasowania',
      start_date: '2026-09-01',
      end_date: null,
      description: null,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }

    vi.spyOn(tripsApi, 'getTrip').mockResolvedValue(mockTrip)
    const deleteSpy = vi.spyOn(tripsApi, 'deleteTrip').mockResolvedValue()

    const router = createTestRouter('/trips/5/edit')
    await router.isReady()

    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
      props: { tripId: 5 },
    })

    await flushPromises()

    // Click delete trigger
    const deleteTrigger = wrapper.get('[data-testid="delete-trip-trigger"]')
    await deleteTrigger.trigger('click')
    await flushPromises()

    // Modal should be open
    const modal = wrapper.getComponent({ name: 'ConfirmModal' })
    expect(modal.props('isOpen')).toBe(true)

    // Confirm deletion
    const confirmBtn = document.body.querySelector<HTMLButtonElement>(
      '[data-testid="modal-confirm-button"]',
    )
    expect(confirmBtn).not.toBeNull()
    confirmBtn?.click()
    await flushPromises()

    expect(deleteSpy).toHaveBeenCalledWith(5)
    expect(router.currentRoute.value.path).toBe('/me')
  })

  it('does not delete a trip when deletion is cancelled', async () => {
    const mockTrip: tripsApi.Trip = {
      id: 5,
      name: 'Podróż',
      start_date: '2026-09-01',
      end_date: null,
      description: null,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }
    vi.spyOn(tripsApi, 'getTrip').mockResolvedValue(mockTrip)
    const deleteSpy = vi.spyOn(tripsApi, 'deleteTrip').mockResolvedValue()
    const router = createTestRouter('/trips/5/edit')
    await router.isReady()
    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
      props: { tripId: 5 },
    })
    await flushPromises()

    await wrapper.get('[data-testid="delete-trip-trigger"]').trigger('click')
    document.body.querySelector<HTMLButtonElement>('[data-testid="modal-cancel-button"]')?.click()
    await flushPromises()

    expect(deleteSpy).not.toHaveBeenCalled()
    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
    expect(router.currentRoute.value.path).toBe('/trips/5/edit')
  })

  it('shows an error and stays on the edit page when deletion fails', async () => {
    const mockTrip: tripsApi.Trip = {
      id: 5,
      name: 'Podróż',
      start_date: '2026-09-01',
      end_date: null,
      description: null,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    }
    vi.spyOn(tripsApi, 'getTrip').mockResolvedValue(mockTrip)
    vi.spyOn(tripsApi, 'deleteTrip').mockRejectedValue(new Error('Brak połączenia'))
    const router = createTestRouter('/trips/5/edit')
    await router.isReady()
    const wrapper = mount(TripFormView, {
      global: { plugins: [router] },
      props: { tripId: 5 },
    })
    await flushPromises()

    await wrapper.get('[data-testid="delete-trip-trigger"]').trigger('click')
    document.body.querySelector<HTMLButtonElement>('[data-testid="modal-confirm-button"]')?.click()
    await flushPromises()

    expect(wrapper.get('[data-testid="form-error-banner"]').text()).toContain('Brak połączenia')
    expect(router.currentRoute.value.path).toBe('/trips/5/edit')
    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
  })
})
