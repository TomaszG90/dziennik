import type { TripPlace } from '@/api/trips'

const EARTH_RADIUS_KM = 6371

export function calculateDistanceKm(from: TripPlace, to: TripPlace): number | null {
  if (
    from.latitude === null ||
    from.longitude === null ||
    to.latitude === null ||
    to.longitude === null
  ) {
    return null
  }

  if (
    [from.latitude, from.longitude, to.latitude, to.longitude].some(
      (coordinate) => typeof coordinate === 'string' && coordinate.trim() === '',
    )
  ) {
    return null
  }

  const latitude1 = Number(from.latitude)
  const longitude1 = Number(from.longitude)
  const latitude2 = Number(to.latitude)
  const longitude2 = Number(to.longitude)

  if (
    !Number.isFinite(latitude1) ||
    !Number.isFinite(longitude1) ||
    !Number.isFinite(latitude2) ||
    !Number.isFinite(longitude2) ||
    Math.abs(latitude1) > 90 ||
    Math.abs(latitude2) > 90 ||
    Math.abs(longitude1) > 180 ||
    Math.abs(longitude2) > 180
  ) {
    return null
  }

  const radians = (degrees: number) => (degrees * Math.PI) / 180
  const latitudeDelta = radians(latitude2 - latitude1)
  const longitudeDelta = radians(longitude2 - longitude1)
  const haversine =
    Math.sin(latitudeDelta / 2) ** 2 +
    Math.cos(radians(latitude1)) * Math.cos(radians(latitude2)) * Math.sin(longitudeDelta / 2) ** 2
  const normalizedHaversine = Math.min(1, haversine)
  const centralAngle =
    2 * Math.atan2(Math.sqrt(normalizedHaversine), Math.sqrt(1 - normalizedHaversine))

  return Math.round(EARTH_RADIUS_KM * centralAngle * 100) / 100
}
