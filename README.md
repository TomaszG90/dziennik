# Dziennik Podróży

Projekt aplikacji turystycznej będącej osobistym dziennikiem podróży. Docelowo użytkownik będzie dokumentować odbyte podróże, oglądać je na interaktywnej mapie i przeglądać statystyki z całej historii lub wybranego roku.

Po kilku latach korzystania z aplikacji można otworzyć mapę i zobaczyć wszystkie podróże, odwiedzone kraje oraz statystyki pokazujące, jak podróżowaliśmy na przestrzeni lat.

## Funkcjonalności

### Konta użytkowników (częściowo zaimplementowane)
- Rejestracja, logowanie i wylogowywanie przez API
- Połączony widok logowania i rejestracji pod adresem `/auth`
- Reset hasła przez e-mail (PR-17E): żądanie linku oraz ustawienie nowego hasła po jego otwarciu
- Edycja profilu, wybór predefiniowanego awatara i ustawienia prywatności
- Developerskie konta demonstracyjne: publiczne, prywatne i częściowo ukryte
- Przeglądanie podróży innych użytkowników (tylko profile publiczne) — w planach

### Zarządzanie podróżami (API zaimplementowane, interfejs w planach)
- Dodawanie, edycja, usuwanie i wyświetlanie podróży przez API
- Nazwa, data lub zakres dat, opcjonalny opis
- Dodawanie, edycja, usuwanie i zmiana kolejności punktów podróży przez API
- Dodawanie, edycja i usuwanie odcinków oraz wybór transportu przez API
- Automatyczne wyznaczanie geometrii tras i dystansu odcinków — w planach

Przykład podróży:

1. Legnica → Berlin (samochód)
2. Berlin → Majorka (samolot)
3. Zwiedzanie Majorki, kilka punktów (samochód)
4. Majorka → Berlin (samolot)
5. Berlin → Legnica (samochód)

### Środki transportu (w planach)

| Transport | Prezentacja na mapie | Obliczanie dystansu |
|---|---|---|
| Samochód | Trasa po rzeczywistej sieci dróg | Z API wyznaczającego trasy |
| Samolot | Łuk między punktami | Odległość po kuli ziemskiej |
| Pociąg | Linia prosta | Odległość w linii prostej |
| Inne | Uproszczone połączenie | Odległość w linii prostej |

### Geokodowanie (API zaimplementowane)
- Wyszukiwanie miejsc przez API geokodowania
- Cache wyników oraz ograniczanie liczby zapytań

### Interaktywna mapa (w planach)
- Pojedyncza podróż z punktami i odcinkami
- Rozróżnienie transportu kolorami, ikonami lub stylem linii
- Wszystkie podróże użytkownika na jednej mapie
- Automatyczne oznaczanie odwiedzonych państw na podstawie zapisanych podróży
- Przybliżanie mapy i podgląd szczegółów tras

### Statystyki (API zaimplementowane, interfejs w planach)
Endpoint `GET /api/v1/stats` (wymaga tokenu Sanctum) zwraca statystyki zalogowanego użytkownika. Parametr `year` jest opcjonalny; po jego podaniu uwzględniane są podróże, których zakres dat przecina wskazany rok. Statystyki takiej podróży obejmują wszystkie jej miejsca i odcinki.

Dane są dostępne dla całej historii lub dla wybranego roku kalendarzowego:

- liczba podróży
- lista unikalnych kodów odwiedzonych państw
- łączny dystans w kilometrach
- dystans w kilometrach według środka transportu
- liczba miejsc

## Architektura

Aplikacja składa się z backendu Laravel, aplikacji Vue SPA i rozwijanej natywnej aplikacji Android. Vue korzysta ze wspólnego REST API (`/api/v1`); aplikacja Android będzie korzystać z tego samego API. Backend udostępnia m.in. funkcje kont, profilu, podróży, punktów, odcinków, geokodowania i statystyk. Interfejs mapy i statystyk jest w planach. Aplikacja Android jest na etapie pustego szkieletu i nie ma jeszcze funkcji użytkowych.

