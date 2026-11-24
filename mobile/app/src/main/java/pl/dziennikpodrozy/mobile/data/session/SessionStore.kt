package pl.dziennikpodrozy.mobile.data.session

import kotlinx.coroutines.flow.StateFlow

interface SessionStore {
    val isAuthenticated: StateFlow<Boolean>
    fun saveSession(token: String)
    fun getToken(): String?
    fun hasActiveSession(): Boolean
    fun clearSession()
}