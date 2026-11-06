import { describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { searchPlaces } from '@/api/geocoding'

describe('searchPlaces', () => {
  it('queries the API geocoder and returns its result list', async () => {
    const results = [{ name: 'Wien', latitude: 48.2082, longitude: 16.3725, country_code: 'AT' }]
    const get = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { data: results } })

    await expect(searchPlaces('Wien')).resolves.toEqual(results)
    expect(get).toHaveBeenCalledWith('/geocode', { params: { q: 'Wien' } })

    get.mockRestore()
  })
})
