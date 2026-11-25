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
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Assert.assertFalse
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.remote.dto.RegisterRequestDto
import pl.dziennikpodrozy.mobile.data.session.SessionStore

class AuthRepositoryTest {

    private class FakeSessionStore(initialToken: String? = null) : SessionStore {
        private val _isAuthenticated = MutableStateFlow(!initialToken.isNullOrBlank())
        override val isAuthenticated: StateFlow<Boolean> = _isAuthenticated.asStateFlow()
        var savedToken: String? = initialToken

        override fun saveSession(token: String) {
            savedToken = token
            _isAuthenticated.value = true
        }

        override fun getToken(): String? = savedToken

        override fun hasActiveSession(): Boolean =
            !savedToken.isNullOrBlank()

        override fun clearSession() {
            savedToken = null
            _isAuthenticated.value = false
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
                        "privacy_settings": {},
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

    @Test
    fun `successful register returns created user`() = runTest {
        val sessionStore = FakeSessionStore()

        val responseBody = """
            {
                "data": {
                    "id": 2,
                    "name": "Nowy Użytkownik",
                    "email": "nowy@example.com",
                    "first_name": null,
                    "last_name": null,
                    "city": null,
                    "birth_date": null,
                    "avatar_path": null,
                    "profile_visibility": "public",
                    "privacy_settings": {},
                    "created_at": null,
                    "updated_at": null
                }
            }
        """.trimIndent()

        val client = HttpClient(
            MockEngine {
                respond(
                    content = responseBody,
                    status = HttpStatusCode.Created,
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
                apiConfig = ApiConfig(baseUrl = "https://example.test/api/v1"),
                sessionManager = sessionStore
            )

            val result = repository.register(
                RegisterRequestDto(
                    name = "Nowy Użytkownik",
                    email = "nowy@example.com",
                    password = "secret-password",
                    password_confirmation = "secret-password",
                    profile_visibility = "public"
                )
            )

            assertEquals(2, result.data.id)
            assertEquals("Nowy Użytkownik", result.data.name)
            assertEquals("nowy@example.com", result.data.email)
        } finally {
            client.close()
        }
    }

    @Test
    fun `failed register with 422 throws validation exception`() = runTest {
        val sessionStore = FakeSessionStore()

        val responseBody = """
            {
                "message": "The email has already been taken.",
                "errors": {
                    "email": ["The email has already been taken."]
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
                apiConfig = ApiConfig(baseUrl = "https://example.test/api/v1"),
                sessionManager = sessionStore
            )

            try {
                repository.register(
                    RegisterRequestDto(
                        name = "Nowy Użytkownik",
                        email = "nowy@example.com",
                        password = "secret-password",
                        password_confirmation = "secret-password"
                    )
                )
                throw AssertionError("Expected validation exception")
            } catch (exception: ApiException.Validation) {
                assertEquals(422, exception.statusCode)
                assertEquals(
                    "The email has already been taken.",
                    exception.fieldErrors["email"]?.first()
                )
            }
        } finally {
            client.close()
        }
    }

    @Test
    fun `isAuthenticated reflects session status changes`() = runTest {
        val sessionStore = FakeSessionStore()

        val client = HttpClient(
            MockEngine {
                respond(
                    content = """{"data":{"user":{"id":1,"name":"A","email":"a@b.com","profile_visibility":"public"},"token":"token123"}}""",
                    status = HttpStatusCode.OK,
                    headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
                )
            }
        ) {
            install(ContentNegotiation) {
                json(Json { ignoreUnknownKeys = true })
            }
        }

        try {
            val repository = AuthRepository(
                client = client,
                apiConfig = ApiConfig(baseUrl = "https://example.test/api/v1"),
                sessionManager = sessionStore
            )

            assertFalse(repository.isAuthenticated.value)

            repository.login("a@b.com", "pass")
            assertTrue(repository.isAuthenticated.value)

            repository.logout()
            assertFalse(repository.isAuthenticated.value)
        } finally {
            client.close()
        }
    }
}
