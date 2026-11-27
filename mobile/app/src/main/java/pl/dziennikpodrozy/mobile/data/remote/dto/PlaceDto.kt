package pl.dziennikpodrozy.mobile.data.remote.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonPrimitive

@Serializable
data class PlaceDto(
    val id: Int,
    @SerialName("trip_id") val tripId: Int,
    val name: String,
    val latitude: String,
    val longitude: String,
    val position: Int,
    @SerialName("country_code") val countryCode: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null
)

@Serializable
data class CreatePlaceRequestDto(
    val name: String,
    val latitude: Double,
    val longitude: Double,
    @SerialName("country_code") val countryCode: String? = null
)

@Serializable
data class UpdatePlaceRequestDto(
    val name: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null,
    // Omit the field with Kotlin null, or clear it with JsonNull.
    @SerialName("country_code") val countryCode: JsonPrimitive? = null
)

@Serializable
internal data class ReorderPlacesRequestDto(
    @SerialName("place_ids") val placeIds: List<Int>
)

@Serializable
internal data class PlaceResponseDto(val data: PlaceDto)

@Serializable
internal data class PlacesListResponseDto(val data: List<PlaceDto>)
