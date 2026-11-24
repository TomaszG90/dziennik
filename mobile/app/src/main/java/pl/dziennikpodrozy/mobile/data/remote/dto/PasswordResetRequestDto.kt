package pl.dziennikpodrozy.mobile.data.remote.dto

import kotlinx.serialization.Serializable

@Serializable
data class ForgotPasswordRequestDto(
    val email: String
)

@Serializable
data class ResetPasswordRequestDto(
    val email: String,
    val token: String,
    val password: String,
    val password_confirmation: String
)

@Serializable
data class PasswordResetResponseDto(
    val message: String
)
