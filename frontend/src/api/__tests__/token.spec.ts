import { describe, it, expect, beforeEach } from 'vitest'
import { getAuthToken, setAuthToken, clearAuthToken } from '../token'

describe('token storage helpers', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('zapisuje i odczytuje token z localStorage', () => {
    expect(getAuthToken()).toBeNull()
    setAuthToken('test-jwt-token')
    expect(getAuthToken()).toBe('test-jwt-token')
  })

  it('usuwa token z localStorage', () => {
    setAuthToken('token-to-remove')
    clearAuthToken()
    expect(getAuthToken()).toBeNull()
  })
})
