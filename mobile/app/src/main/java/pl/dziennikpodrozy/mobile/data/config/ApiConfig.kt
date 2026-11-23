package pl.dziennikpodrozy.mobile.data.config

import pl.dziennikpodrozy.mobile.BuildConfig

data class ApiConfig(
    val baseUrl: String = BuildConfig.API_BASE_URL
)