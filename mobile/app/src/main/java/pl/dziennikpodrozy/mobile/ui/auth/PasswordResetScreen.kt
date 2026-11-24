package pl.dziennikpodrozy.mobile.ui.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
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
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle

@Composable
@OptIn(ExperimentalMaterial3Api::class)
fun ForgotPasswordScreen(
    viewModel: PasswordResetViewModel,
    onNavigateToReset: (String) -> Unit,
    onNavigateToLogin: () -> Unit,
    modifier: Modifier = Modifier
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    PasswordResetForm(
        title = "Odzyskiwanie hasła",
        description = "Podaj adres e-mail, a wyślemy link do zmiany hasła.",
        uiState = uiState,
        onEmailChange = viewModel::onEmailChange,
        onSubmit = viewModel::requestResetLink,
        submitLabel = "Wyślij link",
        onNavigateToLogin = onNavigateToLogin,
        modifier = modifier
    ) {
        if (uiState.successMessage != null) {
            TextButton(onClick = { onNavigateToReset(uiState.email) }) {
                Text("Mam token — ustaw nowe hasło")
            }
        }
    }
}

@Composable
@OptIn(ExperimentalMaterial3Api::class)
fun ResetPasswordScreen(
    viewModel: PasswordResetViewModel,
    onNavigateToLogin: () -> Unit,
    modifier: Modifier = Modifier
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    var isPasswordVisible by remember { mutableStateOf(false) }

    PasswordResetForm(
        title = "Ustaw nowe hasło",
        description = "Wpisz adres e-mail, token z wiadomości oraz nowe hasło.",
        uiState = uiState,
        onEmailChange = viewModel::onEmailChange,
        onSubmit = viewModel::resetPassword,
        submitLabel = "Zmień hasło",
        onNavigateToLogin = onNavigateToLogin,
        modifier = modifier,
        extraFields = {
            ResetTextField(
                value = uiState.token,
                onValueChange = viewModel::onTokenChange,
                label = "Token z wiadomości e-mail",
                error = uiState.fieldErrors["token"]?.firstOrNull(),
                keyboardType = KeyboardType.Ascii,
                imeAction = ImeAction.Next
            )
            Spacer(modifier = Modifier.height(16.dp))
            ResetTextField(
                value = uiState.password,
                onValueChange = viewModel::onPasswordChange,
                label = "Nowe hasło (min. 8 znaków)",
                error = uiState.fieldErrors["password"]?.firstOrNull(),
                keyboardType = KeyboardType.Password,
                imeAction = ImeAction.Next,
                visualTransformation = if (isPasswordVisible) {
                    VisualTransformation.None
                } else {
                    PasswordVisualTransformation()
                },
                trailingAction = {
                    TextButton(onClick = { isPasswordVisible = !isPasswordVisible }) {
                        Text(if (isPasswordVisible) "Ukryj" else "Pokaż")
                    }
                }
            )
            Spacer(modifier = Modifier.height(16.dp))
            ResetTextField(
                value = uiState.passwordConfirmation,
                onValueChange = viewModel::onPasswordConfirmationChange,
                label = "Powtórz nowe hasło",
                error = uiState.fieldErrors["password_confirmation"]?.firstOrNull(),
                keyboardType = KeyboardType.Password,
                imeAction = ImeAction.Done,
                visualTransformation = PasswordVisualTransformation()
            )
        }
    )
}

@Composable
@OptIn(ExperimentalMaterial3Api::class)
private fun PasswordResetForm(
    title: String,
    description: String,
    uiState: PasswordResetUiState,
    onEmailChange: (String) -> Unit,
    onSubmit: () -> Unit,
    submitLabel: String,
    onNavigateToLogin: () -> Unit,
    modifier: Modifier = Modifier,
    extraFields: @Composable () -> Unit = {},
    successActions: @Composable () -> Unit = {}
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(title) },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary
                )
            )
        },
        modifier = modifier
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(description, style = MaterialTheme.typography.bodyMedium)
            Spacer(modifier = Modifier.height(24.dp))

            uiState.errorMessage?.let { message ->
                MessageCard(message = message, isError = true)
                Spacer(modifier = Modifier.height(16.dp))
            }
            uiState.successMessage?.let { message ->
                MessageCard(message = message, isError = false)
                Spacer(modifier = Modifier.height(16.dp))
            }

            ResetTextField(
                value = uiState.email,
                onValueChange = onEmailChange,
                label = "Adres e-mail",
                error = uiState.fieldErrors["email"]?.firstOrNull(),
                keyboardType = KeyboardType.Email,
                imeAction = ImeAction.Next
            )
            Spacer(modifier = Modifier.height(16.dp))
            extraFields()

            Button(
                onClick = onSubmit,
                enabled = !uiState.isLoading,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(50.dp)
            ) {
                if (uiState.isLoading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(24.dp),
                        color = MaterialTheme.colorScheme.onPrimary,
                        strokeWidth = 2.dp
                    )
                } else {
                    Text(submitLabel)
                }
            }

            successActions()
            TextButton(onClick = onNavigateToLogin) {
                Text("Wróć do logowania")
            }
        }
    }
}

@Composable
private fun ResetTextField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    error: String?,
    keyboardType: KeyboardType,
    imeAction: ImeAction,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    trailingAction: @Composable (() -> Unit)? = null
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        singleLine = true,
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType, imeAction = imeAction),
        visualTransformation = visualTransformation,
        trailingIcon = trailingAction,
        isError = error != null,
        supportingText = error?.let { { Text(it) } },
        modifier = Modifier.fillMaxWidth()
    )
}

@Composable
private fun MessageCard(message: String, isError: Boolean) {
    Card(
        colors = CardDefaults.cardColors(
            containerColor = if (isError) {
                MaterialTheme.colorScheme.errorContainer
            } else {
                MaterialTheme.colorScheme.primaryContainer
            },
            contentColor = if (isError) {
                MaterialTheme.colorScheme.onErrorContainer
            } else {
                MaterialTheme.colorScheme.onPrimaryContainer
            }
        ),
        modifier = Modifier.fillMaxWidth()
    ) {
        Text(message, modifier = Modifier.padding(16.dp))
    }
}
