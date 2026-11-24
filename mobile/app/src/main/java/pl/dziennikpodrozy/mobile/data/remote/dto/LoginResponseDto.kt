
package pl.dziennikpodrozy.mobile.data.remote.dto

import kotlinx.serialization.Serializable

@Serializable
data class LoginResponseDto(
    val data: LoginDataDto
)

@Serializable
data class LoginDataDto(
    val user: UserDto,
    val token: String
)
