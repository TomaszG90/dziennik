package pl.dziennikpodrozy.mobile.ui.home

import androidx.lifecycle.ViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import pl.dziennikpodrozy.mobile.data.config.ApiConfig

data class HomeUiState(
    val appName: String = "Dziennik Podróży",
    val apiBaseUrl: String = "",
    val isReady: Boolean = true
)

class HomeViewModel(
    private val apiConfig: ApiConfig = ApiConfig()
) : ViewModel() {
    private val _uiState = MutableStateFlow(
        HomeUiState(apiBaseUrl = apiConfig.baseUrl)
    )
    val uiState: StateFlow<HomeUiState> = _uiState.asStateFlow()
}