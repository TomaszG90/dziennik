package pl.dziennikpodrozy.mobile

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import pl.dziennikpodrozy.mobile.ui.navigation.AppNavGraph
import pl.dziennikpodrozy.mobile.ui.theme.DziennikPodrozyTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            DziennikPodrozyTheme {
                AppNavGraph()
            }
        }
    }
}