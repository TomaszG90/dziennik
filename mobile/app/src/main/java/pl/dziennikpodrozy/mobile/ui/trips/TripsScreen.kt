package pl.dziennikpodrozy.mobile.ui.trips

import android.content.res.Configuration
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Info
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import pl.dziennikpodrozy.mobile.data.remote.dto.TripDto
import pl.dziennikpodrozy.mobile.ui.theme.DziennikPodrozyTheme

@Composable
fun TripsScreen(
    viewModel: TripsViewModel,
    onCreateTripClick: () -> Unit,
    onEditTripClick: (Int) -> Unit,
    onTripDetailsClick: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    TripsContent(
        uiState = uiState,
        onCreateTripClick = onCreateTripClick,
        onEditTripClick = onEditTripClick,
        onTripDetailsClick = onTripDetailsClick,
        onDeleteTripConfirm = viewModel::deleteTrip,
        onRetryClick = viewModel::loadTrips,
        onClearDeleteError = viewModel::clearDeleteError,
        modifier = modifier
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TripsContent(
    uiState: TripsUiState,
    onCreateTripClick: () -> Unit,
    onEditTripClick: (Int) -> Unit,
    onTripDetailsClick: (Int) -> Unit,
    onDeleteTripConfirm: (Int) -> Unit,
    onRetryClick: () -> Unit,
    onClearDeleteError: () -> Unit,
    modifier: Modifier = Modifier
) {
    var tripToDelete by remember { mutableStateOf<TripDto?>(null) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Moje podróże") },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary
                )
            )
        },
        floatingActionButton = {
            FloatingActionButton(
                onClick = onCreateTripClick,
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = MaterialTheme.colorScheme.onPrimary
            ) {
                Icon(
                    imageVector = Icons.Default.Add,
                    contentDescription = "Dodaj nową podróż"
                )
            }
        },
        modifier = modifier
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            when {
                uiState.isLoading -> {
                    CircularProgressIndicator(
                        modifier = Modifier.align(Alignment.Center)
                    )
                }

                uiState.errorMessage != null -> {
                    Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(24.dp),
                        verticalArrangement = Arrangement.Center,
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Card(
                            colors = CardDefaults.cardColors(
                                containerColor = MaterialTheme.colorScheme.errorContainer,
                                contentColor = MaterialTheme.colorScheme.onErrorContainer
                            ),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text(
                                text = uiState.errorMessage,
                                style = MaterialTheme.typography.bodyMedium,
                                modifier = Modifier.padding(16.dp)
                            )
                        }
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = onRetryClick) {
                            Text("Spróbuj ponownie")
                        }
                    }
                }

                uiState.isEmpty -> {
                    Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(24.dp),
                        verticalArrangement = Arrangement.Center,
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Card(
                            colors = CardDefaults.cardColors(
                                containerColor = MaterialTheme.colorScheme.surfaceVariant
                            ),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Column(
                                modifier = Modifier.padding(24.dp),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Info,
                                    contentDescription = null,
                                    modifier = Modifier.size(48.dp),
                                    tint = MaterialTheme.colorScheme.primary
                                )
                                Spacer(modifier = Modifier.height(16.dp))
                                Text(
                                    text = "Brak utworzonych podróży",
                                    style = MaterialTheme.typography.titleMedium
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = "Nie masz jeszcze żadnych zapisanych podróży. Dodaj swoją pierwszą przygodę!",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                                Spacer(modifier = Modifier.height(20.dp))
                                Button(onClick = onCreateTripClick) {
                                    Text("Dodaj pierwszą podróż")
                                }
                            }
                        }
                    }
                }

                else -> {
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(horizontal = 16.dp, vertical = 12.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        items(uiState.trips, key = { it.id }) { trip ->
                            TripItemCard(
                                trip = trip,
                                isDeleting = uiState.deletingTripId == trip.id,
                                onTripClick = { onTripDetailsClick(trip.id) },
                                onEditClick = { onEditTripClick(trip.id) },
                                onDeleteClick = { tripToDelete = trip }
                            )
                        }
                    }
                }
            }
        }
    }

    tripToDelete?.let { trip ->
        AlertDialog(
            onDismissRequest = { tripToDelete = null },
            title = { Text("Usuwanie podróży") },
            text = { Text("Czy na pewno chcesz usunąć podróż „${trip.name}”? Ta operacja jest nieodwracalna.") },
            confirmButton = {
                TextButton(
                    onClick = {
                        val id = trip.id
                        tripToDelete = null
                        onDeleteTripConfirm(id)
                    }
                ) {
                    Text("Usuń", color = MaterialTheme.colorScheme.error)
                }
            },
            dismissButton = {
                TextButton(onClick = { tripToDelete = null }) {
                    Text("Anuluj")
                }
            }
        )
    }

    if (uiState.deleteError != null) {
        AlertDialog(
            onDismissRequest = onClearDeleteError,
            title = { Text("Błąd usuwania") },
            text = { Text(uiState.deleteError) },
            confirmButton = {
                TextButton(onClick = onClearDeleteError) {
                    Text("OK")
                }
            }
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TripItemCard(
    trip: TripDto,
    isDeleting: Boolean,
    onTripClick: () -> Unit,
    onEditClick: () -> Unit,
    onDeleteClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        onClick = onTripClick,
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant
        ),
        modifier = modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = trip.name,
                    style = MaterialTheme.typography.titleLarge,
                    modifier = Modifier.weight(1f)
                )

                Row {
                    IconButton(
                        onClick = onEditClick,
                        enabled = !isDeleting
                    ) {
                        Icon(
                            imageVector = Icons.Default.Edit,
                            contentDescription = "Edytuj podróż",
                            tint = MaterialTheme.colorScheme.primary
                        )
                    }
                    IconButton(
                        onClick = onDeleteClick,
                        enabled = !isDeleting
                    ) {
                        if (isDeleting) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                strokeWidth = 2.dp
                            )
                        } else {
                            Icon(
                                imageVector = Icons.Default.Delete,
                                contentDescription = "Usuń podróż",
                                tint = MaterialTheme.colorScheme.error
                            )
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(6.dp))

            val dateText = if (trip.end_date.isNullOrBlank()) {
                "Od: ${trip.start_date}"
            } else {
                "${trip.start_date} — ${trip.end_date}"
            }

            Text(
                text = dateText,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.primary
            )

            if (!trip.description.isNullOrBlank()) {
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = trip.description,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

@Preview(name = "Light Mode", showBackground = true)
@Preview(name = "Dark Mode", uiMode = Configuration.UI_MODE_NIGHT_YES, showBackground = true)
@Composable
private fun TripsScreenPreview() {
    DziennikPodrozyTheme {
        TripsContent(
            uiState = TripsUiState(
                trips = listOf(
                    TripDto(
                        id = 1,
                        name = "Wakacje w Austrii",
                        start_date = "2026-10-29",
                        end_date = "2026-11-05",
                        description = "Wyjazd do Wiednia i Salzburga"
                    ),
                    TripDto(
                        id = 2,
                        name = "Weekend w Krakowie",
                        start_date = "2026-12-01",
                        end_date = null,
                        description = null
                    )
                )
            ),
            onCreateTripClick = {},
            onEditTripClick = {},
            onTripDetailsClick = {},
            onDeleteTripConfirm = {},
            onRetryClick = {},
            onClearDeleteError = {}
        )
    }
}
