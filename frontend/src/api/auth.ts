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
