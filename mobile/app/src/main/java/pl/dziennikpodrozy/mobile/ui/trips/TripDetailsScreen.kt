package pl.dziennikpodrozy.mobile.ui.trips

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TripDetailsScreen(
    viewModel: TripDetailsViewModel,
    onNavigateBack: () -> Unit,
    onAddPlace: () -> Unit,
    onEditPlace: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val trip = state.trip
    val errorMessage = state.errorMessage
    var pendingDeleteId by rememberSaveable { mutableStateOf<Int?>(null) }
    val pendingDelete = state.places.firstOrNull { it.id == pendingDeleteId }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Szczegóły podróży") },
                navigationIcon = {
                    TextButton(onClick = onNavigateBack) {
                        Text("← Wstecz", color = MaterialTheme.colorScheme.onPrimary)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary
                )
            )
        },
        floatingActionButton = {
            if (trip != null && !state.isChangingPlaces) {
                ExtendedFloatingActionButton(
                    onClick = onAddPlace,
                    containerColor = MaterialTheme.colorScheme.primary,
                    contentColor = MaterialTheme.colorScheme.onPrimary
                ) {
                    Text("Dodaj miejsce")
                }
            }
        },
        modifier = modifier
    ) { innerPadding ->
        Box(Modifier.fillMaxSize().padding(innerPadding)) {
            when {
                state.isLoading -> CircularProgressIndicator(Modifier.align(Alignment.Center))
                errorMessage != null -> TripDetailsError(errorMessage, viewModel::loadDetails)
                trip != null -> LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 88.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    item {
                        DetailsCard {
                            Text(trip.name, style = MaterialTheme.typography.titleLarge)
                            TripDateAndDescription(trip)
                        }
                    }
                    item {
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("Miejsca", style = MaterialTheme.typography.titleLarge)
                            if (state.isReordering) {
                                CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
                            }
                        }
                    }
                    if (state.places.isEmpty()) {
                        item {
                            DetailsCard {
                                Text(
                                    "Ta podróż nie ma jeszcze zapisanych miejsc.",
                                    style = MaterialTheme.typography.bodyMedium
                                )
                            }
                        }
                    }
                    itemsIndexed(state.places, key = { _, place -> place.id }) { index, place ->
                        DetailsCard {
                            Text(place.name, style = MaterialTheme.typography.titleMedium)
                            Text(
                                listOfNotNull(place.latitude, place.longitude, place.countryCode)
                                    .joinToString(" · "),
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                            FlowRow(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.End
                            ) {
                                if (state.places.size > 1) {
                                    TextButton(
                                        onClick = { viewModel.movePlaceUp(place.id) },
                                        enabled = !state.isChangingPlaces && index > 0
                                    ) {
                                        Text("W górę")
                                    }
                                    TextButton(
                                        onClick = { viewModel.movePlaceDown(place.id) },
                                        enabled = !state.isChangingPlaces && index < state.places.lastIndex
                                    ) {
                                        Text("W dół")
                                    }
                                }
                                TextButton(
                                    onClick = { onEditPlace(place.id) },
                                    enabled = !state.isChangingPlaces
                                ) {
                                    Text("Edytuj")
                                }
                                TextButton(
                                    onClick = { pendingDeleteId = place.id },
                                    enabled = !state.isChangingPlaces
                                ) {
                                    if (state.deletingPlaceId == place.id) {
                                        CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp)
                                    } else {
                                        Text("Usuń", color = MaterialTheme.colorScheme.error)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (pendingDelete != null) {
        AlertDialog(
            onDismissRequest = { pendingDeleteId = null },
            title = { Text("Usuwanie miejsca") },
            text = { Text("Czy na pewno chcesz usunąć miejsce „${pendingDelete.name}”?") },
            confirmButton = {
                TextButton(onClick = {
                    pendingDeleteId = null
                    viewModel.deletePlace(pendingDelete.id)
                }) {
                    Text("Usuń", color = MaterialTheme.colorScheme.error)
                }
            },
            dismissButton = {
                TextButton(onClick = { pendingDeleteId = null }) { Text("Anuluj") }
            }
        )
    }

    state.deleteError?.let { message ->
        AlertDialog(
            onDismissRequest = viewModel::clearDeleteError,
            title = { Text("Błąd usuwania") },
            text = { Text(message) },
            confirmButton = {
                TextButton(onClick = viewModel::clearDeleteError) { Text("OK") }
            }
        )
    }

    state.reorderError?.let { message ->
        AlertDialog(
            onDismissRequest = viewModel::clearReorderError,
            title = { Text("Błąd zmiany kolejności") },
            text = { Text(message) },
            confirmButton = {
                TextButton(onClick = viewModel::clearReorderError) { Text("OK") }
            }
        )
    }
}

@Composable
private fun DetailsCard(content: @Composable () -> Unit) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            content()
        }
    }
}

@Composable
internal fun TripDetailsError(message: String, onRetry: () -> Unit) {
    Column(
        modifier = Modifier.fillMaxSize().padding(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp, Alignment.CenterVertically),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Card(
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.errorContainer,
                contentColor = MaterialTheme.colorScheme.onErrorContainer
            ),
            modifier = Modifier.fillMaxWidth()
        ) {
            Text(message, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(16.dp))
        }
        Button(onClick = onRetry) {
            Text("Spróbuj ponownie")
        }
    }
}
