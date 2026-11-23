package pl.dziennikpodrozy.mobile

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import pl.dziennikpodrozy.mobile.data.config.ApiConfig
import pl.dziennikpodrozy.mobile.ui.home.HomeViewModel

class HomeViewModelTest {
    @Test
    fun `initial state exposes correct apiBaseUrl from config`() {
        val customConfig = ApiConfig(baseUrl = "https://custom.api.pl/api/v1")
        val viewModel = HomeViewModel(apiConfig = customConfig)

        assertEquals("https://custom.api.pl/api/v1", viewModel.uiState.value.apiBaseUrl)
        assertTrue(viewModel.uiState.value.isReady)
    }
}