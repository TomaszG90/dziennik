package pl.dziennikpodrozy.mobile.data.repository

import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.ContentType
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.session.SessionStore

class AuthRepositoryTest {

    private class FakeSessionStore : SessionStore {
        var savedToken: String? = null

        override fun saveSession(token: String) {
            savedToken = token
        }

        override fun getToken(): String? = savedToken

        override fun hasActiveSession(): Boolean =
            !savedToken.isNullOrBlank()

        override fun clearSession() {
            savedToken = null
        }
    }

    @Test
    fun `successful login saves token`() = runTest {
        val sessionStore = FakeSessionStore()

        val responseBody = """
            {
                "data": {
                    "user": {
                        "id": 1,
                        "name": "Test User",
                        "email": "test@example.com",
                        "first_name": "Test",
                        "last_name": "User",
                        "city": null,
                        "birth_date": null,
                        "avatar_path": null,
                        "profile_visibility": "public",
                        "privacy_settings": [],
                        "created_at": null,
                        "updated_at": null
                    },
                    "token": "test-sanctum-token"
                }
            }
        """.trimIndent()

        val client = HttpClient(
            MockEngine {
                respond(
                    content = responseBody,
                    status = HttpStatusCode.OK,
                    headers = headersOf(
                        HttpHeaders.ContentType,
                        ContentType.Application.Json.toString()
                    )
                )
            }
        ) {
            install(ContentNegotiation) {
                json(
                    Json {
                        ignoreUnknownKeys = true
                    }
                )
            }
        }

        try {
            val repository = AuthRepository(
                client = client,
                apiConfig = ApiConfig(
                    baseUrl = "https://example.test/api/v1"
                ),
                sessionManager = sessionStore
            )

            val result = repository.login(
                email = "test@example.com",
                password = "test-password"
            )

            assertEquals("test-sanctum-token", sessionStore.savedToken)
            assertEquals("test@example.com", result.data.user.email)
            assertNotNull(sessionStore.getToken())
        } finally {
            client.close()
        }
    }

    @Test
    fun `logout sends bearer token and clears session`() = runTest {
        val sessionStore = FakeSessionStore().apply {
            saveSession("test-sanctum-token")
        }

        var capturedAuthorization: String? = null

        val client = HttpClient(
            MockEngine { request ->
                capturedAuthorization =
                    request.headers[HttpHeaders.Authorization]

                respond(
                    content = """{"message":"Successfully logged out"}""",
                    status = HttpStatusCode.OK,
                    headers = headersOf(
                        HttpHeaders.ContentType,
                        ContentType.Application.Json.toString()
                    )
                )
            }
        )

        try {
            val repository = AuthRepository(
                client = client,
                apiConfig = ApiConfig(
                    baseUrl = "https://example.test/api/v1"
                ),
                sessionManager = sessionStore
            )

            repository.logout()

            assertEquals(
                "Bearer test-sanctum-token",
                capturedAuthorization
            )
            assertEquals(null, sessionStore.getToken())
        } finally {
            client.close()
        }
    }

    @Test
    fun `failed login does not save token`() = runTest {
        val sessionStore = FakeSessionStore()

        val responseBody = """
            {
                "message": "The given data was invalid.",
                "errors": {
                    "email": ["The email field is required."]
                }
            }
        """.trimIndent()

        val client = HttpClient(
            MockEngine {
                respond(
                    content = responseBody,
                    status = HttpStatusCode.UnprocessableEntity,
                    headers = headersOf(
                        HttpHeaders.ContentType,
                        ContentType.Application.Json.toString()
                    )
                )
            }
        ) {
            install(ContentNegotiation) {
                json(
                    Json {
                        ignoreUnknownKeys = true
                    }
                )
            }
        }

        try {
            val repository = AuthRepository(
                client = client,
                apiConfig = ApiConfig(
                    baseUrl = "https://example.test/api/v1"
                ),
                sessionManager = sessionStore
            )

            try {
                repository.login(
                    email = "",
                    password = "test-password"
                )

                throw AssertionError("Expected validation exception")
            } catch (exception: ApiException.Validation) {
                assertEquals(422, exception.statusCode)

                assertEquals(
                    "The email field is required.",
                    exception.fieldErrors["email"]?.first()
                )
            }

            assertEquals(null, sessionStore.getToken())
        } finally {
            client.close()
        }
    }
}
