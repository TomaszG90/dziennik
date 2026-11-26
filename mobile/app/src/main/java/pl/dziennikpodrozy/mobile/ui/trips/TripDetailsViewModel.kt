package pl.dziennikpodrozy.mobile.ui.trips

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import pl.dziennikpodrozy.mobile.data.remote.dto.PlaceDto
import pl.dziennikpodrozy.mobile.data.remote.dto.TripDto
import pl.dziennikpodrozy.mobile.data.repository.PlaceRepository
import pl.dziennikpodrozy.mobile.data.repository.TripRepository

data class TripDetailsUiState(
    val isLoading: Boolean = true,
    val trip: TripDto? = null,
    val places: List<PlaceDto> = emptyList(),
    val errorMessage: String? = null,
    val deletingPlaceId: Int? = null,
    val deleteError: String? = null
)

class TripDetailsViewModel(
    private val tripId: Int,
    private val tripRepository: TripRepository,
    private val placeRepository: PlaceRepository
) : ViewModel() {
    private val _uiState = MutableStateFlow(TripDetailsUiState())
    val uiState = _uiState.asStateFlow()
    private var loadJob: Job? = null

    init {
        loadDetails()
    }

    fun loadDetails() {
        if (_uiState.value.deletingPlaceId != null) return
        loadJob?.cancel()
        _uiState.value = TripDetailsUiState()
        loadJob = viewModelScope.launch {
            try {
                val trip = tripRepository.getTrip(tripId)
                val places = placeRepository.getPlaces(tripId)
                _uiState.value = TripDetailsUiState(
                    isLoading = false,
                    trip = trip,
                    places = places.sortedBy { it.position }
                )
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                _uiState.value = TripDetailsUiState(
                    isLoading = false,
                    errorMessage = placeError(exception, "Nie udało się pobrać szczegółów podróży.")
                )
            }
        }
    }

    fun deletePlace(placeId: Int) {
        val state = _uiState.value
        if (state.isLoading || state.deletingPlaceId != null || state.places.none { it.id == placeId }) return

        _uiState.update { it.copy(deletingPlaceId = placeId, deleteError = null) }
        viewModelScope.launch {
            try {
                placeRepository.deletePlace(tripId, placeId)
                _uiState.update {
                    it.copy(deletingPlaceId = null, places = it.places.filterNot { place -> place.id == placeId })
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(deletingPlaceId = null, deleteError = placeError(exception, "Nie udało się usunąć miejsca."))
                }
            }
        }
    }

    fun clearDeleteError() {
        _uiState.update { it.copy(deleteError = null) }
    }

    companion object {
        fun provideFactory(
            tripId: Int,
            tripRepository: TripRepository,
            placeRepository: PlaceRepository
        ): ViewModelProvider.Factory = object : ViewModelProvider.Factory {
            @Suppress("UNCHECKED_CAST")
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                return TripDetailsViewModel(tripId, tripRepository, placeRepository) as T
            }
        }
    }
}
