package pl.dziennikpodrozy.mobile

import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.http.ContentType
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository
import pl.dziennikpodrozy.mobile.data.session.SessionStore
import pl.dziennikpodrozy.mobile.ui.home.HomeViewModel

@OptIn(ExperimentalCoroutinesApi::class)
class HomeViewModelTest {

    private class FakeSessionStore : SessionStore {
        private val _isAuthenticated = MutableStateFlow(true)
        override val isAuthenticated: StateFlow<Boolean> = _isAuthenticated.asStateFlow()
        var savedToken: String? = "mock-token"

        override fun saveSession(token: String) {
            this.savedToken = token
            _isAuthenticated.value = true
        }

        override fun getToken(): String? = savedToken
        override fun hasActiveSession(): Boolean = !savedToken.isNullOrBlank()
        override fun clearSession() {
            savedToken = null
            _isAuthenticated.value = false
        }
    }

    @Test
    fun `initial state exposes correct apiBaseUrl from config`() {
        val customConfig = ApiConfig(baseUrl = "https://custom.api.pl/api/v1")
        val viewModel = HomeViewModel(apiConfig = customConfig)

        assertEquals("https://custom.api.pl/api/v1", viewModel.uiState.value.apiBaseUrl)
        assertTrue(viewModel.uiState.value.isReady)
    }

    @Test
    fun `logout clears session and updates state`() = runTest {
        Dispatchers.setMain(UnconfinedTestDispatcher(testScheduler))
        try {
            val sessionStore = FakeSessionStore()
            val client = HttpClient(
                MockEngine {
                    respond(
                        content = """{"message":"Successfully logged out"}""",
                        status = HttpStatusCode.OK,
                        headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
                    )
                }
            )
            val repository = AuthRepository(
                client = client,
                apiConfig = ApiConfig("https://example.test/api/v1"),
                sessionManager = sessionStore
            )

            val testDispatcher = UnconfinedTestDispatcher(testScheduler)
            val viewModel = HomeViewModel(
                authRepository = repository,
                dispatcher = testDispatcher
            )
            viewModel.logout().join()

            assertTrue(viewModel.uiState.value.isLoggedOut)
            assertFalse(viewModel.uiState.value.isLoggingOut)
            assertEquals(null, viewModel.uiState.value.logoutError)
            assertEquals(null, sessionStore.savedToken)
        } finally {
            Dispatchers.resetMain()
        }
    }
}