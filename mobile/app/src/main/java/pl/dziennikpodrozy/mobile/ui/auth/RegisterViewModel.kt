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
import pl.dziennikpodrozy.mobile.data.remote.dto.RegisterRequestDto
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository

data class RegisterUiState(
    val name: String = "",
    val email: String = "",
    val password: String = "",
    val passwordConfirmation: String = "",
    val profileVisibility: String = "public",
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val fieldErrors: Map<String, List<String>> = emptyMap(),
    val isSuccess: Boolean = false,
    val successMessage: String? = null
)

class RegisterViewModel(
    private val authRepository: AuthRepository,
    private val dispatcher: CoroutineDispatcher = Dispatchers.Main
) : ViewModel() {

    private val _uiState = MutableStateFlow(RegisterUiState())
    val uiState: StateFlow<RegisterUiState> = _uiState.asStateFlow()

    fun onNameChange(name: String) {
        _uiState.update { current ->
            current.copy(
                name = name,
                fieldErrors = current.fieldErrors - "name",
                errorMessage = null
            )
        }
    }

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

    fun onPasswordConfirmationChange(passwordConfirmation: String) {
        _uiState.update { current ->
            current.copy(
                passwordConfirmation = passwordConfirmation,
                fieldErrors = current.fieldErrors - "password_confirmation",
                errorMessage = null
            )
        }
    }

    fun onProfileVisibilityChange(profileVisibility: String) {
        _uiState.update { current ->
            current.copy(profileVisibility = profileVisibility)
        }
    }

    fun register(): Job {
        val currentState = _uiState.value
        val localFieldErrors = mutableMapOf<String, List<String>>()

        val nameTrimmed = currentState.name.trim()
        if (nameTrimmed.isBlank()) {
            localFieldErrors["name"] = listOf("Nazwa użytkownika jest wymagana.")
        }

        val emailTrimmed = currentState.email.trim()
        if (emailTrimmed.isBlank()) {
            localFieldErrors["email"] = listOf("Adres e-mail jest wymagany.")
        } else if (!isValidEmail(emailTrimmed)) {
            localFieldErrors["email"] = listOf("Wprowadź prawidłowy adres e-mail.")
        }

        if (currentState.password.isBlank()) {
            localFieldErrors["password"] = listOf("Hasło jest wymagane.")
        } else if (currentState.password.length < 8) {
            localFieldErrors["password"] = listOf("Hasło musi mieć co najmniej 8 znaków.")
        }

        if (currentState.passwordConfirmation.isBlank()) {
            localFieldErrors["password_confirmation"] = listOf("Powtórzenie hasła jest wymagane.")
        } else if (currentState.password != currentState.passwordConfirmation) {
            localFieldErrors["password_confirmation"] = listOf("Hasła nie są identyczne.")
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
                authRepository.register(
                    RegisterRequestDto(
                        name = nameTrimmed,
                        email = emailTrimmed,
                        password = currentState.password,
                        password_confirmation = currentState.passwordConfirmation,
                        profile_visibility = currentState.profileVisibility
                    )
                )
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        isSuccess = true,
                        successMessage = "Konto zostało pomyślnie utworzone. Możesz się zalogować."
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
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        errorMessage = exception.message ?: "Wystąpił nieoczekiwany błąd rejestracji."
                    )
                }
            }
        }
    }

    fun resetSuccessState() {
        _uiState.update { it.copy(isSuccess = false, successMessage = null) }
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
                    return RegisterViewModel(authRepository) as T
                }
            }
    }
}
