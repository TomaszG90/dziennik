# Dziennik Podróży — Android

Natywna aplikacja Android rozwijana w języku **Kotlin** z wykorzystaniem **Jetpack Compose** i architektury zgodnej z oficjalnymi wytycznymi Androida (UI / ViewModel / Data). Aplikacja komunikuje się z backendem Laravel poprzez REST API (`/api/v1`) zgodnie ze wspólnym kontraktem [`../shared/openapi.yaml`](../shared/openapi.yaml).

Decyzja architektoniczna i jej zakres: [`../docs/ADR-002-mobile-stack.md`](../docs/ADR-002-mobile-stack.md).

---

## Stack technologiczny

- **Język:** Kotlin (z `jvmTarget = JVM_17`)
- **System budowania:** Android Gradle Plugin (AGP) z Gradle Kotlin DSL i wersjami przypiętymi w `gradle/libs.versions.toml`
- **UI:** Jetpack Compose + Material 3 (`ui/theme/`)
- **Nawigacja:** Navigation Compose (`ui/navigation/AppNavGraph.kt`)
- **Architektura:** Unidirectional Data Flow (UDF), ViewModel (`androidx.lifecycle.viewmodel.compose`), Coroutines, Flow i StateFlow
- **Warstwa danych:** `data/config/ApiConfig.kt` z obsługą zmiennych środowiskowych przez `BuildConfig`
- **Testy:** JUnit 4 + Kotlinx Coroutines Test
- **Jakość kodu i CI:** Android Lint, testy jednostkowe i budowa debug APK weryfikowane w `.github/workflows/android-ci.yml`

---

## Wymagania wstępne

1. **JDK 17 do 22** (np. OpenJDK 17/21/22 lub JetBrains Runtime z Android Studio).
   > ⚠️ **Ważne:** Nie używaj wersji eksperymentalnych/zbyt nowych (np. Java 25, 26), ponieważ wtyczka Kotlin Gradle Plugin zgłosi wtedy błąd `IllegalArgumentException: 26.x.x`.
2. **Android SDK Platform 36** oraz **Build-Tools 36.0.0** (lub 35.0.0+).
3. **Android Studio** (rekomendowane) lub sam Android SDK Command-line Tools z emulatorem.

---

## Konfiguracja środowiska (krok po kroku)

### 1. Wskazanie ścieżki do Android SDK (`local.properties`)
W katalogu `mobile/` utwórz plik `local.properties` (plik ten jest ignorowany przez `.gitignore`):

- **Windows:**
  ```properties
  sdk.dir=C\:\\Users\\<TWOJ_UZYTKOWNIK>\\AppData\\Local\\Android\\Sdk
  ```
- **macOS:**
  ```properties
  sdk.dir=/Users/<TWOJ_UZYTKOWNIK>/Library/Android/sdk
  ```
- **Linux:**
  ```properties
  sdk.dir=/home/<TWOJ_UZYTKOWNIK>/Android/Sdk
  ```

*(Uwaga: w Android Studio ten plik generuje się automatycznie).*

### 2. Wybór odpowiedniego JDK dla Gradle (opcjonalnie)
Jeśli Twoja domyślna systemowa Java to wersja nowsza niż 22 (np. Java 25 lub 26), ustaw zgodny JDK w swoim domowym katalogu `~/.gradle/gradle.properties`:

- **PowerShell (Windows):**
  ```powershell
  Set-Content -Path "$env:USERPROFILE\.gradle\gradle.properties" -Value "org.gradle.java.home=C\:/Users/<TWOJ_UZYTKOWNIK>/.jdks/openjdk-22.0.2"
  ```
- **Linux / macOS:**
  ```bash
  echo "org.gradle.java.home=/sciezka/do/jdk-17-lub-21" >> ~/.gradle/gradle.properties
  ```

---

## Budowanie, Lint i Testy jednostkowe

Przed wysłaniem zmian do repozytorium upewnij się, że cały zestaw weryfikacji przechodzi pomyślnie (jest to ten sam zestaw zadań, który uruchamia GitHub Actions):

