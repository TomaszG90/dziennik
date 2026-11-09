# TASK2.md: Faza 9 — aplikacja mobilna Android

Ten plik rozwija plan mobilny zapowiedziany w [TASK.md](TASK.md). Obowiązują te same zasady Ready/Done, Issue, branchy i review. Numeracja PR jest globalna dla repozytorium i kontynuuje się od PR-55. Jedno zadanie to jeden Issue, branch i Pull Request.

## Wymagane narzędzia i wersje

| Narzędzie | Wersja / ustawienie |
|---|---|
| IDE zalecane | Najnowsza stabilna wersja Android Studio — oficjalnego IDE Androida, opartego na IntelliJ IDEA ([Android Studio](https://developer.android.com/studio/intro)) |
| IntelliJ IDEA (alternatywnie) | 2026.3; do obsługi Androida doinstaluj pluginy Android i Android Design Tools ([wydanie 2026.3](https://www.jetbrains.com/idea/whatsnew/2026-3/), [wsparcie Androida](https://www.jetbrains.com/help/idea/create-your-first-android-application.html)) |
| JDK | 17 |
| Android SDK | Platform 36 (API 36), Build Tools 36.0.0 |
| Android Gradle Plugin | 8.13.2 |
| Gradle Wrapper | 8.13 |
| Kotlin | 2.2.21 |

Wersje Gradle, pluginów i Kotlin są przypięte w konfiguracji projektu. Wersja IDE może być aktualizowana niezależnie; przy IntelliJ IDEA wymagane pluginy Android nie są dołączone domyślnie.

## Przygotowanie środowiska Android

### Co zainstalować

Zainstaluj **najnowszą stabilną wersję Android Studio**. To oficjalne IDE Androida i najlepsze środowisko dla Jetpack Compose; osobna instalacja IntelliJ IDEA nie jest potrzebna. Android Studio zawiera narzędzia do podglądu Compose i konfiguracji SDK. [Instrukcja instalacji Android Studio](https://developer.android.com/studio/install) · [Compose quick start](https://developer.android.com/develop/ui/compose/setup).

W kreatorze instalacji zaakceptuj instalację Android SDK i Emulatora. W Android Studio otwórz katalog `mobile/` z repozytorium, wykonaj **Gradle Sync**, a w ustawieniach Gradle wybierz JDK 17. Dla bieżącej konfiguracji projektu potrzebne są:

- JDK 17 (tę wersję używa Android CI; AGP 8.x wymaga co najmniej JDK 17 — [dokumentacja JDK dla buildów Androida](https://developer.android.com/build/jdks));
- Android SDK Platform 36 i Build Tools 36.0.0;
- opcjonalnie emulator Androida z obrazem systemu API 36 albo fizyczne urządzenie z włączonym debugowaniem USB.

Wersje Gradle i pluginów są zapisane w `mobile/gradle/wrapper/gradle-wrapper.properties`, `mobile/gradle/libs.versions.toml` i `mobile/app/build.gradle.kts`. Nie aktualizuj ich przez pojedyncze kliknięcie **Upgrade** w IDE; zmiana wersji powinna być osobnym, sprawdzonym zadaniem.

### Pierwsze uruchomienie

Z katalogu repozytorium:

```powershell
cd mobile
./gradlew.bat assembleDebug
```

W Git Bash/Linux/macOS użyj `./gradlew assembleDebug`. Android Studio może też uruchomić konfigurację `app` na emulatorze lub podłączonym urządzeniu. Do sprawdzenia dostępnych urządzeń użyj `adb devices`.

Zależności dodawaj przez Gradle: wersje do `gradle/libs.versions.toml`, a biblioteki do `app/build.gradle.kts`. Nie pobieraj bibliotek ręcznie i nie trzymaj plików JAR w repozytorium.

## Zakres aplikacji i kolejność zadań

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| PR-55 | Fundament aplikacji: nawigacja Compose, motyw, struktura UI/ViewModel/data, konfiguracja środowisk API, testy jednostkowe i rozszerzenie Android CI o lint/test | mobile / devops | PR-54 | M |
| PR-56 | Klient API Ktor: serializacja, Bearer token, mapowanie błędów API, timeouty i bezpieczne przechowywanie/odtwarzanie sesji | mobile | PR-55 | L |
| PR-57 | Uwierzytelnianie: logowanie, wylogowanie, rejestracja, walidacja i przekierowanie gościa/zalogowanego użytkownika | mobile | PR-56 | L |
| PR-58 | Reset hasła: formularz żądania linku i ustawienia hasła z obsługą odpowiedzi API | mobile | PR-57 | M |
| PR-59 | Profil: pobranie i edycja danych, widoczności, prywatności pól i awatara | mobile | PR-56 | L |
| PR-60 | Podróże: lista, stany ładowania/pusty/błąd, tworzenie, edycja i usuwanie | mobile | PR-57 | L |
| PR-61 | Szczegóły podróży i punkty: lista, tworzenie, edycja, usuwanie oraz zmiana kolejności miejsc | mobile | PR-60 | L |
| PR-62 | Odcinki podróży: CRUD, wybór transportu i walidacja powiązań z punktami | mobile | PR-61 | M |
| PR-63 | Mapa podróży Android: wybór biblioteki i dostawcy kafelków, markery miejsc oraz prezentacja geometrii odcinków | mobile | PR-30, PR-61, PR-62 | L |
| PR-64 | Statystyki użytkownika: podsumowanie, dystans i transport na podstawie API | mobile | PR-40, PR-57 | M |
| PR-65 | Profile publiczne: lista użytkowników oraz podgląd publicznych podróży i statystyk | mobile | PR-43 | L |
| PR-66 | Przygotowanie wydania Android: identyfikator aplikacji, warianty builda, podpisywanie poza repozytorium i wewnętrzna dystrybucja testowa | mobile / devops | PR-57, PR-60 | M |

PR-63, PR-64 i PR-65 czekają na wymagane funkcje backendu z [TASK.md](TASK.md). Nie implementuj zastępczych endpointów ani nie obliczaj po stronie aplikacji danych, które mają pochodzić z backendu.

## Kryteria akceptacji dla zadań mobilnych

- Funkcja działa na wspieranej wersji Androida i korzysta ze wspólnego `/api/v1` oraz `shared/openapi.yaml`.
- Każdy ekran obsługuje właściwe stany ładowania, pustej odpowiedzi, błędu i braku sieci.
- Warstwa UI nie wykonuje bezpośrednio żądań HTTP ani logiki biznesowej; przepływ prowadzi przez ViewModel i warstwę danych.
- Tokenów nie zapisuje się w zwykłych preferencjach ani logach. Sekrety środowiskowe i klucze usług nie trafiają do aplikacji ani repozytorium.
- Testy obejmują logikę ViewModel/warstwy danych; zachowanie kluczowych ekranów sprawdzają odpowiednie testy UI.
- `./gradlew lintDebug testDebugUnitTest assembleDebug` przechodzi lokalnie i w CI.
- Nowe biblioteki, uprawnienia systemowe, dane offline i decyzje o mapach mają uzasadnienie w Issue; uprawnienia prosimy dopiero wtedy, gdy funkcja ich potrzebuje.

## Poza zakresem tej fazy

Pierwszym celem jest Android. Wersja iOS, Kotlin Multiplatform, synchronizacja offline, lokalizacja w tle, powiadomienia push i publikacja publiczna w sklepie wymagają osobnych decyzji oraz zadań.
