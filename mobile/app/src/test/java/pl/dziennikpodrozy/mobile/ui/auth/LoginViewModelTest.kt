package pl.dziennikpodrozy.mobile.ui.auth

import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.ContentType
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.Json
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository
import pl.dziennikpodrozy.mobile.data.session.SessionStore

@OptIn(ExperimentalCoroutinesApi::class)
class LoginViewModelTest {

    private class FakeSessionStore : SessionStore {
        private val _isAuthenticated = MutableStateFlow(false)
        override val isAuthenticated: StateFlow<Boolean> = _isAuthenticated.asStateFlow()
        var savedToken: String? = null

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

    @Before
    fun setUp() {
        Dispatchers.setMain(StandardTestDispatcher())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun `login with empty fields validates locally without calling backend`() = runTest {
        val sessionStore = FakeSessionStore()
        var backendCalled = false

        val client = HttpClient(
            MockEngine {
                backendCalled = true
                respond("")
            }
        )

        val repository = AuthRepository(
            client = client,
            apiConfig = ApiConfig("https://example.test/api/v1"),
            sessionManager = sessionStore
        )

        val viewModel = LoginViewModel(repository)
        viewModel.login()

        assertFalse(backendCalled)
        assertEquals(
            "Adres e-mail jest wymagany.",
            viewModel.uiState.value.fieldErrors["email"]?.first()
        )
        assertEquals(
            "Hasło jest wymagane.",
            viewModel.uiState.value.fieldErrors["password"]?.first()
        )
        assertFalse(viewModel.uiState.value.isLoading)
        assertFalse(viewModel.uiState.value.isSuccess)
    }

    @Test
    fun `login with invalid email format produces local error`() = runTest {
        val sessionStore = FakeSessionStore()
        val client = HttpClient(MockEngine { respond("") })
        val repository = AuthRepository(
            client = client,
            apiConfig = ApiConfig("https://example.test/api/v1"),
            sessionManager = sessionStore
        )

        val viewModel = LoginViewModel(repository)
        viewModel.onEmailChange("invalid-email")
        viewModel.onPasswordChange("secret123")
        viewModel.login()

        assertEquals(
            "Wprowadź prawidłowy adres e-mail.",
            viewModel.uiState.value.fieldErrors["email"]?.first()
        )
    }

    @Test
    fun `successful login updates state to success`() = runTest {
        val sessionStore = FakeSessionStore()
        val responseBody = """
            {
                "data": {
                    "user": {
                        "id": 1,
                        "name": "Jan Kowalski",
                        "email": "jan@example.com",
                        "profile_visibility": "public"
                    },
                    "token": "sanctum-token-xyz"
                }
            }
        """.trimIndent()

        val client = HttpClient(
            MockEngine {
                respond(
                    content = responseBody,
                    status = HttpStatusCode.OK,
                    headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
                )
            }
        ) {
            install(ContentNegotiation) {
                json(Json { ignoreUnknownKeys = true })
            }
        }

        val repository = AuthRepository(
            client = client,
            apiConfig = ApiConfig("https://example.test/api/v1"),
            sessionManager = sessionStore
        )

        val viewModel = LoginViewModel(
            authRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.onEmailChange("jan@example.com")
        viewModel.onPasswordChange("secret123")
        viewModel.login().join()

        assertTrue(viewModel.uiState.value.isSuccess)
        assertFalse(viewModel.uiState.value.isLoading)
        assertEquals(null, viewModel.uiState.value.errorMessage)
        assertEquals("sanctum-token-xyz", sessionStore.savedToken)
    }

    @Test
    fun `unauthorized login response sets generic error message`() = runTest {
        val sessionStore = FakeSessionStore()
        val client = HttpClient(
            MockEngine {
                respond(
                    content = """{"message":"Invalid credentials."}""",
                    status = HttpStatusCode.Unauthorized,
                    headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
                )
            }
        ) {
            install(ContentNegotiation) {
                json(Json { ignoreUnknownKeys = true })
            }
        }

        val repository = AuthRepository(
            client = client,
            apiConfig = ApiConfig("https://example.test/api/v1"),
            sessionManager = sessionStore
        )

        val viewModel = LoginViewModel(
            authRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.onEmailChange("jan@example.com")
        viewModel.onPasswordChange("wrong-password")
        viewModel.login().join()

        assertFalse(viewModel.uiState.value.isSuccess)
        assertFalse(viewModel.uiState.value.isLoading)
        assertEquals(
            "Nieprawidłowy adres e-mail lub hasło.",
            viewModel.uiState.value.errorMessage
        )
    }

    @Test
    fun `validation error 422 maps field errors to state`() = runTest {
        val sessionStore = FakeSessionStore()
        val responseBody = """
            {
                "message": "The given data was invalid.",
                "errors": {
                    "email": ["Wybrany e-mail jest nieprawidłowy."]
                }
            }
        """.trimIndent()

        val client = HttpClient(
            MockEngine {
                respond(
                    content = responseBody,
                    status = HttpStatusCode.UnprocessableEntity,
                    headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
                )
            }
        ) {
            install(ContentNegotiation) {
                json(Json { ignoreUnknownKeys = true })
            }
        }

        val repository = AuthRepository(
            client = client,
            apiConfig = ApiConfig("https://example.test/api/v1"),
            sessionManager = sessionStore
        )

        val viewModel = LoginViewModel(
            authRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.onEmailChange("test@example.com")
        viewModel.onPasswordChange("secret123")
        viewModel.login().join()

        assertNotNull(viewModel.uiState.value.fieldErrors["email"])
        assertEquals(
            "Wybrany e-mail jest nieprawidłowy.",
            viewModel.uiState.value.fieldErrors["email"]?.first()
        )
    }
}
