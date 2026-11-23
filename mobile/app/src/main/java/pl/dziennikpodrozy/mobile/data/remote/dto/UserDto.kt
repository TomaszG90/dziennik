
package pl.dziennikpodrozy.mobile.data.remote.dto

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

@Serializable
data class UserDto(
    val id: Int,
    val name: String,
    val email: String,
    val first_name: String? = null,
    val last_name: String? = null,
    val city: String? = null,
    val birth_date: String? = null,
    val avatar_path: String? = null,
    val profile_visibility: String,
    val privacy_settings: List<JsonElement> = emptyList(),
    val created_at: String? = null,
    val updated_at: String? = null
)
