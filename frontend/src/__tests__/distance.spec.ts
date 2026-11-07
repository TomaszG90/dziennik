import { describe, expect, it } from 'vitest'
import { calculateDistanceKm } from '@/utils/distance'
import type { TripPlace } from '@/api/trips'

function place(
  latitude: number | null,
  longitude: number | null,
  country_code: string | null,
): TripPlace {
  return {
    id: 1,
    trip_id: 1,
    name: 'Test',
    latitude,
    longitude,
    country_code,
    position: 1,
  }
}

describe('calculateDistanceKm', () => {
  it('calculates a straight-line distance between locations in different countries', () => {
    const krakow = place(50.0647, 19.945, 'PL')
    const vienna = place(48.2082, 16.3738, 'AT')

    expect(calculateDistanceKm(krakow, vienna)).toBeCloseTo(331.9, 0)
    expect(calculateDistanceKm(vienna, krakow)).toBe(calculateDistanceKm(krakow, vienna))
  })

  it('returns null when coordinates are missing or outside valid ranges', () => {
    expect(calculateDistanceKm(place(null, 19, 'PL'), place(48, 16, 'AT'))).toBeNull()
    expect(calculateDistanceKm(place(91, 19, 'PL'), place(48, 16, 'AT'))).toBeNull()
  })
})
