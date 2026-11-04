import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TripFormView from '../TripFormView.vue'
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
    const confirmBtn = wrapper.get('[data-testid="modal-confirm-button"]')
    await confirmBtn.trigger('click')
    await flushPromises()

    expect(deleteSpy).toHaveBeenCalledWith(5)
    expect(router.currentRoute.value.path).toBe('/me')
  })
})
