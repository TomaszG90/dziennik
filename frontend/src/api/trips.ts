import { apiClient } from './client'

export interface TripFriendInviter {
  id?: number
  name: string
  avatar?: string | null
}

export interface Trip {
  id: number
  name: string
  start_date: string | null
  end_date: string | null
  description: string | null
  created_at: string
  updated_at: string
  is_invitation?: boolean
  is_guest?: boolean
  inviter?: TripFriendInviter | null
}

export interface TripsPage {
  data: Trip[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface TripsQuery {
  page?: number
  search?: string
  year?: number
  status?: 'all' | 'planned' | 'past'
}

export interface TripPayload {
  name: string
  start_date: string
  end_date?: string | null
  description?: string | null
}

export interface TripPlace {
  id: number
  trip_id: number
  name: string
  latitude: string | number | null
  longitude: string | number | null
  position: number
  country_code: string | null
}

export interface TripPlacePayload {
  name: string
  latitude: number
  longitude: number
  country_code?: string | null
}

export interface TripLeg {
  id: number
  trip_id: number
  from_place_id: number
  to_place_id: number
  transport: string
  distance: string | number | null
  geometry?: {
    type: 'LineString'
    coordinates: Array<[number, number]>
  } | null
  position: number
}

export interface UserMapData {
  trips: Array<Pick<Trip, 'id' | 'name' | 'start_date' | 'end_date'>>
  places: Array<TripPlace & { trip_name: string }>
  legs: Array<TripLeg & { trip_name: string }>
}

export async function getUserMap(): Promise<UserMapData> {
  const response = await apiClient.get<{ data: UserMapData }>('/map')
  return response.data.data
}

export type TripTransport = 'walking' | 'car' | 'plane' | 'train' | 'other'

export interface TripLegPayload {
  from_place_id: number
  to_place_id: number
  transport: TripTransport
  distance?: number | null
}

export type UpdateTripLegPayload = Partial<TripLegPayload>

export async function getTripsPage(query: TripsQuery = {}): Promise<TripsPage> {
  const response = await apiClient.get<TripsPage>('/trips', {
    params: { ...query, per_page: 5 },
  })
  return response.data
}

export async function getTripYears(): Promise<number[]> {
  const response = await apiClient.get<{ data: number[] }>('/trips/years')
  return response.data.data
}

export async function getTrips(): Promise<Trip[]> {
  const trips: Trip[] = []
  let page = 1
  let lastPage = 1

  do {
    const result = await getTripsPage({ page })
    trips.push(...result.data)
    lastPage = result.meta.last_page
    page += 1
  } while (page <= lastPage)

  return trips
}

export async function getTrip(id: string | number): Promise<Trip> {
  const response = await apiClient.get<{ data: Trip }>(`/trips/${id}`)
  return response.data.data
}

export async function getTripPlaces(id: string | number): Promise<TripPlace[]> {
  const response = await apiClient.get<{ data: TripPlace[] }>(`/trips/${id}/places`)
  return response.data.data
}

export async function getTripLegs(id: string | number): Promise<TripLeg[]> {
  const response = await apiClient.get<{ data: TripLeg[] }>(`/trips/${id}/legs`)
  return response.data.data
}

export async function createTripPlace(
  tripId: string | number,
  payload: TripPlacePayload,
): Promise<TripPlace> {
  const response = await apiClient.post<{ data: TripPlace }>(`/trips/${tripId}/places`, payload)
  return response.data.data
}

export async function deleteTripPlace(tripId: string | number, placeId: number): Promise<void> {
  await apiClient.delete(`/trips/${tripId}/places/${placeId}`)
}

export async function reorderTripPlaces(
  tripId: string | number,
  placeIds: number[],
): Promise<TripPlace[]> {
  const response = await apiClient.patch<{ data: TripPlace[] }>(`/trips/${tripId}/places/reorder`, {
    place_ids: placeIds,
  })
  return response.data.data
}

export async function createTripLeg(
  tripId: string | number,
  payload: TripLegPayload,
): Promise<TripLeg> {
  const response = await apiClient.post<{ data: TripLeg }>(`/trips/${tripId}/legs`, payload)
  return response.data.data
}

export async function updateTripLeg(
  tripId: string | number,
  legId: number,
  payload: UpdateTripLegPayload,
): Promise<TripLeg> {
  const response = await apiClient.patch<{ data: TripLeg }>(
    `/trips/${tripId}/legs/${legId}`,
    payload,
  )
  return response.data.data
}

export async function deleteTripLeg(tripId: string | number, legId: number): Promise<void> {
  await apiClient.delete(`/trips/${tripId}/legs/${legId}`)
}

export async function createTrip(payload: TripPayload): Promise<Trip> {
  const response = await apiClient.post<{ data: Trip }>('/trips', payload)
  return response.data.data
}

export async function updateTrip(id: string | number, payload: TripPayload): Promise<Trip> {
  const response = await apiClient.put<{ data: Trip }>(`/trips/${id}`, payload)
  return response.data.data
}

export async function deleteTrip(id: string | number): Promise<void> {
  await apiClient.delete(`/trips/${id}`)
}

export async function copyTrip(id: string | number): Promise<Trip> {
  const response = await apiClient.post<{ data: Trip }>(`/trips/${id}/copy`)
  return response.data.data
}
