package pl.dziennikpodrozy.mobile.data.remote.dto

import kotlinx.serialization.Serializable

@Serializable
data class RegisterRequestDto(
    val name: String,
    val email: String,
    val password: String,
    val password_confirmation: String,
    val profile_visibility: String = "public"
)