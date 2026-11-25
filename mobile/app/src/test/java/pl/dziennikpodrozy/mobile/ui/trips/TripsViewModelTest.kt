package pl.dziennikpodrozy.mobile.ui.trips

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
import pl.dziennikpodrozy.mobile.data.repository.TripRepository
import pl.dziennikpodrozy.mobile.data.session.SessionStore

@OptIn(ExperimentalCoroutinesApi::class)
class TripsViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()

    private val sessionStore = object : SessionStore {
        override val isAuthenticated: StateFlow<Boolean> = MutableStateFlow(true).asStateFlow()
        override fun saveSession(token: String) = Unit
        override fun getToken(): String = "test-token"
        override fun hasActiveSession(): Boolean = true
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
    fun `loadTrips success updates uiState with trips list`() = runTest {
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

        val client = HttpClient(MockEngine {
            respond(
                content = responseBody,
                status = HttpStatusCode.OK,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        val repository = repository(client)
        val viewModel = TripsViewModel(
            tripRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.loadTrips().join()

        assertFalse(viewModel.uiState.value.isLoading)
        assertFalse(viewModel.uiState.value.isEmpty)
        assertEquals(1, viewModel.uiState.value.trips.size)
        assertEquals("Wakacje w Austrii", viewModel.uiState.value.trips.first().name)
        client.close()
    }

    @Test
    fun `loadTrips empty list sets isEmpty to true`() = runTest {
        val responseBody = """{ "data": [] }"""

        val client = HttpClient(MockEngine {
            respond(
                content = responseBody,
                status = HttpStatusCode.OK,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        val repository = repository(client)
        val viewModel = TripsViewModel(
            tripRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.loadTrips().join()

        assertFalse(viewModel.uiState.value.isLoading)
        assertTrue(viewModel.uiState.value.isEmpty)
        assertEquals(0, viewModel.uiState.value.trips.size)
        client.close()
    }

    @Test
    fun `deleteTrip removes trip from state list`() = runTest {
        var isDeletedOnServer = false
        val responseListBody = """
            {
                "data": [
                    { "id": 1, "name": "A", "start_date": "2026-10-01" },
                    { "id": 2, "name": "B", "start_date": "2026-10-05" }
                ]
            }
        """.trimIndent()

        val client = HttpClient(MockEngine { request ->
            if (request.method.value == "DELETE") {
                isDeletedOnServer = true
                respond("", status = HttpStatusCode.NoContent)
            } else {
                respond(
                    content = responseListBody,
                    status = HttpStatusCode.OK,
                    headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
                )
            }
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        val repository = repository(client)
        val viewModel = TripsViewModel(
            tripRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.loadTrips().join()

        assertEquals(2, viewModel.uiState.value.trips.size)

        viewModel.deleteTrip(1).join()

        assertTrue(isDeletedOnServer)
        assertEquals(1, viewModel.uiState.value.trips.size)
        assertEquals(2, viewModel.uiState.value.trips.first().id)
        client.close()
    }

    private fun repository(client: HttpClient) = TripRepository(
        client = client,
        apiConfig = ApiConfig("https://example.test/api/v1"),
        sessionManager = sessionStore
    )
}
