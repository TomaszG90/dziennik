package pl.dziennikpodrozy.mobile.ui.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository

data class HomeUiState(
    val appName: String = "Dziennik Podróży",
    val apiBaseUrl: String = "",
    val isReady: Boolean = true,
    val isLoggingOut: Boolean = false,
    val isLoggedOut: Boolean = false,
    val logoutError: String? = null
)

class HomeViewModel(
    private val apiConfig: ApiConfig = ApiConfig(),
    private val authRepository: AuthRepository? = null,
    private val dispatcher: CoroutineDispatcher = Dispatchers.Main
) : ViewModel() {
    private val _uiState = MutableStateFlow(
        HomeUiState(apiBaseUrl = apiConfig.baseUrl)
    )
    val uiState: StateFlow<HomeUiState> = _uiState.asStateFlow()

    fun logout(): Job {
        _uiState.update { it.copy(isLoggingOut = true, logoutError = null) }
        return viewModelScope.launch(dispatcher) {
            try {
                authRepository?.logout()
                _uiState.update {
                    it.copy(
                        isLoggingOut = false,
                        isLoggedOut = true
                    )
                }
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(
                        isLoggingOut = false,
                        logoutError = exception.message ?: "Błąd podczas wylogowywania."
                    )
                }
            }
        }
    }

    companion object {
        fun provideFactory(
            apiConfig: ApiConfig = ApiConfig(),
            authRepository: AuthRepository? = null
        ): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T {
                    return HomeViewModel(apiConfig, authRepository) as T
                }
            }
    }
}