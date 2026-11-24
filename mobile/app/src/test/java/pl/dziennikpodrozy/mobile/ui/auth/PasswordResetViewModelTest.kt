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
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.Json
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository
import pl.dziennikpodrozy.mobile.data.session.SessionStore

@OptIn(ExperimentalCoroutinesApi::class)
class PasswordResetViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()

    private val sessionStore = object : SessionStore {
        override val isAuthenticated = MutableStateFlow(false)
        override fun saveSession(token: String) = Unit
        override fun getToken(): String? = null
        override fun hasActiveSession(): Boolean = false
        override fun clearSession() = Unit
    }

    @Before
    fun setUp() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun `reset form validates missing token and mismatched passwords locally`() = runTest {
        var backendCalled = false
        val client = client {
            backendCalled = true
            respond("")
        }
        val viewModel = PasswordResetViewModel(
            authRepository = repository(client),
            initialEmail = "anna@example.com",
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.onPasswordChange("new-password")
        viewModel.onPasswordConfirmationChange("different-password")

        viewModel.resetPassword()

        assertFalse(backendCalled)
        assertEquals(
            "Token z wiadomości e-mail jest wymagany.",
            viewModel.uiState.value.fieldErrors["token"]?.first()
        )
        assertEquals(
            "Hasła nie są identyczne.",
            viewModel.uiState.value.fieldErrors["password_confirmation"]?.first()
        )
        client.close()
    }

    @Test
    fun `reset link success displays message returned by api`() = runTest {
        val client = client {
            respond(
                content = """{"message":"Jeśli konto istnieje, otrzymasz link."}""",
                status = HttpStatusCode.OK,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }
        val viewModel = PasswordResetViewModel(
            authRepository = repository(client),
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.onEmailChange("anna@example.com")

        viewModel.requestResetLink().join()

        assertEquals("Jeśli konto istnieje, otrzymasz link.", viewModel.uiState.value.successMessage)
        assertFalse(viewModel.uiState.value.isLoading)
        client.close()
    }

    @Test
    fun `invalid token response is mapped to email field`() = runTest {
        val client = client {
            respond(
                content = """{"message":"Invalid token.","errors":{"email":["Token wygasł."]}}""",
                status = HttpStatusCode.UnprocessableEntity,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }
        val viewModel = PasswordResetViewModel(
            authRepository = repository(client),
            initialEmail = "anna@example.com",
            initialToken = "expired",
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.onPasswordChange("new-password")
        viewModel.onPasswordConfirmationChange("new-password")

        viewModel.resetPassword().join()

        assertEquals("Token wygasł.", viewModel.uiState.value.fieldErrors["email"]?.first())
        assertTrue(viewModel.uiState.value.errorMessage != null)
        client.close()
    }

    private fun client(handler: io.ktor.client.engine.mock.MockRequestHandleScope.(io.ktor.client.request.HttpRequestData) -> io.ktor.client.request.HttpResponseData) =
        HttpClient(MockEngine(handler)) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

    private fun repository(client: HttpClient) = AuthRepository(
        client = client,
        apiConfig = ApiConfig("https://example.test/api/v1"),
        sessionManager = sessionStore
    )
}
