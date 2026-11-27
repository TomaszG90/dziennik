package pl.dziennikpodrozy.mobile

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import pl.dziennikpodrozy.mobile.data.DefaultAppContainer
import pl.dziennikpodrozy.mobile.ui.navigation.AppNavGraph
import pl.dziennikpodrozy.mobile.ui.theme.DziennikPodrozyTheme

class MainActivity : ComponentActivity() {
    private val appContainer by lazy { DefaultAppContainer(applicationContext) }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            DziennikPodrozyTheme {
                AppNavGraph(
                    authRepository = appContainer.authRepository,
                    tripRepository = appContainer.tripRepository,
                    placeRepository = appContainer.placeRepository,
                    apiConfig = appContainer.apiConfig
                )
            }
        }
    }
}
