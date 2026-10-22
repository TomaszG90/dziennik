# Dziennik Podróży

Aplikacja turystyczna będąca osobistym dziennikiem podróży. Użytkownik dokumentuje odbyte podróże, widzi je na interaktywnej mapie i przegląda statystyki podróżnicze z całej historii lub z wybranego roku.

Po kilku latach korzystania z aplikacji można otworzyć mapę i zobaczyć wszystkie podróże, odwiedzone kraje oraz statystyki pokazujące, jak podróżowaliśmy na przestrzeni lat.

## Funkcjonalności

### Konta użytkowników
- Rejestracja, logowanie i wylogowywanie
- Edycja podstawowych danych profilu
- Profil publiczny lub prywatny
- Przeglądanie podróży innych użytkowników (tylko profile publiczne)

### Zarządzanie podróżami
- Dodawanie, edycja i usuwanie podróży
- Nazwa, data lub zakres dat, opcjonalny opis
- Dowolna liczba punktów i odcinków w podróży
- Ustalanie kolejności miejsc i środka transportu dla każdego odcinka

Przykład podróży:

1. Legnica → Berlin (samochód)
2. Berlin → Majorka (samolot)
3. Zwiedzanie Majorki, kilka punktów (samochód)
4. Majorka → Berlin (samolot)
5. Berlin → Legnica (samochód)

### Środki transportu

| Transport | Prezentacja na mapie | Obliczanie dystansu |
|---|---|---|
| Samochód | Trasa po rzeczywistej sieci dróg | Z API wyznaczającego trasy |
| Samolot | Łuk między punktami | Odległość po kuli ziemskiej |
| Pociąg | Linia prosta | Odległość w linii prostej |
| Inne | Uproszczone połączenie | Odległość w linii prostej |

### Interaktywna mapa
- Pojedyncza podróż z punktami i odcinkami
- Rozróżnienie transportu kolorami, ikonami lub stylem linii
- Wszystkie podróże użytkownika na jednej mapie
- Automatyczne oznaczanie odwiedzonych państw na podstawie zapisanych podróży
- Przybliżanie mapy i podgląd szczegółów tras

### Statystyki
Dla całej historii oraz dla wybranego roku kalendarzowego:
- liczba odbytych podróży
- liczba i lista odwiedzonych państw (wyróżnione na mapie)
- łączna liczba przebytych kilometrów
- dystans według środka transportu
- liczba odwiedzonych miejsc

## Architektura

Aplikacja składa się z REST API i osobnej aplikacji SPA. Frontend komunikuje się z backendem wyłącznie przez API, dzięki czemu w przyszłości można dodać aplikację mobilną korzystającą z tych samych endpointów.

```
backend/    # Laravel: REST API
frontend/   # Vue 3 SPA
mobile/     # Aplikacja mobilna (w planach)
docker/     # Konfiguracja środowiska kontenerowego (Nginx, PHP, Node)
docs/       # Dokumentacja projektu
```

## Stack technologiczny

| Warstwa | Technologia |
|---|---|
| Backend (API) | PHP 8.3+, Laravel 12, Laravel Sanctum |
| Frontend (SPA) | Vue 3, Vite, Vue Router, Pinia, TypeScript |
| Baza danych | PostgreSQL 16 |
| Konteneryzacja | Docker, Docker Compose |
| Mapa | Leaflet lub MapLibre GL (do ustalenia) |
| Wyznaczanie tras | OpenRouteService lub OSRM (do ustalenia) |
| Geokodowanie | Nominatim / Photon lub inne (do ustalenia) |
| Granice państw | GeoJSON (np. Natural Earth) |
| Testy | Pest/PHPUnit, Vitest |
| Jakość kodu | Laravel Pint, ESLint, Prettier |
| CI/CD | GitHub Actions |

Zewnętrzne API i gotowe biblioteki są wykorzystywane do obsługi map, geokodowania i wyznaczania tras.

## Wymagania

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (z obsługą Docker Compose v2)
- Git

*(Opcjonalnie do pracy bez kontenerów: PHP >= 8.3 i Composer, Node.js >= 20 i npm, PostgreSQL 16).*

## Uruchomienie lokalne (Docker)

1. Sklonuj repozytorium:
   ```bash
   git clone <url-repo>
   cd dziennik
   ```

2. Skonfiguruj zmienne środowiskowe:
   ```bash
   cp backend/.env.example backend/.env
   cp frontend/.env.example frontend/.env
   ```

3. Zbuduj i uruchom kontenery w tle:
   ```bash
   docker compose up -d --build
   ```

4. Zainicjalizuj backend wewnątrz kontenera:
   ```bash
   # Generowanie klucza aplikacji
   docker compose exec backend php artisan key:generate

   # Wykonanie migracji bazy i seedera
   docker compose exec backend php artisan migrate --seed
   ```

### Dostęp do aplikacji

- **Frontend (Vue/Vite):** [http://localhost:5173](http://localhost:5173) (z hot-reloadem Vite)
- **Backend (API / Nginx):** [http://localhost:8000](http://localhost:8000)
- **Baza danych (PostgreSQL):** `localhost:5432` (baza: `dziennik`, użytkownik: `dziennik_user`, hasło: `secret`)

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

Zmienne środowiskowe ustawiasz w plikach `.env` (nie commituj ich do repozytorium).

W pliku `backend/.env` dla środowiska Docker hostem bazy danych jest nazwa kontenera `postgres`:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=dziennik
DB_USERNAME=dziennik_user
DB_PASSWORD=secret
```

| Zmienna | Opis |
|---|---|
| `DB_*` | Dane połączenia z bazą (backend) |
| `ROUTING_API_KEY` | Klucz do API wyznaczania tras (backend) |
| `GEOCODING_API_KEY` | Klucz do API geokodowania (backend) |
| `VITE_API_URL` | Adres API (frontend, np. `http://localhost:8000`) |

## Testy i jakość kodu

Wszystkie narzędzia uruchamiamy przez dockera:

```bash
# Backend
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint

# Frontend
docker compose exec frontend npm run lint
docker compose exec frontend npm run test
```

## Współpraca

Zasady pracy, branche i Pull Requesty opisuje [CONTRIBUTING.md](CONTRIBUTING.md).

## Licencja

Projekt udostępniany na licencji MIT. Szczegóły w pliku [LICENSE](LICENSE).
