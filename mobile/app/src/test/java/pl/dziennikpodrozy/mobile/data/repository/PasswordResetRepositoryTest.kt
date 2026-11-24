package pl.dziennikpodrozy.mobile.data.repository

import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.ContentType
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import io.ktor.http.content.TextContent
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.remote.dto.ResetPasswordRequestDto
import pl.dziennikpodrozy.mobile.data.session.SessionStore

class PasswordResetRepositoryTest {

    private val sessionStore = object : SessionStore {
        override val isAuthenticated = kotlinx.coroutines.flow.MutableStateFlow(false)
        override fun saveSession(token: String) = Unit
        override fun getToken(): String? = null
        override fun hasActiveSession(): Boolean = false
        override fun clearSession() = Unit
    }

    @Test
    fun `request reset link posts email and returns server message`() = runTest {
        var capturedPath = ""
        var capturedBody = ""
        val client = HttpClient(MockEngine { request ->
            capturedPath = request.url.encodedPath
            capturedBody = (request.body as? TextContent)?.text.orEmpty()
            respond(
                content = """{"message":"Jeśli konto istnieje, otrzymasz link."}""",
                status = HttpStatusCode.OK,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        try {
            val response = repository(client).sendPasswordResetLink("anna@example.com")

            assertEquals("/api/v1/forgot-password", capturedPath)
            assertTrue(capturedBody.contains(""""email":"anna@example.com""""))
            assertEquals("Jeśli konto istnieje, otrzymasz link.", response.message)
        } finally {
            client.close()
        }
    }

    @Test
    fun `reset password posts all fields and returns server message`() = runTest {
        var capturedPath = ""
        var capturedBody = ""
        val client = HttpClient(MockEngine { request ->
            capturedPath = request.url.encodedPath
            capturedBody = (request.body as? TextContent)?.text.orEmpty()
            respond(
                content = """{"message":"Hasło zostało zmienione."}""",
                status = HttpStatusCode.OK,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        try {
            val response = repository(client).resetPassword(
                ResetPasswordRequestDto(
                    email = "anna@example.com",
                    token = "reset-token",
                    password = "new-password",
                    password_confirmation = "new-password"
                )
            )

            assertEquals("/api/v1/reset-password", capturedPath)
            assertTrue(capturedBody.contains(""""token":"reset-token""""))
            assertTrue(capturedBody.contains(""""password_confirmation":"new-password""""))
            assertEquals("Hasło zostało zmienione.", response.message)
        } finally {
            client.close()
        }
    }

    @Test
    fun `reset password validation response maps to field errors`() = runTest {
        val client = HttpClient(MockEngine {
            respond(
                content = """{"message":"The given data was invalid.","errors":{"email":["Token jest nieprawidłowy."]}}""",
                status = HttpStatusCode.UnprocessableEntity,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        try {
            try {
                repository(client).resetPassword(
                    ResetPasswordRequestDto(
                        email = "anna@example.com",
                        token = "invalid",
                        password = "new-password",
                        password_confirmation = "new-password"
                    )
                )
                throw AssertionError("Expected validation exception")
            } catch (exception: ApiException.Validation) {
                assertEquals(422, exception.statusCode)
                assertEquals("Token jest nieprawidłowy.", exception.fieldErrors["email"]?.first())
            }
        } finally {
            client.close()
        }
    }

    private fun repository(client: HttpClient) = AuthRepository(
        client = client,
        apiConfig = ApiConfig("https://example.test/api/v1"),
        sessionManager = sessionStore
    )
}
