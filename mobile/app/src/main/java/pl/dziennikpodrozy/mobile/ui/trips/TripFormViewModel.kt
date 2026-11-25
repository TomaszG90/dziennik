package pl.dziennikpodrozy.mobile.ui.trips

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
import pl.dziennikpodrozy.mobile.data.remote.dto.CreateTripRequestDto
import pl.dziennikpodrozy.mobile.data.remote.dto.UpdateTripRequestDto
import pl.dziennikpodrozy.mobile.data.repository.TripRepository

data class TripFormUiState(
    val tripId: Int? = null,
    val name: String = "",
    val startDate: String = "",
    val endDate: String = "",
    val description: String = "",
    val isLoading: Boolean = false,
    val isInitialLoading: Boolean = false,
    val errorMessage: String? = null,
    val fieldErrors: Map<String, List<String>> = emptyMap(),
    val isSuccess: Boolean = false
)

class TripFormViewModel(
    private val tripRepository: TripRepository,
    private val initialTripId: Int? = null,
    private val dispatcher: CoroutineDispatcher = Dispatchers.Main
) : ViewModel() {

    private val _uiState = MutableStateFlow(TripFormUiState(tripId = initialTripId))
    val uiState: StateFlow<TripFormUiState> = _uiState.asStateFlow()

    init {
        if (initialTripId != null && initialTripId > 0) {
            loadTripForEdit(initialTripId)
        }
    }

    fun onNameChange(name: String) {
        _uiState.update { current ->
            current.copy(
                name = name,
                fieldErrors = current.fieldErrors - "name",
                errorMessage = null
            )
        }
    }

    fun onStartDateChange(startDate: String) {
        _uiState.update { current ->
            current.copy(
                startDate = startDate,
                fieldErrors = current.fieldErrors - "start_date",
                errorMessage = null
            )
        }
    }

    fun onEndDateChange(endDate: String) {
        _uiState.update { current ->
            current.copy(
                endDate = endDate,
                fieldErrors = current.fieldErrors - "end_date",
                errorMessage = null
            )
        }
    }

    fun onDescriptionChange(description: String) {
        _uiState.update { current ->
            current.copy(
                description = description,
                fieldErrors = current.fieldErrors - "description",
                errorMessage = null
            )
        }
    }

    fun loadTripForEdit(tripId: Int): Job {
        _uiState.update { it.copy(isInitialLoading = true, errorMessage = null) }

        return viewModelScope.launch(dispatcher) {
            try {
                val trip = tripRepository.getTrip(tripId)
                _uiState.update {
                    it.copy(
                        isInitialLoading = false,
                        tripId = trip.id,
                        name = trip.name,
                        startDate = trip.start_date,
                        endDate = trip.end_date.orEmpty(),
                        description = trip.description.orEmpty()
                    )
                }
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(
                        isInitialLoading = false,
                        errorMessage = exception.message ?: "Nie udało się pobrać danych podróży."
                    )
                }
            }
        }
    }

    fun saveTrip(): Job {
        val currentState = _uiState.value
        val localFieldErrors = mutableMapOf<String, List<String>>()

        val nameTrimmed = currentState.name.trim()
        if (nameTrimmed.isBlank()) {
            localFieldErrors["name"] = listOf("Nazwa podróży jest wymagana.")
        } else if (nameTrimmed.length > 255) {
            localFieldErrors["name"] = listOf("Nazwa nie może przekraczać 255 znaków.")
        }

        val startDateTrimmed = currentState.startDate.trim()
        if (startDateTrimmed.isBlank()) {
            localFieldErrors["start_date"] = listOf("Data rozpoczęcia jest wymagana.")
        }

        val endDateTrimmed = currentState.endDate.trim()
        if (startDateTrimmed.isNotBlank() && endDateTrimmed.isNotBlank()) {
            if (endDateTrimmed < startDateTrimmed) {
                localFieldErrors["end_date"] = listOf("Data zakończenia nie może być wcześniejsza niż data rozpoczęcia.")
            }
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
                val isEditMode = currentState.tripId != null && currentState.tripId > 0
                if (isEditMode) {
                    tripRepository.updateTrip(
                        id = currentState.tripId!!,
                        request = UpdateTripRequestDto(
                            name = nameTrimmed,
                            start_date = startDateTrimmed,
                            end_date = endDateTrimmed.ifBlank { null },
                            description = currentState.description.trim().ifBlank { null }
                        )
                    )
                } else {
                    tripRepository.createTrip(
                        request = CreateTripRequestDto(
                            name = nameTrimmed,
                            start_date = startDateTrimmed,
                            end_date = endDateTrimmed.ifBlank { null },
                            description = currentState.description.trim().ifBlank { null }
                        )
                    )
                }

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
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        errorMessage = exception.message ?: "Wystąpił nieoczekiwany błąd podczas zapisywania."
                    )
                }
            }
        }
    }

    fun resetSuccessState() {
        _uiState.update { it.copy(isSuccess = false) }
    }

    companion object {
        fun provideFactory(
            tripRepository: TripRepository,
            tripId: Int? = null
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                return TripFormViewModel(tripRepository, tripId) as T
            }
        }
    }
}
