# Nazwa projektu (Dziennik)

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
docs/       # dokumentacja
```

## Stack technologiczny

| Warstwa | Technologia |
|---|---|
| Backend (API) | PHP 8.x, Laravel 12, Laravel Sanctum |
| Frontend (SPA) | Vue 3, Vite, Vue Router, Pinia, TypeScript |
| Baza danych | PostgreSQL (do ustalenia: MySQL) |
| Mapa | Leaflet lub MapLibre GL (do ustalenia) |
| Wyznaczanie tras | OpenRouteService lub OSRM (do ustalenia) |
| Geokodowanie | Nominatim / Photon lub inne (do ustalenia) |
| Granice państw | GeoJSON (np. Natural Earth) |
| Testy | Pest/PHPUnit, Vitest |
| Jakość kodu | Laravel Pint, ESLint, Prettier |
| CI/CD | GitHub Actions |

Zewnętrzne API i gotowe biblioteki są wykorzystywane do obsługi map, geokodowania i wyznaczania tras.

## Wymagania

- PHP >= 8.2 i Composer
- Node.js >= 20 i npm
- PostgreSQL (lub MySQL)
- Klucze do zewnętrznych API (patrz sekcja Konfiguracja)

## Uruchomienie lokalne

```bash
git clone <url-repo>
cd <nazwa-repo>
```

**Backend**

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

**Frontend** (w drugim terminalu)

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

## Konfiguracja

Zmienne środowiskowe ustawiasz w plikach `.env` (nie commituj ich do repo).

| Zmienna | Opis |
|---|---|
| `DB_*` | Dane połączenia z bazą (backend) |
| `ROUTING_API_KEY` | Klucz do API wyznaczania tras (backend) |
| `GEOCODING_API_KEY` | Klucz do API geokodowania (backend) |
| `VITE_API_URL` | Adres API (frontend) |

## Testy i jakość kodu

```bash
# backend
php artisan test
./vendor/bin/pint

# frontend
npm run lint
npm run test
```

winget install Composer.Composer --accept-source-agreements --accept-package-agreements## Współpraca

Zasady pracy, branche i Pull Requesty opisuje [CONTRIBUTING.md](CONTRIBUTING.md).

## Licencja

Projekt udostępniany na licencji MIT. Szczegóły w pliku [LICENSE](LICENSE).