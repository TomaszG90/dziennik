# Dziennik Podróży — Android

Natywna aplikacja Android rozwijana od podstaw w Kotlinie. Na tym etapie repozytorium zawiera wyłącznie konfigurację projektu i CI; nie ma jeszcze ekranów ani funkcji aplikacji.

## Stack

- Kotlin
- Android Gradle Plugin i Gradle Kotlin DSL (wersje przypięte w `gradle/libs.versions.toml`)
- Jetpack Compose i Material 3
- AndroidX Lifecycle, ViewModel i Navigation Compose przy dodawaniu ekranów
- Coroutines, Flow i StateFlow dla pracy asynchronicznej
- Ktor Client, OkHttp i Kotlinx Serialization przy pierwszej integracji API
- Kontrakt REST API: [`../shared/openapi.yaml`](../shared/openapi.yaml), generowany z backendu przez Scramble

Decyzja architektoniczna i jej zakres: [`../docs/ADR-002-mobile-stack.md`](../docs/ADR-002-mobile-stack.md).

## Wymagania

- JDK 17 lub nowszy
- Android SDK zgodny z `compileSdk` projektu
- Android Studio (zalecane) albo Gradle Wrapper z repozytorium

## Budowanie

Z katalogu `mobile/`:

```bash
./gradlew assembleDebug
```

W PowerShell:

```powershell
.\gradlew.bat assembleDebug
```

## Zasady

- Nie wywołuj usług zewnętrznych bezpośrednio z aplikacji; klient mobilny korzysta z API Laravel.
- Zmiany endpointów opisuj w backendzie i aktualizuj wspólny kontrakt OpenAPI.
- Nie umieszczaj sekretów ani kluczy w aplikacji mobilnej.
- Pierwsze ekrany i funkcje powinny być osobnymi zadaniami po zaakceptowaniu bootstrapu.
