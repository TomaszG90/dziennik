package pl.dziennikpodrozy.mobile.data.repository

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.delete
import io.ktor.client.request.get
import io.ktor.client.request.header
import io.ktor.client.request.patch
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.client.statement.HttpResponse
import io.ktor.client.statement.bodyAsText
import io.ktor.http.ContentType
import io.ktor.http.HttpHeaders
import io.ktor.http.contentType
import io.ktor.http.isSuccess
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiError
import pl.dziennikpodrozy.mobile.data.remote.ApiExceptionMapper
import pl.dziennikpodrozy.mobile.data.remote.dto.CreateTripRequestDto
import pl.dziennikpodrozy.mobile.data.remote.dto.TripDto
import pl.dziennikpodrozy.mobile.data.remote.dto.TripResponseDto
import pl.dziennikpodrozy.mobile.data.remote.dto.TripsListResponseDto
import pl.dziennikpodrozy.mobile.data.remote.dto.UpdateTripRequestDto
import pl.dziennikpodrozy.mobile.data.session.SessionStore

class TripRepository(
    private val client: HttpClient,
    private val apiConfig: ApiConfig,
    private val sessionManager: SessionStore
) {

    private fun getAuthTokenHeader(): String {
        val token = sessionManager.getToken()
        return if (!token.isNullOrBlank()) "Bearer $token" else ""
    }

    suspend fun getTrips(): List<TripDto> {
        val response = client.get("${apiConfig.baseUrl}/trips") {
            header(HttpHeaders.Authorization, getAuthTokenHeader())
        }

        if (response.status.isSuccess()) {
            return response.body<TripsListResponseDto>().data
        }

        handleErrorResponse(response)
    }

    suspend fun getTrip(id: Int): TripDto {
        val response = client.get("${apiConfig.baseUrl}/trips/$id") {
            header(HttpHeaders.Authorization, getAuthTokenHeader())
        }

        if (response.status.isSuccess()) {
            return response.body<TripResponseDto>().data
        }

        handleErrorResponse(response)
    }

    suspend fun createTrip(request: CreateTripRequestDto): TripDto {
        val response = client.post("${apiConfig.baseUrl}/trips") {
            header(HttpHeaders.Authorization, getAuthTokenHeader())
            contentType(ContentType.Application.Json)
            setBody(request)
        }

        if (response.status.isSuccess()) {
            return response.body<TripResponseDto>().data
        }

        handleErrorResponse(response)
    }

    suspend fun updateTrip(id: Int, request: UpdateTripRequestDto): TripDto {
        val response = client.patch("${apiConfig.baseUrl}/trips/$id") {
            header(HttpHeaders.Authorization, getAuthTokenHeader())
            contentType(ContentType.Application.Json)
            setBody(request)
        }

        if (response.status.isSuccess()) {
            return response.body<TripResponseDto>().data
        }

        handleErrorResponse(response)
    }

    suspend fun deleteTrip(id: Int) {
        val response = client.delete("${apiConfig.baseUrl}/trips/$id") {
            header(HttpHeaders.Authorization, getAuthTokenHeader())
        }

        if (response.status.isSuccess()) {
            return
        }

        handleErrorResponse(response)
    }

    private suspend fun handleErrorResponse(response: HttpResponse): Nothing {
        val error = try {
            Json.decodeFromString<ApiError>(response.bodyAsText())
        } catch (_: SerializationException) {
            null
        }

        throw ApiExceptionMapper.map(
            statusCode = response.status.value,
            error = error
        )
    }
}
