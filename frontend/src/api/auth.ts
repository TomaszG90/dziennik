import { apiClient } from './client'
import type { ProfilePrivacySettings, UserProfile } from './types'

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  profile_visibility: 'public' | 'private'
}

export interface UpdateProfilePayload {
  name: string
  email: string
  first_name: string
  last_name: string
  city: string
  birth_date: string | null
  avatar_path: string
  profile_visibility: 'public' | 'private'
  privacy_settings: ProfilePrivacySettings
}

export async function registerUser(payload: RegisterPayload): Promise<void> {
  await apiClient.post('/auth/register', payload)
}

export async function updateMyProfile(payload: UpdateProfilePayload): Promise<UserProfile> {
  const { data } = await apiClient.patch<{ data: UserProfile }>('/me', payload)
  return data.data
}
export interface PasswordResetRequest {
  email: string
}

export interface PasswordResetPayload extends PasswordResetRequest {
  token: string
  password: string
  password_confirmation: string
}

export async function requestPasswordReset(payload: PasswordResetRequest): Promise<void> {
  await apiClient.post('/forgot-password', payload)
}

export async function resetPassword(payload: PasswordResetPayload): Promise<void> {
  await apiClient.post('/reset-password', payload)
}
