package pl.dziennikpodrozy.mobile.data

import android.content.Context
import io.ktor.client.HttpClient
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.data.remote.ApiClient
import pl.dziennikpodrozy.mobile.data.repository.AuthRepository
import pl.dziennikpodrozy.mobile.data.repository.TripRepository
import pl.dziennikpodrozy.mobile.data.session.SecureTokenStorage
import pl.dziennikpodrozy.mobile.data.session.SessionManager
import pl.dziennikpodrozy.mobile.data.session.SessionStore

interface AppContainer {
    val apiConfig: ApiConfig
    val httpClient: HttpClient
    val sessionStore: SessionStore
    val authRepository: AuthRepository
    val tripRepository: TripRepository
}

class DefaultAppContainer(context: Context) : AppContainer {
    override val apiConfig: ApiConfig by lazy { ApiConfig() }
    override val httpClient: HttpClient by lazy { ApiClient.create() }
    override val sessionStore: SessionStore by lazy {
        SessionManager(SecureTokenStorage(context.applicationContext))
    }
    override val authRepository: AuthRepository by lazy {
        AuthRepository(httpClient, apiConfig, sessionStore)
    }
    override val tripRepository: TripRepository by lazy {
        TripRepository(httpClient, apiConfig, sessionStore)
    }
}
