package pl.dziennikpodrozy.mobile.ui.trips

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle

@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun PlaceFormScreen(
    viewModel: PlaceFormViewModel,
    onNavigateBack: () -> Unit,
    onSaveSuccess: () -> Unit
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val loadError = state.loadError

    BackHandler(enabled = state.isSaving) {}
    LaunchedEffect(state.isSaved) {
        if (state.isSaved) onSaveSuccess()
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(if (state.isEditing) "Edycja miejsca" else "Nowe miejsce") },
                navigationIcon = {
                    TextButton(onClick = onNavigateBack, enabled = !state.isSaving) {
                        Text("← Wstecz", color = MaterialTheme.colorScheme.onPrimary)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary
                )
            )
        }
    ) { innerPadding ->
        Box(Modifier.fillMaxSize().padding(innerPadding)) {
            when {
                state.isLoading -> CircularProgressIndicator(Modifier.align(Alignment.Center))
                loadError != null -> TripDetailsError(loadError, viewModel::loadPlace)
                else -> Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState())
                        .padding(24.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    state.saveError?.let { message ->
                        Card(
                            colors = CardDefaults.cardColors(
                                containerColor = MaterialTheme.colorScheme.errorContainer,
                                contentColor = MaterialTheme.colorScheme.onErrorContainer
                            ),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Text(message, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(16.dp))
                        }
                    }

                    PlaceField.entries.forEach { field ->
                        val error = state.fieldErrors[field.apiKey]
                        OutlinedTextField(
                            value = state.values[field].orEmpty(),
                            onValueChange = { viewModel.changeField(field, it) },
                            label = { Text(field.label) },
                            keyboardOptions = KeyboardOptions(
                                imeAction = if (field == PlaceField.CountryCode) ImeAction.Done else ImeAction.Next
                            ),
                            enabled = !state.isSaving,
                            singleLine = true,
                            isError = error != null,
                            supportingText = error?.let { { Text(it) } },
                            modifier = Modifier.fillMaxWidth()
                        )
                    }

                    Button(
                        onClick = viewModel::savePlace,
                        enabled = !state.isSaving && !state.isSaved,
                        modifier = Modifier.fillMaxWidth().height(50.dp)
                    ) {
                        if (state.isSaving) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(24.dp),
                                color = MaterialTheme.colorScheme.onPrimary,
                                strokeWidth = 2.dp
                            )
                        } else {
                            Text(if (state.isEditing) "Zapisz zmiany" else "Dodaj miejsce")
                        }
                    }
                }
            }
        }
    }
}
