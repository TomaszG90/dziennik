
package pl.dziennikpodrozy.mobile.data.remote

import kotlinx.serialization.Serializable

@Serializable
data class ApiError(
    val message: String? = null,
    val errors: Map<String, List<String>> = emptyMap()
)
