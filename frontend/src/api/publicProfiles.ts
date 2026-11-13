import { apiClient } from './client'
import type { Trip, UserMapData } from './trips'

export interface PublicProfile {
  id: number
  name?: string
  first_name?: string
  last_name?: string
  city?: string
  birth_date?: string | null
  avatar_path?: string
  created_at: string
}

export interface PublicProfilesPage {
  data: PublicProfile[]
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}

export interface PublicProfileStats {
  trips_count: number
  countries: string[]
  total_distance: number
  distance_by_transport: Record<string, number>
  places_count: number
}

export async function getPublicProfiles(page = 1, search?: string): Promise<PublicProfilesPage> {
  const response = await apiClient.get<PublicProfilesPage>('/public/users', {
    params: { page, search },
  })

  return response.data
}

export function getPublicAvatarUrl(filename: string): string {
  return new URL(`/avatars/${filename}`, apiClient.defaults.baseURL).toString()
}

export async function getPublicProfile(id: string | number): Promise<PublicProfile> {
  const response = await apiClient.get<{ data: PublicProfile }>(`/public/users/${id}`)
  return response.data.data
}

export async function getPublicProfileTrips(id: string | number): Promise<Trip[]> {
  const response = await apiClient.get<{ data: Trip[] }>(`/public/users/${id}/trips`)
  return response.data.data
}

export async function getPublicProfileMap(id: string | number): Promise<UserMapData> {
  const response = await apiClient.get<{ data: UserMapData }>(`/public/users/${id}/map`)
  return response.data.data
}

export async function getPublicProfileStats(id: string | number): Promise<PublicProfileStats> {
  const response = await apiClient.get<{ data: PublicProfileStats }>(`/public/users/${id}/stats`)
  return response.data.data
}
