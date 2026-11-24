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
class RegisterViewModelTest {

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
    fun `register with empty fields produces local validation errors`() = runTest {
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

        val viewModel = RegisterViewModel(repository)
        viewModel.register()

        assertFalse(backendCalled)
        assertEquals("Nazwa użytkownika jest wymagana.", viewModel.uiState.value.fieldErrors["name"]?.first())
        assertEquals("Adres e-mail jest wymagany.", viewModel.uiState.value.fieldErrors["email"]?.first())
        assertEquals("Hasło jest wymagane.", viewModel.uiState.value.fieldErrors["password"]?.first())
        assertEquals("Powtórzenie hasła jest wymagane.", viewModel.uiState.value.fieldErrors["password_confirmation"]?.first())
    }

    @Test
    fun `passwords mismatch produces password confirmation error`() = runTest {
        val sessionStore = FakeSessionStore()
        val client = HttpClient(MockEngine { respond("") })
        val repository = AuthRepository(
            client = client,
            apiConfig = ApiConfig("https://example.test/api/v1"),
            sessionManager = sessionStore
        )

        val viewModel = RegisterViewModel(repository)
        viewModel.onNameChange("Jan")
        viewModel.onEmailChange("jan@example.com")
        viewModel.onPasswordChange("haslo1234")
        viewModel.onPasswordConfirmationChange("inne_haslo1234")
        viewModel.register()

        assertEquals(
            "Hasła nie są identyczne.",
            viewModel.uiState.value.fieldErrors["password_confirmation"]?.first()
        )
    }

    @Test
    fun `short password produces password length error`() = runTest {
        val sessionStore = FakeSessionStore()
        val client = HttpClient(MockEngine { respond("") })
        val repository = AuthRepository(
            client = client,
            apiConfig = ApiConfig("https://example.test/api/v1"),
            sessionManager = sessionStore
        )

        val viewModel = RegisterViewModel(repository)
        viewModel.onNameChange("Jan")
        viewModel.onEmailChange("jan@example.com")
        viewModel.onPasswordChange("123")
        viewModel.onPasswordConfirmationChange("123")
        viewModel.register()

        assertEquals(
            "Hasło musi mieć co najmniej 8 znaków.",
            viewModel.uiState.value.fieldErrors["password"]?.first()
        )
    }

    @Test
    fun `successful register sets isSuccess and successMessage`() = runTest {
        val sessionStore = FakeSessionStore()
        val responseBody = """
            {
                "data": {
                    "id": 1,
                    "name": "Jan Kowalski",
                    "email": "jan@example.com",
                    "profile_visibility": "public"
                }
            }
        """.trimIndent()

        val client = HttpClient(
            MockEngine {
                respond(
                    content = responseBody,
                    status = HttpStatusCode.Created,
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

        val viewModel = RegisterViewModel(
            authRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.onNameChange("Jan Kowalski")
        viewModel.onEmailChange("jan@example.com")
        viewModel.onPasswordChange("password123")
        viewModel.onPasswordConfirmationChange("password123")
        viewModel.register().join()

        assertTrue(viewModel.uiState.value.isSuccess)
        assertNotNull(viewModel.uiState.value.successMessage)
        assertFalse(viewModel.uiState.value.isLoading)
    }

    @Test
    fun `register backend 422 maps validation errors`() = runTest {
        val sessionStore = FakeSessionStore()
        val responseBody = """
            {
                "message": "The given data was invalid.",
                "errors": {
                    "email": ["Podany adres e-mail jest już zajęty."]
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

        val viewModel = RegisterViewModel(
            authRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.onNameChange("Jan Kowalski")
        viewModel.onEmailChange("zajety@example.com")
        viewModel.onPasswordChange("password123")
        viewModel.onPasswordConfirmationChange("password123")
        viewModel.register().join()

        assertFalse(viewModel.uiState.value.isSuccess)
        assertEquals(
            "Podany adres e-mail jest już zajęty.",
            viewModel.uiState.value.fieldErrors["email"]?.first()
        )
    }
}
