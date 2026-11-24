package pl.dziennikpodrozy.mobile.data.remote.dto

import kotlinx.serialization.Serializable

@Serializable
data class RegisterResponseDto(
    val data: UserDto
)
