
package pl.dziennikpodrozy.mobile.data.session

class SessionManager(
    private val secureTokenStorage: SecureTokenStorage
) : SessionStore {

    override fun saveSession(token: String) {
        secureTokenStorage.saveToken(token)
    }

    override fun getToken(): String? {
        return secureTokenStorage.getToken()
    }

    override fun hasActiveSession(): Boolean {
        return !getToken().isNullOrBlank()
    }

    override fun clearSession() {
        secureTokenStorage.clearToken()
    }
}
