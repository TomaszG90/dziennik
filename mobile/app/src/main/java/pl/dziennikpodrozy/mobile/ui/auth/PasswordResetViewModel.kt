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
import pl.dziennikpodrozy.mobile.data.remote.dto.ResetPasswordRequestDto
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository

data class PasswordResetUiState(
    val email: String = "",
    val token: String = "",
    val password: String = "",
    val passwordConfirmation: String = "",
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val fieldErrors: Map<String, List<String>> = emptyMap(),
    val successMessage: String? = null
)

class PasswordResetViewModel(
    private val authRepository: AuthRepository,
    initialEmail: String = "",
    initialToken: String = "",
    private val dispatcher: CoroutineDispatcher = Dispatchers.Main
) : ViewModel() {

    private val _uiState = MutableStateFlow(
        PasswordResetUiState(email = initialEmail, token = initialToken)
    )
    val uiState: StateFlow<PasswordResetUiState> = _uiState.asStateFlow()

    fun onEmailChange(email: String) = updateField("email") {
        it.copy(email = email)
    }

    fun onTokenChange(token: String) = updateField("token") {
        it.copy(token = token)
    }

    fun onPasswordChange(password: String) = updateField("password") {
        it.copy(password = password)
    }

    fun onPasswordConfirmationChange(passwordConfirmation: String) =
        updateField("password_confirmation") {
            it.copy(passwordConfirmation = passwordConfirmation)
        }

    fun requestResetLink(): Job {
        val email = _uiState.value.email.trim()
        val errors = validateEmail(email)
        if (errors.isNotEmpty()) {
            _uiState.update { it.copy(fieldErrors = errors, successMessage = null) }
            return Job().apply { complete() }
        }

        _uiState.update {
            it.copy(isLoading = true, errorMessage = null, fieldErrors = emptyMap(), successMessage = null)
        }
        return viewModelScope.launch(dispatcher) {
            try {
                val response = authRepository.sendPasswordResetLink(email)
                _uiState.update {
                    it.copy(isLoading = false, successMessage = response.message)
                }
            } catch (exception: ApiException.Validation) {
                applyValidationError(exception)
            } catch (exception: Exception) {
                applyUnexpectedError(exception, "Nie udało się wysłać linku do zmiany hasła.")
            }
        }
    }

    fun resetPassword(): Job {
        val state = _uiState.value
        val email = state.email.trim()
        val errors = validateEmail(email).toMutableMap()
        if (state.token.isBlank()) {
            errors["token"] = listOf("Token z wiadomości e-mail jest wymagany.")
        }
        if (state.password.isBlank()) {
            errors["password"] = listOf("Hasło jest wymagane.")
        } else if (state.password.length < 8) {
            errors["password"] = listOf("Hasło musi mieć co najmniej 8 znaków.")
        }
        if (state.passwordConfirmation.isBlank()) {
            errors["password_confirmation"] = listOf("Powtórzenie hasła jest wymagane.")
        } else if (state.password != state.passwordConfirmation) {
            errors["password_confirmation"] = listOf("Hasła nie są identyczne.")
        }

        if (errors.isNotEmpty()) {
            _uiState.update { it.copy(fieldErrors = errors, successMessage = null) }
            return Job().apply { complete() }
        }

        _uiState.update {
            it.copy(isLoading = true, errorMessage = null, fieldErrors = emptyMap(), successMessage = null)
        }
        return viewModelScope.launch(dispatcher) {
            try {
                val response = authRepository.resetPassword(
                    ResetPasswordRequestDto(
                        email = email,
                        token = state.token.trim(),
                        password = state.password,
                        password_confirmation = state.passwordConfirmation
                    )
                )
                _uiState.update {
                    it.copy(isLoading = false, successMessage = response.message)
                }
            } catch (exception: ApiException.Validation) {
                applyValidationError(exception)
            } catch (exception: Exception) {
                applyUnexpectedError(exception, "Nie udało się zmienić hasła.")
            }
        }
    }

    private fun updateField(
        field: String,
        transform: (PasswordResetUiState) -> PasswordResetUiState
    ) {
        _uiState.update { current ->
            transform(current).copy(
                fieldErrors = current.fieldErrors - field,
                errorMessage = null,
                successMessage = null
            )
        }
    }

    private fun applyValidationError(exception: ApiException.Validation) {
        _uiState.update {
            it.copy(
                isLoading = false,
                fieldErrors = exception.fieldErrors,
                errorMessage = exception.message
            )
        }
    }

    private fun applyUnexpectedError(exception: Exception, fallback: String) {
        _uiState.update {
            it.copy(
                isLoading = false,
                errorMessage = exception.message ?: fallback
            )
        }
    }

    private fun validateEmail(email: String): Map<String, List<String>> {
        return when {
            email.isBlank() -> mapOf("email" to listOf("Adres e-mail jest wymagany."))
            !EMAIL_REGEX.matches(email) -> mapOf("email" to listOf("Wprowadź prawidłowy adres e-mail."))
            else -> emptyMap()
        }
    }

    companion object {
        private val EMAIL_REGEX = "^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$".toRegex()

        fun provideFactory(
            authRepository: AuthRepository,
            initialEmail: String = "",
            initialToken: String = ""
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                return PasswordResetViewModel(
                    authRepository = authRepository,
                    initialEmail = initialEmail,
                    initialToken = initialToken
                ) as T
            }
        }
    }
}
