import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import * as tripsApi from '@/api/trips'
import DashboardView from '@/views/DashboardView.vue'

function createTestRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard', name: 'dashboard', component: DashboardView },
      { path: '/trips/new', name: 'trip-create', component: { template: '<div />' } },
      { path: '/map', name: 'user-map', component: { template: '<div />' } },
      { path: '/trips/:id', name: 'trip-details', component: { template: '<div />' } },
      { path: '/trips/:id/edit', name: 'trip-edit', component: { template: '<div />' } },
    ],
  })
}

function createPage(currentPage: number): tripsApi.TripsPage {
  return {
    data: [
      {
        id: currentPage,
        name: `Wycieczka ${currentPage}`,
        start_date: `${new Date().getFullYear()}-08-01`,
        end_date: `${new Date().getFullYear()}-08-05`,
        description: null,
        created_at: '',
        updated_at: '',
      },
    ],
    meta: { current_page: currentPage, last_page: 2, per_page: 5, total: 6 },
  }
}

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
})

describe('DashboardView pagination', () => {
  it('appends the next page of trips when requested', async () => {
    const getTripsPage = vi
      .spyOn(tripsApi, 'getTripsPage')
      .mockImplementation(async ({ page: requestedPage = 1 } = {}) => createPage(requestedPage))
    const router = createTestRouter()
    await router.push('/dashboard')
    const wrapper = mount(DashboardView, { global: { plugins: [router] } })
    await flushPromises()

    expect(getTripsPage).toHaveBeenCalledWith({
      page: 1,
      search: undefined,
      year: new Date().getFullYear(),
      status: 'all',
    })
    expect(wrapper.text()).toContain('Wyświetlono 1 z 6 wycieczek')
    expect(wrapper.text()).toContain('Wycieczka 1')

    await wrapper.get('.trip-pagination button').trigger('click')
    await flushPromises()

    expect(getTripsPage).toHaveBeenLastCalledWith({
      page: 2,
      search: undefined,
      year: new Date().getFullYear(),
      status: 'all',
    })
    expect(wrapper.text()).toContain('Wycieczka 1')
    expect(wrapper.text()).toContain('Wycieczka 2')
    expect(wrapper.text()).toContain('Wyświetlono 2 z 6 wycieczek')
  })

  it('sends search and filter values to the API instead of filtering one page locally', async () => {
    vi.useFakeTimers()
    const getTripsPage = vi.spyOn(tripsApi, 'getTripsPage').mockResolvedValue(createPage(1))
    const router = createTestRouter()
    await router.push('/dashboard')
    const wrapper = mount(DashboardView, { global: { plugins: [router] } })
    await flushPromises()

    await wrapper.get('[data-testid="trip-search"]').setValue('Tatry')
    await wrapper.get('[data-testid="trip-year-filter"]').setValue('2025')
    await wrapper.get('[data-testid="trip-status-filter"]').setValue('past')
    vi.advanceTimersByTime(250)
    await flushPromises()

    expect(getTripsPage).toHaveBeenLastCalledWith({
      page: 1,
      search: 'Tatry',
      year: 2025,
      status: 'past',
    })
  })
})
