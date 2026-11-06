import { apiClient } from './client'

export interface GeocodeResult {
  name: string | null
  latitude: number | null
  longitude: number | null
  street: string | null
  housenumber: string | null
  postcode: string | null
  city: string | null
  district: string | null
  county: string | null
  state: string | null
  country: string | null
  country_code: string | null
}

export async function searchPlaces(query: string): Promise<GeocodeResult[]> {
  const response = await apiClient.get<{ data: GeocodeResult[] }>('/geocode', {
    params: { q: query },
  })
  return response.data.data
}
