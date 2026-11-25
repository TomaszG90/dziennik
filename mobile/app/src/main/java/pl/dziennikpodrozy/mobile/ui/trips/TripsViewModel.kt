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
import pl.dziennikpodrozy.mobile.data.remote.dto.TripDto
import pl.dziennikpodrozy.mobile.data.repository.TripRepository

data class TripsUiState(
    val isLoading: Boolean = false,
    val trips: List<TripDto> = emptyList(),
    val isEmpty: Boolean = false,
    val errorMessage: String? = null,
    val deletingTripId: Int? = null,
    val deleteError: String? = null
)

class TripsViewModel(
    private val tripRepository: TripRepository,
    private val dispatcher: CoroutineDispatcher = Dispatchers.Main
) : ViewModel() {

    private val _uiState = MutableStateFlow(TripsUiState(isLoading = true))
    val uiState: StateFlow<TripsUiState> = _uiState.asStateFlow()

    init {
        loadTrips()
    }

    fun loadTrips(): Job {
        _uiState.update {
            it.copy(
                isLoading = true,
                errorMessage = null
            )
        }

        return viewModelScope.launch(dispatcher) {
            try {
                val tripsList = tripRepository.getTrips()
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        trips = tripsList,
                        isEmpty = tripsList.isEmpty(),
                        errorMessage = null
                    )
                }
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        errorMessage = exception.message ?: "Nie udało się pobrać listy podróży."
                    )
                }
            }
        }
    }

    fun deleteTrip(tripId: Int): Job {
        _uiState.update { it.copy(deletingTripId = tripId, deleteError = null) }

        return viewModelScope.launch(dispatcher) {
            try {
                tripRepository.deleteTrip(tripId)
                val updatedTrips = _uiState.value.trips.filterNot { it.id == tripId }
                _uiState.update {
                    it.copy(
                        deletingTripId = null,
                        trips = updatedTrips,
                        isEmpty = updatedTrips.isEmpty()
                    )
                }
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(
                        deletingTripId = null,
                        deleteError = exception.message ?: "Nie udało się usunąć podróży."
                    )
                }
            }
        }
    }

    fun clearDeleteError() {
        _uiState.update { it.copy(deleteError = null) }
    }

    companion object {
        fun provideFactory(tripRepository: TripRepository): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T {
                    return TripsViewModel(tripRepository) as T
                }
            }
    }
}
