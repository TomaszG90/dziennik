package pl.dziennikpodrozy.mobile.ui.auth

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
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val fieldErrors: Map<String, List<String>> = emptyMap(),
    val isSuccess: Boolean = false
)

class LoginViewModel(
    private val authRepository: AuthRepository,
    private val dispatcher: CoroutineDispatcher = Dispatchers.Main
) : ViewModel() {

    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun onEmailChange(email: String) {
        _uiState.update { current ->
            current.copy(
                email = email,
                fieldErrors = current.fieldErrors - "email",
                errorMessage = null
            )
        }
    }

    fun onPasswordChange(password: String) {
        _uiState.update { current ->
            current.copy(
                password = password,
                fieldErrors = current.fieldErrors - "password",
                errorMessage = null
            )
        }
    }

    fun login(): Job {
        val currentState = _uiState.value
        val localFieldErrors = mutableMapOf<String, List<String>>()

        val emailTrimmed = currentState.email.trim()
        if (emailTrimmed.isBlank()) {
            localFieldErrors["email"] = listOf("Adres e-mail jest wymagany.")
        } else if (!isValidEmail(emailTrimmed)) {
            localFieldErrors["email"] = listOf("Wprowadź prawidłowy adres e-mail.")
        }

        if (currentState.password.isBlank()) {
            localFieldErrors["password"] = listOf("Hasło jest wymagane.")
        }

        if (localFieldErrors.isNotEmpty()) {
            _uiState.update { it.copy(fieldErrors = localFieldErrors) }
            return Job().apply { complete() }
        }

        _uiState.update {
            it.copy(
                isLoading = true,
                errorMessage = null,
                fieldErrors = emptyMap()
            )
        }

        return viewModelScope.launch(dispatcher) {
            try {
                authRepository.login(
                    email = emailTrimmed,
                    password = currentState.password
                )
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        isSuccess = true
                    )
                }
            } catch (exception: ApiException.Validation) {
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        fieldErrors = exception.fieldErrors,
                        errorMessage = exception.message
                    )
                }
            } catch (exception: ApiException.Unauthorized) {
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        errorMessage = "Nieprawidłowy adres e-mail lub hasło."
                    )
                }
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        errorMessage = exception.message ?: "Wystąpił nieoczekiwany błąd logowania."
                    )
                }
            }
        }
    }

    fun resetSuccessState() {
        _uiState.update { it.copy(isSuccess = false) }
    }

    private fun isValidEmail(email: String): Boolean {
        return EMAIL_REGEX.matches(email)
    }

    companion object {
        private val EMAIL_REGEX = "^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$".toRegex()

        fun provideFactory(authRepository: AuthRepository): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T {
                    return LoginViewModel(authRepository) as T
                }
            }
    }
}
