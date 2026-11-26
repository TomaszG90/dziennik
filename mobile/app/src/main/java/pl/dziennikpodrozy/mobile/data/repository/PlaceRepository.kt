package pl.dziennikpodrozy.mobile.data.repository

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.HttpRequestBuilder
import io.ktor.client.request.bearerAuth
import io.ktor.client.request.delete
import io.ktor.client.request.get
import io.ktor.client.request.patch
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.client.statement.HttpResponse
import io.ktor.client.statement.bodyAsText
import io.ktor.http.ContentType
import io.ktor.http.contentType
import io.ktor.http.isSuccess
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiError
import pl.dziennikpodrozy.mobile.data.remote.ApiExceptionMapper
import pl.dziennikpodrozy.mobile.data.remote.dto.CreatePlaceRequestDto
import pl.dziennikpodrozy.mobile.data.remote.dto.PlaceDto
import pl.dziennikpodrozy.mobile.data.remote.dto.PlaceResponseDto
import pl.dziennikpodrozy.mobile.data.remote.dto.PlacesListResponseDto
import pl.dziennikpodrozy.mobile.data.remote.dto.ReorderPlacesRequestDto
import pl.dziennikpodrozy.mobile.data.remote.dto.UpdatePlaceRequestDto
import pl.dziennikpodrozy.mobile.data.session.SessionStore

class PlaceRepository(
    private val client: HttpClient,
    private val apiConfig: ApiConfig,
    private val sessionStore: SessionStore
) {
    suspend fun getPlaces(tripId: Int): List<PlaceDto> {
        val response = client.get(placesUrl(tripId)) { authorize() }
        return response.requireSuccess().body<PlacesListResponseDto>().data
    }

    suspend fun getPlace(tripId: Int, placeId: Int): PlaceDto {
        val response = client.get("${placesUrl(tripId)}/$placeId") { authorize() }
        return response.requireSuccess().body<PlaceResponseDto>().data
    }

    suspend fun createPlace(tripId: Int, request: CreatePlaceRequestDto): PlaceDto {
        val response = client.post(placesUrl(tripId)) {
            authorize()
            contentType(ContentType.Application.Json)
            setBody(request)
        }
        return response.requireSuccess().body<PlaceResponseDto>().data
    }

    suspend fun updatePlace(tripId: Int, placeId: Int, request: UpdatePlaceRequestDto): PlaceDto {
        val response = client.patch("${placesUrl(tripId)}/$placeId") {
            authorize()
            contentType(ContentType.Application.Json)
            setBody(request)
        }
        return response.requireSuccess().body<PlaceResponseDto>().data
    }

    suspend fun deletePlace(tripId: Int, placeId: Int) {
        client.delete("${placesUrl(tripId)}/$placeId") { authorize() }.requireSuccess()
    }

    suspend fun reorderPlaces(tripId: Int, placeIds: List<Int>): List<PlaceDto> {
        val response = client.patch("${placesUrl(tripId)}/reorder") {
            authorize()
            contentType(ContentType.Application.Json)
            setBody(ReorderPlacesRequestDto(placeIds))
        }
        return response.requireSuccess().body<PlacesListResponseDto>().data
    }

    private fun placesUrl(tripId: Int): String = "${apiConfig.baseUrl}/trips/$tripId/places"

    private fun HttpRequestBuilder.authorize() {
        val token = sessionStore.getToken()
        if (!token.isNullOrBlank()) bearerAuth(token)
    }

    private suspend fun HttpResponse.requireSuccess(): HttpResponse {
        if (status.isSuccess()) return this

        val error = try {
            Json.decodeFromString<ApiError>(bodyAsText())
        } catch (_: SerializationException) {
            null
        }
        throw ApiExceptionMapper.map(status.value, error)
    }
}
