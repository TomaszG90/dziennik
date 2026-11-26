package pl.dziennikpodrozy.mobile.ui.trips

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import java.io.IOException
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.remote.dto.PlaceDto
import pl.dziennikpodrozy.mobile.data.remote.dto.TripDto
import pl.dziennikpodrozy.mobile.data.repository.PlaceRepository
import pl.dziennikpodrozy.mobile.data.repository.TripRepository

data class TripDetailsUiState(
    val isLoading: Boolean = true,
    val trip: TripDto? = null,
    val places: List<PlaceDto> = emptyList(),
    val errorMessage: String? = null
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
                val message = when (exception) {
                    is IOException -> "Nie udało się połączyć z serwerem. Sprawdź połączenie z internetem."
                    is ApiException -> exception.message
                    else -> null
                }
                _uiState.value = TripDetailsUiState(
                    isLoading = false,
                    errorMessage = message ?: "Nie udało się pobrać szczegółów podróży."
                )
            }
        }
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
