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

export async function getTrips(): Promise<Trip[]> {
  const response = await apiClient.get<{ data: Trip[] }>('/trips')
  return response.data.data
}
