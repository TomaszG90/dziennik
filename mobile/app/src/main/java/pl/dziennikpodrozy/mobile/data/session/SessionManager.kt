package pl.dziennikpodrozy.mobile.data.session

import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

class SessionManager(
    private val secureTokenStorage: SecureTokenStorage
) : SessionStore {

    private val _isAuthenticated = MutableStateFlow(hasActiveSession())
    override val isAuthenticated: StateFlow<Boolean> = _isAuthenticated.asStateFlow()

    override fun saveSession(token: String) {
        secureTokenStorage.saveToken(token)
        _isAuthenticated.value = true
    }

    override fun getToken(): String? {
        return secureTokenStorage.getToken()
    }

    override fun hasActiveSession(): Boolean {
        return !getToken().isNullOrBlank()
    }

    override fun clearSession() {
        secureTokenStorage.clearToken()
        _isAuthenticated.value = false
    }
}