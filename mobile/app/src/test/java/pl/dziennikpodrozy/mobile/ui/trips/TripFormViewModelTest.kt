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
class TripFormViewModelTest {

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
    fun `saveTrip with empty fields validates locally without calling backend`() = runTest {
        var backendCalled = false
        val client = HttpClient(MockEngine {
            backendCalled = true
            respond("")
        })

        val repository = repository(client)
        val viewModel = TripFormViewModel(
            tripRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )

        viewModel.saveTrip()

        assertFalse(backendCalled)
        assertEquals("Nazwa podróży jest wymagana.", viewModel.uiState.value.fieldErrors["name"]?.first())
        assertEquals("Data rozpoczęcia jest wymagana.", viewModel.uiState.value.fieldErrors["start_date"]?.first())
        assertFalse(viewModel.uiState.value.isSuccess)
        client.close()
    }

    @Test
    fun `saveTrip with endDate before startDate produces local validation error`() = runTest {
        var backendCalled = false
        val client = HttpClient(MockEngine {
            backendCalled = true
            respond("")
        })

        val repository = repository(client)
        val viewModel = TripFormViewModel(
            tripRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )

        viewModel.onNameChange("Wakacje")
        viewModel.onStartDateChange("2026-10-20")
        viewModel.onEndDateChange("2026-10-10")
        viewModel.saveTrip()

        assertFalse(backendCalled)
        assertEquals(
            "Data zakończenia nie może być wcześniejsza niż data rozpoczęcia.",
            viewModel.uiState.value.fieldErrors["end_date"]?.first()
        )
        client.close()
    }

    @Test
    fun `saveTrip creation mode success sets isSuccess`() = runTest {
        val responseBody = """
            {
                "data": {
                    "id": 1,
                    "name": "Wakacje",
                    "start_date": "2026-10-20",
                    "end_date": "2026-10-25"
                }
            }
        """.trimIndent()

        val client = HttpClient(MockEngine {
            respond(
                content = responseBody,
                status = HttpStatusCode.Created,
                headers = headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString())
            )
        }) {
            install(ContentNegotiation) { json(Json { ignoreUnknownKeys = true }) }
        }

        val repository = repository(client)
        val viewModel = TripFormViewModel(
            tripRepository = repository,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )

        viewModel.onNameChange("Wakacje")
        viewModel.onStartDateChange("2026-10-20")
        viewModel.onEndDateChange("2026-10-25")
        viewModel.saveTrip().join()

        assertTrue(viewModel.uiState.value.isSuccess)
        assertFalse(viewModel.uiState.value.isLoading)
        client.close()
    }

    @Test
    fun `loadTripForEdit sets initial form fields`() = runTest {
        val responseBody = """
            {
                "data": {
                    "id": 5,
                    "name": "Wiedeń 2026",
                    "start_date": "2026-06-01",
                    "end_date": "2026-06-07",
                    "description": "Wyjazd wakacyjny"
                }
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
        val viewModel = TripFormViewModel(
            tripRepository = repository,
            initialTripId = 5,
            dispatcher = UnconfinedTestDispatcher(testScheduler)
        )
        viewModel.loadTripForEdit(5).join()

        assertEquals("Wiedeń 2026", viewModel.uiState.value.name)
        assertEquals("2026-06-01", viewModel.uiState.value.startDate)
        assertEquals("2026-06-07", viewModel.uiState.value.endDate)
        assertEquals("Wyjazd wakacyjny", viewModel.uiState.value.description)
        assertFalse(viewModel.uiState.value.isInitialLoading)
        client.close()
    }

    private fun repository(client: HttpClient) = TripRepository(
        client = client,
        apiConfig = ApiConfig("https://example.test/api/v1"),
        sessionManager = sessionStore
    )
}