```
backend/    # Laravel: REST API
frontend/   # Vue 3 SPA
mobile/     # Natywna aplikacja Android (Kotlin + Jetpack Compose)
docker/     # Konfiguracja środowiska kontenerowego (Nginx, PHP, Node)
docs/       # Dokumentacja projektu
```

## Stack technologiczny

| Warstwa | Technologia |
|---|---|
| Backend (API) | PHP 8.3+, Laravel 12, Laravel Sanctum |
| Frontend (SPA) | Vue 3, Vite, Vue Router, Pinia, TypeScript |
| Mobile (Android) | Kotlin, Jetpack Compose, AndroidX |
| Kontrakt API | Laravel Scramble, OpenAPI 3.1 (`shared/openapi.yaml`) |
| Baza danych | PostgreSQL 16 |
| Konteneryzacja | Docker, Docker Compose |
| Mapa | Leaflet lub MapLibre GL (do ustalenia) |
| Wyznaczanie tras | OpenRouteService lub OSRM (do ustalenia) |
| Geokodowanie | Nominatim / Photon lub inne (do ustalenia) |
| Granice państw | GeoJSON (np. Natural Earth) |
| Testy | PHPUnit, Vitest |
| Jakość kodu | Laravel Pint, ESLint, Prettier |
| CI/CD | GitHub Actions |

Zewnętrzne API i biblioteki mapowe zostaną wybrane i skonfigurowane w ramach zadań opisanych w backlogu.

Stack mobilny opisuje [ADR-002](docs/ADR-002-mobile-stack.md), a instrukcja aplikacji Android znajduje się w [mobile/README.md](mobile/README.md).

## Praca nad Androidem

`mobile/` to osobna aplikacja natywna w Kotlinie. Na tym etapie zawiera tylko szkielet Compose, bez ekranów i funkcji. Pierwszy build uruchomisz z katalogu repozytorium:

```bash
cd mobile
./gradlew assembleDebug
```

W PowerShell użyj `cd mobile; .\gradlew.bat assembleDebug`. W emulatorze Androida hostowy backend dostępny pod `localhost:8000` jest osiągalny przez `10.0.2.2:8000`; adres bazowy API konfiguruj zależnie od środowiska, nie wpisuj go na stałe w ekranach lub repozytoriach.

Nowe funkcje mobilne powinny konsumować `/api/v1` na podstawie [`shared/openapi.yaml`](shared/openapi.yaml). Tokeny uwierzytelniające przechowuj z użyciem bezpiecznego mechanizmu Androida, a kluczy serwisów zewnętrznych nie umieszczaj w aplikacji.

## Wymagania

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (z obsługą Docker Compose v2)
- Git

*(Opcjonalnie do pracy bez kontenerów: PHP >= 8.3 i Composer, Node.js 22.18+ (lub 24.12+) i npm, PostgreSQL 16).*

Do pracy nad Androidem potrzebne są również JDK 17+ i Android Studio z Android SDK. Wersję SDK i polecenia builda opisuje [mobile/README.md](mobile/README.md).

## Uruchomienie lokalne (Docker)

1. Sklonuj repozytorium:
   ```bash
   git clone <url-repo>
   cd dziennik
   ```

2. Skonfiguruj zmienne środowiskowe:
   ```bash
   cp .env.example .env
   cp .env backend/.env
   cp frontend/.env.example frontend/.env
   ```
   Główny `.env` dostarcza zmienne dla Docker Compose, a `backend/.env` jest używany przez Laravel. Oba pliki muszą mieć zgodne wartości `DB_*`. Ustaw niepuste lokalne `DB_PASSWORD` w głównym `.env` przed uruchomieniem bazy.

3. Zbuduj i uruchom kontenery w tle:
   ```bash
   docker compose up -d --build
   ```

4. Zainstaluj zależności backendu:
   ```bash
   docker compose exec backend composer install
   ```

