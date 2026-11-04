export interface ApiValidationErrorPayload {
  message: string
  errors?: Record<string, string[]>
}

export interface ProfilePrivacySettings {
  name: boolean
  email: boolean
  firstName: boolean
  lastName: boolean
  city: boolean
  birthDate: boolean
  avatar: boolean
}

export interface UserProfile {
  id: number | string
  name: string
  email: string
  first_name: string | null
  last_name: string | null
  city: string | null
  birth_date: string | null
  avatar_path: string | null
  profile_visibility: 'public' | 'private'
  privacy_settings: ProfilePrivacySettings
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status?: number,
    public readonly validationErrors?: Record<string, string[]>,
  ) {
    super(message)
    this.name = 'ApiError'
  }

  get isValidationError(): boolean {
    return this.status === 422
  }

  get isUnauthorized(): boolean {
    return this.status === 401
  }
}
