package pl.dziennikpodrozy.mobile.ui.trips

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
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonObject
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.repository.PlaceRepository
import pl.dziennikpodrozy.mobile.data.session.SessionStore

@OptIn(ExperimentalCoroutinesApi::class)
class PlaceFormViewModelTest {
    private val dispatcher = StandardTestDispatcher()
    private val config = ApiConfig("https://example.test/api/v1")
    private val sessionStore = object : SessionStore {
        override val isAuthenticated = MutableStateFlow(true)
        override fun getToken() = "test-token"
        override fun hasActiveSession() = true
        override fun saveSession(token: String) = Unit
        override fun clearSession() = Unit
    }
    private val placeResponse = """
        {"data":{"id":3,"trip_id":10,"name":"Wiedeń","latitude":"48.21",
        "longitude":"16.37","position":1,"country_code":"AT"}}
    """.trimIndent()

    @Before
    fun setUp() = Dispatchers.setMain(dispatcher)

    @After
    fun tearDown() = Dispatchers.resetMain()

    @Test
    fun `invalid values keep the form open and show field errors`() = runTest {
        client { error("Invalid values must not be sent") }.use {
            val viewModel = viewModel(it)
            viewModel.changeField(PlaceField.Latitude, "91")
            viewModel.changeField(PlaceField.Longitude, "NaN")
            viewModel.changeField(PlaceField.CountryCode, "POL")
            viewModel.savePlace()

            val state = viewModel.uiState.value
            assertFalse(state.isSaving)
            assertEquals(setOf("name", "latitude", "longitude", "country_code"), state.fieldErrors.keys)
        }
    }

    @Test
    fun `create supports signed coordinates with a comma separator`() = runTest {
        client { request ->
            assertEquals(HttpMethod.Post, request.method)
            assertEquals("/api/v1/trips/10/places", request.url.encodedPath)
            val expected = """{"name":"Lima","latitude":-12.05,"longitude":-77.04,"country_code":"PE"}"""
            assertEquals(Json.parseToJsonElement(expected), Json.parseToJsonElement((request.body as TextContent).text))
            placeResponse to HttpStatusCode.Created
        }.use {
            val viewModel = viewModel(it)
            viewModel.changeField(PlaceField.Name, " Lima ")
            viewModel.changeField(PlaceField.Latitude, "-12,05")
            viewModel.changeField(PlaceField.Longitude, "-77,04")
            viewModel.changeField(PlaceField.CountryCode, "pe")
            viewModel.savePlace()

            assertTrue(viewModel.uiState.first { !it.isSaving }.isSaved)
        }
    }

    @Test
    fun `edit preloads values and preserves input on validation error`() = runTest {
        client { request ->
            assertEquals("/api/v1/trips/10/places/3", request.url.encodedPath)
            when (request.method) {
                HttpMethod.Get -> placeResponse to HttpStatusCode.OK
                HttpMethod.Patch -> {
                    val body = Json.parseToJsonElement((request.body as TextContent).text).jsonObject
                    assertEquals("null", body["country_code"].toString())
                    """{"message":"Invalid data","errors":{"name":["Błąd nazwy"]}}""" to
                        HttpStatusCode.UnprocessableEntity
                }
                else -> error("Unexpected method: ${request.method}")
            }
        }.use {
            val viewModel = viewModel(it, placeId = 3)
            val loaded = viewModel.uiState.first { !it.isLoading }
            assertEquals("Wiedeń", loaded.values[PlaceField.Name])
            assertEquals("AT", loaded.values[PlaceField.CountryCode])

            viewModel.changeField(PlaceField.Name, "Nowa nazwa")
            viewModel.changeField(PlaceField.CountryCode, "")
            viewModel.savePlace()

            val failed = viewModel.uiState.first { !it.isSaving }
            assertFalse(failed.isSaved)
            assertEquals("Nowa nazwa", failed.values[PlaceField.Name])
            assertEquals("Błąd nazwy", failed.fieldErrors["name"])
        }
    }

    private fun client(response: (HttpRequestData) -> Pair<String, HttpStatusCode>): HttpClient =
        HttpClient(MockEngine { request ->
            val (body, status) = response(request)
            respond(body, status, headersOf(HttpHeaders.ContentType, ContentType.Application.Json.toString()))
        }) {
            install(ContentNegotiation) { json(Json { explicitNulls = false }) }
        }

    private fun viewModel(client: HttpClient, placeId: Int? = null) =
        PlaceFormViewModel(10, placeId, PlaceRepository(client, config, sessionStore))
}
