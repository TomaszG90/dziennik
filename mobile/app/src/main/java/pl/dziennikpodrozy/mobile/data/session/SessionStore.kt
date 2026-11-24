package pl.dziennikpodrozy.mobile.data.session

interface SessionStore {
    fun saveSession(token: String)
    fun getToken(): String?
    fun hasActiveSession(): Boolean
    fun clearSession()
}