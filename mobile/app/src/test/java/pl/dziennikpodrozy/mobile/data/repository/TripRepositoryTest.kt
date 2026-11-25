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
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.remote.dto.CreateTripRequestDto
import pl.dziennikpodrozy.mobile.data.remote.dto.UpdateTripRequestDto
import pl.dziennikpodrozy.mobile.data.session.SessionStore

class TripRepositoryTest {

    private class FakeSessionStore : SessionStore {
        private val _isAuthenticated = MutableStateFlow(true)
        override val isAuthenticated: StateFlow<Boolean> = _isAuthenticated.asStateFlow()
        override fun saveSession(token: String) = Unit
        override fun getToken(): String? = "test-token-123"
        override fun hasActiveSession(): Boolean = true
        override fun clearSession() = Unit
    }

    private val sessionStore = FakeSessionStore()

    @Test
    fun `getTrips sends bearer token and returns list of trips`() = runTest {
        var capturedAuthHeader: String? = null
        var capturedPath = ""

        val responseBody = """
            {
                "data": [
                    {
                        "id": 1,
                        "name": "Wakacje w Austrii",
                        "start_date": "2026-10-29",
                        "end_date": "2026-11-05",
                        "description": "Wiedeń"
                    }
                ]
            }
        """.trimIndent()

        val client = HttpClient(MockEngine { request ->
            capturedAuthHeader = request.headers[HttpHeaders.Authorization]
            capturedPath = request.url.encodedPath
            respond(
                content = responseBody,
                status = HttpStatusCode.OK,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        try {
            val repository = repository(client)
            val trips = repository.getTrips()

            assertEquals("Bearer test-token-123", capturedAuthHeader)
            assertEquals("/api/v1/trips", capturedPath)
            assertEquals(1, trips.size)
            assertEquals("Wakacje w Austrii", trips.first().name)
        } finally {
            client.close()
        }
    }

    @Test
    fun `createTrip posts new trip and returns created Dto`() = runTest {
        var capturedPath = ""
        var capturedBody = ""

        val responseBody = """
            {
                "data": {
                    "id": 10,
                    "name": "Wyjazd w góry",
                    "start_date": "2026-12-20",
                    "end_date": "2026-12-27",
                    "description": "Zakopane"
                }
            }
        """.trimIndent()

        val client = HttpClient(MockEngine { request ->
            capturedPath = request.url.encodedPath
            capturedBody = (request.body as? TextContent)?.text.orEmpty()
            respond(
                content = responseBody,
                status = HttpStatusCode.Created,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        try {
            val repository = repository(client)
            val createdTrip = repository.createTrip(
                CreateTripRequestDto(
                    name = "Wyjazd w góry",
                    start_date = "2026-12-20",
                    end_date = "2026-12-27",
                    description = "Zakopane"
                )
            )

            assertEquals("/api/v1/trips", capturedPath)
            assertTrue(capturedBody.contains(""""name":"Wyjazd w góry""""))
            assertEquals(10, createdTrip.id)
            assertEquals("Wyjazd w góry", createdTrip.name)
        } finally {
            client.close()
        }
    }

    @Test
    fun `updateTrip sends patch and returns updated Dto`() = runTest {
        var capturedPath = ""
        var capturedMethod = ""

        val responseBody = """
            {
                "data": {
                    "id": 10,
                    "name": "Zmieniona nazwa",
                    "start_date": "2026-12-20",
                    "end_date": "2026-12-28",
                    "description": "Zakopane"
                }
            }
        """.trimIndent()

        val client = HttpClient(MockEngine { request ->
            capturedPath = request.url.encodedPath
            capturedMethod = request.method.value
            respond(
                content = responseBody,
                status = HttpStatusCode.OK,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        try {
            val repository = repository(client)
            val updatedTrip = repository.updateTrip(
                id = 10,
                request = UpdateTripRequestDto(
                    name = "Zmieniona nazwa",
                    end_date = "2026-12-28"
                )
            )

            assertEquals("/api/v1/trips/10", capturedPath)
            assertEquals("PATCH", capturedMethod)
            assertEquals("Zmieniona nazwa", updatedTrip.name)
        } finally {
            client.close()
        }
    }

    @Test
    fun `deleteTrip sends delete request and completes`() = runTest {
        var capturedPath = ""
        var capturedMethod = ""

        val client = HttpClient(MockEngine { request ->
            capturedPath = request.url.encodedPath
            capturedMethod = request.method.value
            respond(
                content = "",
                status = HttpStatusCode.NoContent,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        })

        try {
            val repository = repository(client)
            repository.deleteTrip(10)

            assertEquals("/api/v1/trips/10", capturedPath)
            assertEquals("DELETE", capturedMethod)
        } finally {
            client.close()
        }
    }

    @Test
    fun `createTrip with 422 validation error throws ApiException Validation`() = runTest {
        val errorResponseBody = """
            {
                "message": "The given data was invalid.",
                "errors": {
                    "name": ["Pole nazwa jest wymagane."]
                }
            }
        """.trimIndent()

        val client = HttpClient(MockEngine {
            respond(
                content = errorResponseBody,
                status = HttpStatusCode.UnprocessableEntity,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        try {
            val repository = repository(client)
            try {
                repository.createTrip(
                    CreateTripRequestDto(
                        name = "",
                        start_date = "2026-10-29"
                    )
                )
                throw AssertionError("Expected validation exception")
            } catch (exception: ApiException.Validation) {
                assertEquals(422, exception.statusCode)
                assertEquals("Pole nazwa jest wymagane.", exception.fieldErrors["name"]?.first())
            }
        } finally {
            client.close()
        }
    }

    private fun repository(client: HttpClient) = TripRepository(
        client = client,
        apiConfig = ApiConfig("https://example.test/api/v1"),
        sessionManager = sessionStore
    )
}
