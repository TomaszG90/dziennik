package pl.dziennikpodrozy.mobile.ui.trips

import io.ktor.client.HttpClient
import io.ktor.client.engine.mock.MockEngine
import io.ktor.client.engine.mock.respond
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.http.ContentType
import io.ktor.http.HttpHeaders
import io.ktor.http.HttpMethod
import io.ktor.http.HttpStatusCode
import io.ktor.http.headersOf
import io.ktor.serialization.kotlinx.json.json
import java.io.IOException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.repository.PlaceRepository
import pl.dziennikpodrozy.mobile.data.repository.TripRepository
import pl.dziennikpodrozy.mobile.data.session.SessionStore

@OptIn(ExperimentalCoroutinesApi::class)
class TripDetailsViewModelTest {
    private val dispatcher = StandardTestDispatcher()
    private val config = ApiConfig("https://example.test/api/v1")
    private val sessionStore = object : SessionStore {
        override val isAuthenticated = MutableStateFlow(true)
        override fun saveSession(token: String) = Unit
        override fun getToken() = "test-token"
        override fun hasActiveSession() = true
        override fun clearSession() = Unit
    }
    private val tripResponse = """{"data":{"id":10,"name":"Austria","start_date":"2026-10-29"}}"""

    @Before
    fun setUp() = Dispatchers.setMain(dispatcher)

    @After
    fun tearDown() = Dispatchers.resetMain()

    @Test
    fun `loads trip and sorts places by position`() = runTest {
        val places = """
            {"data":[
                {"id":8,"trip_id":10,"name":"Graz","latitude":"47.07","longitude":"15.44","position":2},
                {"id":3,"trip_id":10,"name":"Wiedeń","latitude":"48.21","longitude":"16.37","position":1}
            ]}
        """.trimIndent()
        client { places to HttpStatusCode.OK }.use {
            val viewModel = viewModel(it)
            assertTrue(viewModel.uiState.value.isLoading)
            val state = viewModel.uiState.first { !it.isLoading }
            assertFalse(state.isLoading)
            assertEquals("Austria", state.trip?.name)
            assertEquals(listOf(3, 8), state.places.map { place -> place.id })
        }
    }

    @Test
    fun `empty places keeps the trip visible`() = runTest {
        client { """{"data":[]}""" to HttpStatusCode.OK }.use {
            val viewModel = viewModel(it)
            val state = viewModel.uiState.first { !it.isLoading }
            assertFalse(state.isLoading)
            assertEquals(10, state.trip?.id)
            assertTrue(state.places.isEmpty())
            assertNull(state.errorMessage)
        }
    }

    @Test
    fun `failed places request can be retried`() = runTest {
        var requestCount = 0
        client {
            requestCount++
            if (requestCount == 1) {
                """{"message":"Brak dostępu."}""" to HttpStatusCode.Forbidden
            } else {
                """{"data":[]}""" to HttpStatusCode.OK
            }
        }.use {
            val viewModel = viewModel(it)
            val failedState = viewModel.uiState.first { !it.isLoading }
            assertEquals("Brak dostępu.", failedState.errorMessage)

            viewModel.loadDetails()
            assertTrue(viewModel.uiState.value.isLoading)
            assertNull(viewModel.uiState.value.errorMessage)
            val loadedState = viewModel.uiState.first { !it.isLoading }
            assertEquals(10, loadedState.trip?.id)
        }
    }

    @Test
    fun `connection failure shows a message about network access`() = runTest {
        client { throw IOException("Network unavailable") }.use {
            val viewModel = viewModel(it)
            val state = viewModel.uiState.first { !it.isLoading }
            assertFalse(state.isLoading)
            assertTrue(state.errorMessage.orEmpty().contains("Sprawdź połączenie z internetem"))
        }
    }

    @Test
    fun `delete removes place only after a successful response`() = runTest {
        var attempt = 0
        val places = """
            {"data":[{"id":3,"trip_id":10,"name":"Wiedeń","latitude":"48.21","longitude":"16.37","position":1}]}
        """.trimIndent()
        client(
            deleteResponse = {
                attempt++
                if (attempt == 1) {
                    """{"message":"Brak dostępu."}""" to HttpStatusCode.Forbidden
                } else {
                    "" to HttpStatusCode.NoContent
                }
            },
            placesResponse = { places to HttpStatusCode.OK }
        ).use {
            val viewModel = viewModel(it)
            viewModel.uiState.first { !it.isLoading }
            viewModel.deletePlace(3)
            val failed = viewModel.uiState.first { it.deletingPlaceId == null }
            assertEquals("Brak dostępu.", failed.deleteError)
            assertEquals(3, failed.places.single().id)

            viewModel.clearDeleteError()
            viewModel.deletePlace(3)
            val deleted = viewModel.uiState.first { it.deletingPlaceId == null }
            assertTrue(deleted.places.isEmpty())
            assertNull(deleted.deleteError)
        }
    }

    private fun client(
        deleteResponse: () -> Pair<String, HttpStatusCode> = { error("Unexpected delete") },
        placesResponse: () -> Pair<String, HttpStatusCode>
    ): HttpClient =
        HttpClient(MockEngine { request ->
            val (body, status) = when (request.url.encodedPath) {
                "/api/v1/trips/10" -> tripResponse to HttpStatusCode.OK
                "/api/v1/trips/10/places" -> placesResponse()
                "/api/v1/trips/10/places/3" -> {
                    assertEquals(HttpMethod.Delete, request.method)
                    deleteResponse()
                }
                else -> error("Unexpected request: ${request.url}")
            }
            respond(body, status, headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString()))
        }) {
            install(ContentNegotiation) { json() }
        }

    private fun viewModel(client: HttpClient) = TripDetailsViewModel(
        tripId = 10,
        tripRepository = TripRepository(client, config, sessionStore),
        placeRepository = PlaceRepository(client, config, sessionStore)
    )
}