### W terminalu z katalogu `mobile/`:

- **Bash / Linux / macOS:**
  ```bash
  ./gradlew lintDebug testDebugUnitTest assembleDebug
  ```

- **PowerShell (Windows):**
  ```powershell
  .\gradlew.bat lintDebug testDebugUnitTest assembleDebug
  ```

Pojedyncze polecenia:
- `./gradlew lintDebug` — statyczna analiza kodu i zgodności z Androidem
- `./gradlew testDebugUnitTest` — uruchomienie testów jednostkowych
- `./gradlew assembleDebug` — zbudowanie pliku APK (`app/build/outputs/apk/debug/app-debug.apk`)

---

## Uruchamianie aplikacji i testy manualne

### Sposób A: Android Studio (najprostszy)
1. Otwórz folder `mobile` w **Android Studio**.
2. Poczekaj na zakończenie synchronizacji Gradle.
3. Wybierz urządzenie z listy u góry (fizyczny telefon lub AVD z API 26+).
4. Kliknij zielony przycisk **Run 'app'** (`Shift + F10`).

### Sposób B: Z wiersza poleceń (PowerShell / Terminal)
1. Upewnij się, że urządzenie lub emulator jest podłączony:
   ```bash
   adb devices
   ```
   *(Jeśli emulator nie działa, uruchom go np. przez: `emulator -avd <NAZWA_AVD>`)*
2. Zainstaluj wariant debug:
   ```powershell
   .\gradlew.bat installDebug
   ```
3. Uruchom aplikację na ekranie telefonu:
   ```powershell
   adb shell am start -n pl.dziennikpodrozy.mobile/.MainActivity
   ```

---

## Konfiguracja środowisk i komunikacja z API

Aplikacja mobilna korzysta z konfiguracji `ApiConfig`, która odczytuje adres bazowy z `BuildConfig.API_BASE_URL`.

- **Emulator Androida:** backend uruchomiony na komputerze pod `http://localhost:8000` jest widziany z poziomu emulatora pod adresem:
  `http://10.0.2.2:8000/api/v1` (domyślna wartość w `build.gradle.kts`).
- **Urządzenie fizyczne:** jeśli testujesz na prawdziwym telefonie w tej samej sieci Wi-Fi, użyj adresu IP swojego komputera w sieci lokalnej (np. `http://192.168.1.50:8000/api/v1`).

---

## Rozwiązywanie typowych problemów (Troubleshooting)

### 1. `FAILURE: Build failed with an exception. * What went wrong: 26.0.1`
- **Przyczyna:** Systemowa zmienna `JAVA_HOME` wskazuje na Javę 25/26.
- **Rozwiązanie:** Wskaż JDK 17, 21 lub 22 w pliku `~/.gradle/gradle.properties` (`org.gradle.java.home=...`).

### 2. `SDK location not found`
- **Przyczyna:** Brak pliku `local.properties` ze ścieżką do Android SDK.
- **Rozwiązanie:** Utwórz `mobile/local.properties` i wklej ścieżkę do swojego SDK (`sdk.dir=...`).

### 3. `Device is OFFLINE` lub `No connected devices!`
- **Przyczyna:** Emulator jeszcze się włącza albo żaden telefon nie jest podłączony z włączonym debugowaniem USB.
- **Rozwiązanie:** Poczekaj kilkanaście sekund, aż system Android na emulatorze w pełni wystartuje (aż zobaczysz pulpit Androida), a następnie ponów `.\gradlew.bat installDebug`.

### 4. Emulator zużywa za dużo pamięci RAM
- **Przyczyna:** Domyślne ustawienia AVD mogą rezerwować aż 4 GB pamięci RAM.
- **Rozwiązanie:** W Android Studio wejdź w **Device Manager** -> Edytuj AVD -> **Show Advanced Settings** -> Zmniejsz **RAM** do `2048 MB` lub `1536 MB`. Możesz także zwolnić demony Gradle poleceniem: `.\gradlew.bat --stop`.
