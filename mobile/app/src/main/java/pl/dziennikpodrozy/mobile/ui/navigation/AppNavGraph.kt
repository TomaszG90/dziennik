package pl.dziennikpodrozy.mobile.ui.navigation

import android.net.Uri
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository
import pl.dziennikpodrozy.mobile.ui.auth.LoginScreen
import pl.dziennikpodrozy.mobile.ui.auth.LoginViewModel
import pl.dziennikpodrozy.mobile.ui.auth.ForgotPasswordScreen
import pl.dziennikpodrozy.mobile.ui.auth.PasswordResetViewModel
import pl.dziennikpodrozy.mobile.ui.auth.RegisterScreen
import pl.dziennikpodrozy.mobile.ui.auth.RegisterViewModel
import pl.dziennikpodrozy.mobile.ui.auth.ResetPasswordScreen
import pl.dziennikpodrozy.mobile.ui.home.HomeScreen
import pl.dziennikpodrozy.mobile.ui.home.HomeViewModel

sealed class Screen(val route: String) {
    data object Login : Screen("login")
    data object Register : Screen("register")
    data object ForgotPassword : Screen("forgot-password")
    data object ResetPassword : Screen("reset-password?email={email}&token={token}") {
        fun createRoute(email: String = "", token: String = ""): String =
            "reset-password?email=${Uri.encode(email)}&token=${Uri.encode(token)}"
    }
    data object Home : Screen("home")
    data object About : Screen("about")
}

@Composable
fun AppNavGraph(
    authRepository: AuthRepository,
    apiConfig: ApiConfig = ApiConfig(),
    navController: NavHostController = rememberNavController()
) {
    val startDestination = remember(authRepository) {
        if (authRepository.hasActiveSession()) Screen.Home.route else Screen.Login.route
    }

    NavHost(
        navController = navController,
        startDestination = startDestination
    ) {
        composable(Screen.Login.route) {
            val viewModel: LoginViewModel = viewModel(
                factory = LoginViewModel.provideFactory(authRepository)
            )
            LoginScreen(
                viewModel = viewModel,
                onNavigateToRegister = {
                    navController.navigate(Screen.Register.route)
                },
                onNavigateToForgotPassword = {
                    navController.navigate(Screen.ForgotPassword.route)
                },
                onLoginSuccess = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                        launchSingleTop = true
                    }
                }
            )
        }
        composable(Screen.ForgotPassword.route) {
            val viewModel: PasswordResetViewModel = viewModel(
                factory = PasswordResetViewModel.provideFactory(authRepository)
            )
            ForgotPasswordScreen(
                viewModel = viewModel,
                onNavigateToReset = { email ->
                    navController.navigate(Screen.ResetPassword.createRoute(email))
                },
                onNavigateToLogin = {
                    if (!navController.popBackStack(Screen.Login.route, inclusive = false)) {
                        navController.navigate(Screen.Login.route) {
                            popUpTo(Screen.ForgotPassword.route) { inclusive = true }
                            launchSingleTop = true
                        }
                    }
                }
            )
        }
        composable(
            route = Screen.ResetPassword.route,
            arguments = listOf(
                navArgument("email") {
                    type = NavType.StringType
                    defaultValue = ""
                },
                navArgument("token") {
                    type = NavType.StringType
                    defaultValue = ""
                }
            )
        ) { backStackEntry ->
            val email = backStackEntry.arguments?.getString("email").orEmpty()
            val token = backStackEntry.arguments?.getString("token").orEmpty()
            val viewModel: PasswordResetViewModel = viewModel(
                factory = PasswordResetViewModel.provideFactory(
                    authRepository = authRepository,
                    initialEmail = email,
                    initialToken = token
                )
            )
            ResetPasswordScreen(
                viewModel = viewModel,
                onNavigateToLogin = {
                    navController.navigate(Screen.Login.route) {
                        popUpTo(Screen.Login.route) { inclusive = false }
                        launchSingleTop = true
                    }
                }
            )
        }
        composable(Screen.Register.route) {
            val viewModel: RegisterViewModel = viewModel(
                factory = RegisterViewModel.provideFactory(authRepository)
            )
            RegisterScreen(
                viewModel = viewModel,
                onNavigateToLogin = {
                    if (!navController.popBackStack(Screen.Login.route, inclusive = false)) {
                        navController.navigate(Screen.Login.route) {
                            popUpTo(Screen.Register.route) { inclusive = true }
                            launchSingleTop = true
                        }
                    }
                }
            )
        }
        composable(Screen.Home.route) {
            val viewModel: HomeViewModel = viewModel(
                factory = HomeViewModel.provideFactory(apiConfig, authRepository)
            )
            HomeScreen(
                viewModel = viewModel,
                onNavigateToAbout = { navController.navigate(Screen.About.route) },
                onLoggedOut = {
                    navController.navigate(Screen.Login.route) {
                        popUpTo(navController.graph.id) { inclusive = true }
                        launchSingleTop = true
                    }
                }
            )
        }
        composable(Screen.About.route) {
            AboutScreen(
                onNavigateBack = { navController.popBackStack() }
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AboutScreen(
    onNavigateBack: () -> Unit,
    modifier: Modifier = Modifier
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("O aplikacji") },
                navigationIcon = {
                    TextButton(onClick = onNavigateBack) {
                        Text(
                            text = "← Wstecz",
                            color = MaterialTheme.colorScheme.onPrimary
                        )
                    }
                },
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
                .padding(24.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = "Dziennik Podróży — Android",
                style = MaterialTheme.typography.titleLarge
            )
            Spacer(modifier = Modifier.height(12.dp))
            Text(
                text = "Fundament aplikacji Jetpack Compose z Navigation i Material 3.",
                style = MaterialTheme.typography.bodyLarge
            )
            Spacer(modifier = Modifier.height(24.dp))
            Button(onClick = onNavigateBack) {
                Text(text = "Wróć do ekranu głównego")
            }
        }
    }
}
