import { afterEach, describe, expect, it } from 'vitest'
import type { AxiosResponse, InternalAxiosRequestConfig } from 'axios'
import { apiClient } from '@/api/client'
import { getTrips, getTripsPage, type TripsPage } from '@/api/trips'

const originalAdapter = apiClient.defaults.adapter

function createPage(currentPage: number, lastPage: number, total: number): TripsPage {
  return {
    data: Array.from({ length: currentPage === 1 ? 5 : 1 }, (_, index) => ({
      id: (currentPage - 1) * 5 + index + 1,
      name: `Trip ${(currentPage - 1) * 5 + index + 1}`,
      start_date: null,
      end_date: null,
      description: null,
      created_at: '',
      updated_at: '',
    })),
    meta: { current_page: currentPage, last_page: lastPage, per_page: 5, total },
  }
}

afterEach(() => {
  apiClient.defaults.adapter = originalAdapter
})

describe('trips API pagination', () => {
  it('requests a maximum of five trips and fetches subsequent pages as needed', async () => {
    const requestedPages: number[] = []
    const requestedPageSizes: number[] = []
    apiClient.defaults.adapter = async (config: InternalAxiosRequestConfig) => {
      const page = Number(config.params?.page ?? 1)
      requestedPages.push(page)
      requestedPageSizes.push(Number(config.params?.per_page))
      return {
        data: createPage(page, 2, 6),
        status: 200,
        statusText: 'OK',
        headers: {},
        config,
      } as AxiosResponse
    }

    const trips = await getTrips()

    expect(trips).toHaveLength(6)
    expect(requestedPages).toEqual([1, 2])
    expect(requestedPageSizes).toEqual([5, 5])
  })

  it('passes dashboard filters while keeping page size capped at five', async () => {
    let requestParams: Record<string, unknown> | undefined
    apiClient.defaults.adapter = async (config: InternalAxiosRequestConfig) => {
      requestParams = config.params
      return {
        data: createPage(2, 2, 6),
        status: 200,
        statusText: 'OK',
        headers: {},
        config,
      } as AxiosResponse
    }

    await getTripsPage({ page: 2, search: 'Tatry', year: 2025, status: 'past' })

    expect(requestParams).toEqual({
      page: 2,
      search: 'Tatry',
      year: 2025,
      status: 'past',
      per_page: 5,
    })
  })
})
