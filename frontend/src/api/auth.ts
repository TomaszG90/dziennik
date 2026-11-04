import { apiClient } from './client'

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  profile_visibility: 'public' | 'private'
}

export async function registerUser(payload: RegisterPayload): Promise<void> {
  await apiClient.post('/auth/register', payload)
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
