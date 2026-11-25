package pl.dziennikpodrozy.mobile.data.remote.dto

import kotlinx.serialization.Serializable

@Serializable
data class TripDto(
    val id: Int,
    val name: String,
    val start_date: String,
    val end_date: String? = null,
    val description: String? = null,
    val created_at: String? = null,
    val updated_at: String? = null
)

@Serializable
data class CreateTripRequestDto(
    val name: String,
    val start_date: String,
    val end_date: String? = null,
    val description: String? = null
)

@Serializable
data class UpdateTripRequestDto(
    val name: String? = null,
    val start_date: String? = null,
    val end_date: String? = null,
    val description: String? = null
)

@Serializable
data class TripResponseDto(
    val data: TripDto
)

@Serializable
data class TripsListResponseDto(
    val data: List<TripDto>
)