5. Zainicjalizuj backend wewnątrz kontenera:
   ```bash
   # Sprawdź, czy backend/.env zawiera klucz; wygeneruj go tylko, jeśli jest pusty
   docker compose exec backend sh -c 'if grep -q "^APP_KEY=." .env; then echo "APP_KEY jest ustawiony"; else echo "APP_KEY jest pusty — generuję klucz"; php artisan key:generate; fi'

   # Odtwórz backend, aby kontener wczytał APP_KEY z pliku backend/.env
   docker compose up -d --force-recreate backend

   # Sprawdź, czy klucz jest dostępny w środowisku kontenera (bez wypisywania wartości)
   docker compose exec backend php -r "echo getenv('APP_KEY') ? 'APP_KEY jest ustawiony' : 'APP_KEY jest pusty';"

   # Wykonanie migracji bazy i seedera
   docker compose exec backend php artisan migrate --seed
   ```

### Dostęp do aplikacji

- **Frontend (Vue/Vite):** [http://localhost:5173](http://localhost:5173) (z hot-reloadem Vite)
- **Backend (API / Nginx):** [http://localhost:8000](http://localhost:8000)
- **Baza danych (PostgreSQL):** `localhost:5432` (baza, użytkownik i hasło zgodne z głównym `.env`)

## Przydatne polecenia Docker

```bash
# Zatrzymanie kontenerów
docker compose down

# Podgląd logów wszystkich usług
docker compose logs -f

# Wejście do powłoki kontenera backendu
docker compose exec backend sh

# Uruchamianie komend Artisan
docker compose exec backend php artisan <komenda>
```

## Konfiguracja

Zmienne środowiskowe ustawiasz w plikach `.env` (nie commituj ich do repozytorium). Wartości w głównym `.env` i `backend/.env` muszą być zgodne. Lokalnego hasła do bazy nie używaj na środowisku produkcyjnym.

W pliku `backend/.env` dla środowiska Docker hostem bazy danych jest nazwa kontenera `postgres`:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=dziennik
DB_USERNAME=dziennik_user
DB_PASSWORD=local_dev_password
```

| Zmienna | Opis |
|---|---|
| `DB_*` | Dane połączenia z bazą (backend) |
| `ROUTING_API_KEY` | Klucz do API wyznaczania tras (do skonfigurowania, gdy funkcja zostanie wdrożona) |
| `GEOCODING_API_KEY` | Klucz do API geokodowania (do skonfigurowania, gdy funkcja zostanie wdrożona) |
| `VITE_API_BASE_URL` | Bazowy adres API frontendu (domyślnie `http://localhost:8000/api/v1`) |
| `FRONTEND_URL` | Publiczny adres frontendu używany w linkach resetowania hasła (domyślnie `http://localhost:5173`) |

## Testy i jakość kodu

Polecenia lokalne uruchamiamy w kontenerach:

```bash
# Backend
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test

# Frontend
docker compose exec frontend npm run lint
docker compose exec frontend npm run test
docker compose exec frontend npm run build
```

Kontrole aplikacji Android uruchamiaj z katalogu `mobile/`:

```bash
./gradlew lintDebug testDebugUnitTest assembleDebug
```

W PowerShell: `cd mobile; .\gradlew.bat lintDebug testDebugUnitTest assembleDebug`.

GitHub Actions uruchamia testy backendu i build frontendu dla zmian w backendzie, frontendzie lub kontrakcie; osobny workflow sprawdza lint/test/build frontendu, a zmiany w `mobile/` uruchamiają build debug Androida. Obecny workflow Android CI nie uruchamia jeszcze `lintDebug` ani `testDebugUnitTest`; dodaj je do CI wraz z pierwszymi zadaniami funkcjonalnymi.

## Współpraca

Zasady współpracy opisuje [CONTRIBUTING.md](CONTRIBUTING.md), procedura realizacji zadań jest w [TASK_ins.md](TASK_ins.md), a backlog w [TASK.md](TASK.md).

## Licencja

Projekt udostępniany na licencji MIT. Szczegóły w pliku [LICENSE](LICENSE).
