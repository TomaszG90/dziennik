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
  inviter?: TripFriendInviter | null
}

export interface TripPayload {
  name: string
  start_date: string
  end_date?: string | null
  description?: string | null
}

export async function getTrips(): Promise<Trip[]> {
  const response = await apiClient.get<{ data: Trip[] }>('/trips')
  return response.data.data
}

export async function getTrip(id: number | string): Promise<Trip> {
  const response = await apiClient.get<{ data: Trip }>(`/trips/${id}`)
  return response.data.data
}

export async function createTrip(payload: TripPayload): Promise<Trip> {
  const response = await apiClient.post<{ data: Trip }>('/trips', payload)
  return response.data.data
}

export async function updateTrip(id: number | string, payload: Partial<TripPayload>): Promise<Trip> {
  const response = await apiClient.patch<{ data: Trip }>(`/trips/${id}`, payload)
  return response.data.data
}

export async function deleteTrip(id: number | string): Promise<void> {
  await apiClient.delete(`/trips/${id}`)
}
