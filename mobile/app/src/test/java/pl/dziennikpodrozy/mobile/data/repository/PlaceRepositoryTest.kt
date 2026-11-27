package pl.dziennikpodrozy.mobile.data.repository

import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.request.HttpRequestData
import io.ktor.http.ContentType
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpMethod
import io.ktor.http.HttpStatusCode
import io.ktor.http.content.TextContent
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonNull
import org.junit.Assert.assertEquals
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.remote.dto.CreatePlaceRequestDto
import pl.dziennikpodrozy.mobile.data.remote.dto.UpdatePlaceRequestDto
import pl.dziennikpodrozy.mobile.data.session.SessionStore

class PlaceRepositoryTest {
    private val sessionStore = object : SessionStore {
        override val isAuthenticated = MutableStateFlow(true)
        override fun saveSession(token: String) = Unit
        override fun getToken() = "test-token"
        override fun hasActiveSession() = true
        override fun clearSession() = Unit
    }

    private val placesPath = "/api/v1/trips/10/places"
    private val place = """
        {
            "id": 3,
            "trip_id": 10,
            "name": "Wiedeń",
            "latitude": "48.2082000",
            "longitude": "16.3738000",
            "position": 1,
            "country_code": null,
            "created_at": null,
            "updated_at": null
        }
    """.trimIndent()
    private val placeResponse = """{"data":$place}"""

    @Test
    fun `list places reads response coordinates as strings`() = runTest {
        client(HttpMethod.Get, placesPath, """{"data":[$place]}""").use {
            val result = repository(it).getPlaces(10).single()
            assertEquals(10, result.tripId)
            assertEquals("48.2082000", result.latitude)
        }
    }

    @Test
    fun `get place uses the trip and place IDs`() = runTest {
        client(HttpMethod.Get, "$placesPath/3", placeResponse).use {
            assertEquals(3, repository(it).getPlace(10, 3).id)
        }
    }

    @Test
    fun `create place sends numeric coordinates`() = runTest {
        client(HttpMethod.Post, placesPath, placeResponse, HttpStatusCode.Created) {
            assertJsonBody(it, """{"name":"Wiedeń","latitude":48.2082,"longitude":16.3738}""")
        }.use {
            val result = repository(it).createPlace(10, CreatePlaceRequestDto("Wiedeń", 48.2082, 16.3738))
            assertEquals(3, result.id)
        }
    }

    @Test
    fun `update place can clear country code without changing other fields`() = runTest {
        client(HttpMethod.Patch, "$placesPath/3", placeResponse) {
            assertJsonBody(it, """{"country_code":null}""")
        }.use {
            val result = repository(it).updatePlace(10, 3, UpdatePlaceRequestDto(countryCode = JsonNull))
            assertEquals(null, result.countryCode)
        }
    }

    @Test
    fun `delete place accepts an empty 204 response`() = runTest {
        client(HttpMethod.Delete, "$placesPath/3", "", HttpStatusCode.NoContent).use {
            repository(it).deletePlace(10, 3)
        }
    }

    @Test
    fun `reorder places sends every ID and returns the server order`() = runTest {
        val otherPlace = place.replace("\"id\": 3", "\"id\": 8")
        val movedPlace = place.replace("\"position\": 1", "\"position\": 2")
        client(HttpMethod.Patch, "$placesPath/reorder", """{"data":[$otherPlace,$movedPlace]}""") {
            assertJsonBody(it, """{"place_ids":[3,8]}""")
        }.use {
            val places = repository(it).reorderPlaces(10, listOf(3, 8))
            assertEquals(listOf(8, 3), places.map { place -> place.id })
        }
    }

    @Test
    fun `validation response preserves field errors`() = runTest {
        val body = """{"message":"Invalid data","errors":{"place_ids":["Invalid order"]}}"""
        client(HttpMethod.Patch, "$placesPath/reorder", body, HttpStatusCode.UnprocessableEntity).use {
            try {
                repository(it).reorderPlaces(10, listOf(3))
                throw AssertionError("Expected validation exception")
            } catch (exception: ApiException.Validation) {
                assertEquals(422, exception.statusCode)
                assertEquals(listOf("Invalid order"), exception.fieldErrors["place_ids"])
            }
        }
    }

    private fun client(
        method: HttpMethod,
        path: String,
        responseBody: String,
        status: HttpStatusCode = HttpStatusCode.OK,
        checkRequest: (HttpRequestData) -> Unit = {}
    ): HttpClient = HttpClient(MockEngine { request ->
        assertEquals(method, request.method)
        assertEquals(path, request.url.encodedPath)
        assertEquals("Bearer test-token", request.headers[HttpHeaders.Authorization])
        checkRequest(request)
        respond(responseBody, status, headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString()))
    }) {
        install(ContentNegotiation) {
            json(Json {
                ignoreUnknownKeys = true
                explicitNulls = false
            })
        }
    }

    private fun assertJsonBody(request: HttpRequestData, expected: String) {
        val body = request.body as TextContent
        assertEquals(Json.parseToJsonElement(expected), Json.parseToJsonElement(body.text))
    }

    private fun repository(client: HttpClient) =
        PlaceRepository(client, ApiConfig("https://example.test/api/v1"), sessionStore)
}
