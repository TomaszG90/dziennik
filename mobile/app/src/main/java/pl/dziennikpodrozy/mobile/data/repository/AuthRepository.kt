
package pl.dziennikpodrozy.mobile.data.repository

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.client.request.header
import io.ktor.client.statement.bodyAsText
import io.ktor.http.ContentType
import io.ktor.http.contentType
import io.ktor.http.isSuccess
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiError
import pl.dziennikpodrozy.mobile.data.remote.ApiExceptionMapper
import pl.dziennikpodrozy.mobile.data.remote.dto.LoginRequestDto
import pl.dziennikpodrozy.mobile.data.remote.dto.LoginResponseDto
import pl.dziennikpodrozy.mobile.data.session.SessionStore

class AuthRepository(
    private val client: HttpClient,
    private val apiConfig: ApiConfig,
    private val sessionManager: SessionStore
) {

    suspend fun login(
        email: String,
        password: String
    ): LoginResponseDto {
        val response = client.post("${apiConfig.baseUrl}/auth/login") {
            contentType(ContentType.Application.Json)
            setBody(
                LoginRequestDto(
                    email = email,
                    password = password
                )
            )
        }

        if (response.status.isSuccess()) {
            val loginResponse = response.body<LoginResponseDto>()

            sessionManager.saveSession(loginResponse.data.token)

            return loginResponse
        }

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

    fun getSavedToken(): String? {
        return sessionManager.getToken()
    }

    fun hasActiveSession(): Boolean {
        return sessionManager.hasActiveSession()
    }


    suspend fun logout() {
        val token = sessionManager.getToken() ?: return

        val response = try {
            client.post("${apiConfig.baseUrl}/auth/logout") {
                header(
                    io.ktor.http.HttpHeaders.Authorization,
                    "Bearer $token"
                )
            }
        } finally {
            // Czyścimy lokalną sesję również wtedy,
            // gdy serwer jest niedostępny.
            sessionManager.clearSession()
        }

        // 401 oznacza, że token jest już nieważny.
        if (!response.status.isSuccess() && response.status.value != 401) {
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

}
