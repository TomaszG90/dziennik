# ADR-002: Natywna aplikacja Android

- **Status:** Zaakceptowana
- **Data:** 2026-11-08
- **Obszar:** mobile, API
- **Powiązane zadania:** PR-51–PR-54

## Kontekst

Projekt rozpoczyna aplikację mobilną jako osobny klient wspólnego REST API Laravel (`/api/v1`). Zespół wybrał Kotlin i chce zacząć od pustego szkieletu, bez przenoszenia widoków Vue.

## Decyzja

- Platforma początkowa: **Android**.
- Język i build: **Kotlin**, Gradle Kotlin DSL i Gradle Wrapper.
- UI: **Jetpack Compose** z Material 3.
- Architektura: warstwy UI/data, ViewModel i jednokierunkowy przepływ stanu; szkielet nie implementuje logiki domenowej.
- Nawigacja: Navigation Compose, gdy pojawią się pierwsze ekrany.
- Asynchroniczność i stan: Kotlin Coroutines, Flow i StateFlow.
- HTTP: Ktor Client z silnikiem OkHttp oraz Kotlinx Serialization, dodawane przy pierwszej integracji API.
- Wstrzykiwanie zależności: bez biblioteki DI w szkielecie; wybór dopiero, gdy liczba zależności aplikacji tego wymaga.
- Kontrakt API: wspólny `shared/openapi.yaml`, generowany z backendu przez Scramble. Android będzie później integrował API na podstawie tego kontraktu.
- CI: budowanie wariantu debug na Ubuntu przez Gradle; publikowanie do sklepu i podpisywanie są poza zakresem bootstrapu.

## Konsekwencje

- `mobile/` jest niezależną aplikacją Android i nie współdzieli kodu UI z Vue.
- Wspólne pozostają backend i wersjonowany kontrakt API.
- Nie generujemy jeszcze klienta Kotlin z OpenAPI; kontrakt będzie podstawą implementacji klienta w osobnym zadaniu.
- iOS nie jest celem obecnego bootstrapu. Wymaganie iOS oznacza osobną decyzję, np. Kotlin Multiplatform lub osobny klient.
- Wersje Android Gradle Plugin, Kotlin, Compose i SDK powinny być przypięte w katalogu wersji Gradle i aktualizowane razem.

