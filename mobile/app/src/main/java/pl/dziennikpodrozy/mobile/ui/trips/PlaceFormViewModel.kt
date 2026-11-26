package pl.dziennikpodrozy.mobile.ui.trips

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import java.io.IOException
import java.util.Locale
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonPrimitive
import pl.dziennikpodrozy.mobile.data.remote.ApiException
import pl.dziennikpodrozy.mobile.data.remote.dto.CreatePlaceRequestDto
import pl.dziennikpodrozy.mobile.data.remote.dto.UpdatePlaceRequestDto
import pl.dziennikpodrozy.mobile.data.repository.PlaceRepository

internal enum class PlaceField(val apiKey: String, val label: String) {
    Name("name", "Nazwa miejsca"),
    Latitude("latitude", "Szerokość geograficzna"),
    Longitude("longitude", "Długość geograficzna"),
    CountryCode("country_code", "Kod kraju (opcjonalnie)")
}

internal data class PlaceFormUiState(
    val isEditing: Boolean,
    val values: Map<PlaceField, String> = emptyMap(),
    val isLoading: Boolean = isEditing,
    val loadError: String? = null,
    val isSaving: Boolean = false,
    val isSaved: Boolean = false,
    val fieldErrors: Map<String, String> = emptyMap(),
    val saveError: String? = null
)

internal class PlaceFormViewModel(
    private val tripId: Int,
    private val placeId: Int?,
    private val repository: PlaceRepository
) : ViewModel() {
    private val _uiState = MutableStateFlow(PlaceFormUiState(isEditing = placeId != null))
    val uiState = _uiState.asStateFlow()

    init {
        loadPlace()
    }

    fun loadPlace() {
        val id = placeId ?: return
        _uiState.update { it.copy(isLoading = true, loadError = null) }
        viewModelScope.launch {
            try {
                val place = repository.getPlace(tripId, id)
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        values = mapOf(
                            PlaceField.Name to place.name,
                            PlaceField.Latitude to place.latitude,
                            PlaceField.Longitude to place.longitude,
                            PlaceField.CountryCode to place.countryCode.orEmpty()
                        )
                    )
                }
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(isLoading = false, loadError = placeError(exception, "Nie udało się pobrać miejsca."))
                }
            }
        }
    }

    fun changeField(field: PlaceField, value: String) {
        _uiState.update {
            it.copy(
                values = it.values + (field to value),
                fieldErrors = it.fieldErrors - field.apiKey
            )
        }
    }

    fun savePlace() {
        val state = _uiState.value
        if (state.isLoading || state.isSaving || state.isSaved || state.loadError != null) return

        val name = state.values[PlaceField.Name].orEmpty().trim()
        val latitude = state.values[PlaceField.Latitude]?.trim()?.replace(',', '.')?.toDoubleOrNull()
        val longitude = state.values[PlaceField.Longitude]?.trim()?.replace(',', '.')?.toDoubleOrNull()
        val countryCode = state.values[PlaceField.CountryCode].orEmpty().trim().uppercase(Locale.ROOT)
        val errors = buildMap {
            if (name.isEmpty() || name.length > 255) {
                put(PlaceField.Name.apiKey, "Podaj nazwę od 1 do 255 znaków.")
            }
            if (latitude == null || latitude !in -90.0..90.0) {
                put(PlaceField.Latitude.apiKey, "Podaj szerokość od −90 do 90.")
            }
            if (longitude == null || longitude !in -180.0..180.0) {
                put(PlaceField.Longitude.apiKey, "Podaj długość od −180 do 180.")
            }
            if (countryCode.isNotEmpty() && !countryCode.matches(Regex("[A-Z]{2}"))) {
                put(PlaceField.CountryCode.apiKey, "Podaj dwuliterowy kod kraju, np. PL.")
            }
        }
        if (errors.isNotEmpty() || latitude == null || longitude == null) {
            _uiState.update { it.copy(fieldErrors = errors, saveError = null) }
            return
        }

        _uiState.update { it.copy(isSaving = true, fieldErrors = emptyMap(), saveError = null) }
        val country = countryCode.ifEmpty { null }
        viewModelScope.launch {
            try {
                if (placeId == null) {
                    repository.createPlace(tripId, CreatePlaceRequestDto(name, latitude, longitude, country))
                } else {
                    repository.updatePlace(
                        tripId,
                        placeId,
                        UpdatePlaceRequestDto(name, latitude, longitude, country?.let(::JsonPrimitive) ?: JsonNull)
                    )
                }
                _uiState.update { it.copy(isSaving = false, isSaved = true) }
            } catch (exception: CancellationException) {
                throw exception
            } catch (exception: ApiException.Validation) {
                val fieldErrors = PlaceField.entries.mapNotNull { field ->
                    exception.fieldErrors[field.apiKey]?.firstOrNull()?.let { field.apiKey to it }
                }.toMap()
                _uiState.update {
                    it.copy(
                        isSaving = false,
                        fieldErrors = fieldErrors,
                        saveError = exception.message.takeIf { fieldErrors.isEmpty() }
                    )
                }
            } catch (exception: Exception) {
                _uiState.update {
                    it.copy(isSaving = false, saveError = placeError(exception, "Nie udało się zapisać miejsca."))
                }
            }
        }
    }

    companion object {
        fun provideFactory(tripId: Int, placeId: Int?, repository: PlaceRepository): ViewModelProvider.Factory =
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T {
                    return PlaceFormViewModel(tripId, placeId, repository) as T
                }
            }
    }
}

internal fun placeError(exception: Exception, fallback: String): String = when (exception) {
    is IOException -> "Nie udało się połączyć z serwerem. Sprawdź połączenie z internetem."
    is ApiException -> exception.message ?: fallback
    else -> fallback
}
