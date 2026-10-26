export interface ApiValidationErrorPayload {
  message: string
  errors?: Record<string, string[]>
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
