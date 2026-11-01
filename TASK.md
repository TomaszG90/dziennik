# TASK.md: lista zadań projektu (PR-1, PR-2, ...)

Lista zadań rozwojowych. Każde zadanie to **jeden branch = jeden Pull Request**. Numer `PR-X` jest identyfikatorem zadania; używaj go w nazwie brancha, tytule Issue i opisie PR.

Jak wykonać zadanie krok po kroku: [TASK_ins.md](TASK_ins.md).

## Aktualny stan

Repozytorium zawiera podstawowy szkielet Laravel 12 z Sanctum, PostgreSQL i testami PHPUnit oraz aplikację Vue 3/Vite. W backendzie jest obecnie przykładowa chroniona trasa `/api/user`; wersjonowane API `/api/v1` i funkcje dziennika podróży są celem kolejnych zadań, a nie ukończonymi funkcjami. Przed rozpoczęciem zadania sprawdź zależności i kryteria akceptacji z Issue.

## Definicje Ready i Done

### Definition of Ready (Kiedy można zacząć zadanie)
- [ ] Zadanie ma jasny tytuł z identyfikatorem `PR-X` i jednoznaczny opis.
- [ ] Zadania z kolumny **Zależy od** są już zmergowane do `main`.
- [ ] Zdefiniowano kryteria akceptacji (wiadomo dokładnie, co oznacza sukces).
- [ ] Oszacowano rozmiar zadania (S / M / L).

### Definition of Done (Kiedy zadanie jest zakończone)
- [ ] Kod działa poprawnie w środowisku kontenerowym Docker.
- [ ] Testy i kontrole jakości właściwe dla zmiany przechodzą (`php artisan test`, Pint; dla frontendu także lint, testy i build).
- [ ] Pull Request używa szablonu `.github/PULL_REQUEST_TEMPLATE.md` i został zaakceptowany przez min. 1 osobę.
- [ ] Kod został zmergowany metodą **squash** do `main`, a gałąź została usunięta.

## Zgłaszanie zadania

Utwórz Issue, korzystając z szablonu funkcji w `.github/ISSUE_TEMPLATE/feature_request.md`. W tytule umieść identyfikator `PR-X`, a w treści uzupełnij kontekst, wymagania techniczne i mierzalne kryteria akceptacji. Błędy zgłaszaj przez `.github/ISSUE_TEMPLATE/bug_report.md`.

## Jak korzystać z listy

1. Wybierz zadanie, którego kolumna **Zależy od** jest już zmergowana (lub jest `-`).
2. Przypisz je do siebie w Issue z szablonu i dopisz `PR-X` do tytułu.
3. Nazwa brancha: `feature/pr-8-rejestracja-api`, commity: `feat: dodaj endpoint rejestracji`.
4. Jedna osoba może wziąć kilka zadań, ale **po jednym naraz**.
5. Zadania z tej samej fazy bez wzajemnych zależności można robić **równolegle**.

**Rozmiar:** S = do 2 h, M = do pół dnia, L = do 2 dni (większe zadania dzielimy).

> Kolejność w tabelach to sugerowana kolejność realizacji. Pogrubione zadania blokują wiele innych, więc warto zrobić je jako pierwsze.

---

## Faza 0: Fundament projektu

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| ~~**PR-1**~~ | Pliki repozytorium i środowisko Docker: `.gitignore`, konteneryzacja (`docker-compose.yml`, Dockerfile PHP 8.3-FPM, Nginx, Node 20, PostgreSQL 16), `LICENSE` (MIT), szablony Issue i PR w `.github/` | devops / docs | - | M |
| ~~**PR-2**~~ | Inicjalizacja backendu w Dockerze: Laravel 12, Sanctum, Pint, Pest/PHPUnit, konfiguracja bazy PostgreSQL w `.env.example` | backend | PR-1 | M |
| ~~**PR-3**~~ | Inicjalizacja frontendu w Dockerze: Vue 3, Vite, TypeScript, Vue Router, Pinia, ESLint, Prettier, Vitest, HMR w kontenerze, `.env.example` | frontend | PR-1 | M |
| ~~PR-4~~ | Prefiks `/api/v1`, endpoint zdrowia `GET /api/v1/health`, jednolity format błędów JSON (handler wyjątków) | backend | PR-2 | S |
| ~~PR-5~~ | CI backend (GitHub Actions): `composer install`, Pint, testy | backend | PR-2 | M |
| ~~PR-6~~ | CI frontend (GitHub Actions): `npm ci`, lint, testy, build | frontend | PR-3 | S |
| ~~PR-7~~ | Dokumentacja decyzji w `docs/`: wybór biblioteki map, API tras i geokodowania (PostgreSQL został wybrany) | docs | - | M |
| ~~PR-8~~ | Szkielet dokumentacji API w `docs/` (plik OpenAPI lub opis w Markdown) wraz z regułami jej aktualizacji | docs | PR-4 | S |

---

## Faza 1: Konta użytkowników

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| ~~**PR-9**~~ | Migracja i model użytkownika: dodać pole widoczności profilu (publiczny/prywatny), fabryka i seeder | backend | PR-2 | S |
| ~~PR-10~~ | `POST /api/v1/auth/register` (Form Request, serwis, API Resource) + testy | backend | PR-9, PR-4 | M |
| ~~PR-11~~ | `POST /api/v1/auth/login` i `POST /api/v1/auth/logout` (Sanctum) + testy | backend | PR-10 | M |
| ~~PR-12~~ | `GET` i `PATCH /api/v1/me`: podgląd i edycja danych profilu oraz widoczności + testy | backend | PR-11 | M |
| ~~**PR-13**~~ | Klient API we frontendzie: `src/api/` (instancja axios/fetch, obsługa tokenu i błędów 401/422) | frontend | PR-3 | M |
| ~~PR-14~~ | Store auth (Pinia): stan użytkownika, login, logout, odtwarzanie sesji po odświeżeniu | frontend | PR-13 | M |
| ~~PR-15~~ | Layout aplikacji i nawigacja (nagłówek, menu, strona główna) oraz guardy routera (zalogowany / gość) | frontend | PR-14 | M |
| ~~PR-16~~ | Widoki: formularz rejestracji i logowania z wyświetlaniem błędów walidacji | frontend | PR-14, PR-10, PR-11 | M |
| ~~PR-17~~ | Widok edycji profilu (dane + przełącznik profil publiczny/prywatny) | frontend | PR-15, PR-12 | M |~~
| PR-17A | Rozszerzenie modelu `User` i seeder developerski: migracja nowych pól i ustawień prywatności zgodnych z kontraktem frontendu; seeder z 3 predefiniowanymi kontami (publiczne, prywatne i częściowo ukryte z mieszanymi ustawieniami w polu JSON) | backend | PR-11 | M |
| PR-17B | API profilu i wybór predefiniowanego awatara: endpoint `PATCH /api/v1/me`; walidacja awatara ograniczona do nazw wcześniej wgranych plików (np. `Rule::in(['1.png', ..., '10.png'])`) | backend | PR-17A | S |
| PR-17C | Połączony widok logowania i rejestracji: zastąpienie osobnych tras jednym widokiem `/auth` z przełączanymi formularzami oraz aktualizacja Vue Routera i linków w menu | frontend | PR-15 | M |
| PR-17D | Interfejs edycji profilu zgodny z makietą: pola danych, ustawienia prywatności poszczególnych pól w JSON i przełącznik globalny, modal/galeria wyboru jednego z 10 awatarów oraz minimalna szerokość okna edycji profilu | frontend | PR-17B, PR-17C | M |
| PR-17E | Reset hasła: backendowe endpointy `POST /forgot-password` i `POST /reset-password` z tokenem e-mail oraz widoki podania adresu e-mail i zmiany hasła po kliknięciu w link | backend + frontend | PR-17C | L |

---

## Faza 2: Podróże, punkty i odcinki

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| ~~**PR-18**~~ | Model `Trip` (nazwa, data lub zakres dat, opis, właściciel) + migracja + polityka dostępu (tylko właściciel) | backend | PR-9 | M |
| ~~PR-19~~ | CRUD podróży: `/api/v1/trips` (lista, szczegóły, tworzenie, edycja, usuwanie) + testy | backend | PR-18 | L |
| ~~**PR-20**~~ | Model `Place` (punkt: nazwa, współrzędne, kolejność, kod kraju) i `Leg` (odcinek: z, do, transport, dystans) + migracje~~ | backend | PR-18 | M |
| PR-21 | API punktów podróży: dodawanie, edycja, usuwanie, zmiana kolejności + testy | backend | PR-20 | L |
| PR-22 | API odcinków: tworzenie, edycja, usuwanie, wybór środka transportu (samochód, samolot, pociąg, inne) + testy | backend | PR-20 | L |
| PR-23 | Frontend: lista podróży użytkownika (widok + stan ładowania i pusty stan) | frontend | PR-15, PR-19 | M |
| PR-24 | Frontend: formularz tworzenia i edycji podróży (nazwa, daty, opis), usuwanie z potwierdzeniem | frontend | PR-23 | M |
| PR-25 | Frontend: widok szczegółów podróży z listą punktów i odcinków (bez mapy) | frontend | PR-24, PR-21, PR-22 | M |
| PR-26 | Frontend: dodawanie, usuwanie i zmiana kolejności punktów i odcinków oraz wybór transportu | frontend | PR-25 | L |

---

## Faza 3: Geolokalizacja i dystanse

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| PR-27 | Serwis geokodowania (Nominatim/Photon) i endpoint wyszukiwania miejsc `GET /api/v1/geocode?q=` z cache i limitem zapytań | backend | PR-4, PR-7 | M |
| PR-28 | Serwis dystansu "po kuli ziemskiej" (haversine) i "w linii prostej" + testy jednostkowe | backend | PR-2 | S |
| PR-29 | Serwis wyznaczania trasy samochodowej (OpenRouteService/OSRM): geometria trasy i dystans, obsługa błędów API | backend | PR-7 | L |
| PR-30 | Automatyczne wyliczanie i zapis dystansu oraz geometrii odcinka zależnie od transportu | backend | PR-22, PR-28, PR-29 | M |
| PR-31 | Ustalanie kraju dla punktu (kod ISO) na podstawie współrzędnych lub odpowiedzi geokodera i zapis przy punkcie | backend | PR-20, PR-27 | M |
| PR-32 | Frontend: pole wyszukiwania miejsca z podpowiedziami (autocomplete) przy dodawaniu punktu | frontend | PR-26, PR-27 | M |

---

## Faza 4: Interaktywna mapa

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| **PR-33** | Bazowy komponent mapy (Leaflet lub MapLibre): kafelki, zoom, konfiguracja, test renderowania | frontend | PR-3, PR-7 | M |
| PR-34 | Mapa jednej podróży: markery punktów i odcinki | frontend | PR-33, PR-25 | L |
| PR-35 | Rozróżnienie transportu: kolory, ikony lub styl linii, legenda mapy | frontend | PR-34 | M |
| PR-36 | Prezentacja samolotu jako łuku, trasy samochodowej po drogach, pociągu i "inne" jako linii prostej | frontend | PR-35, PR-30 | L |
| PR-37 | Mapa wszystkich podróży użytkownika (endpoint zbiorczy `GET /api/v1/map` + widok) | backend + frontend | PR-34, PR-30 | L |
| PR-38 | Warstwa odwiedzonych państw: dane GeoJSON (np. Natural Earth), wyróżnienie krajów na mapie | frontend | PR-37, PR-31 | L |
| PR-39 | Szczegóły trasy: popup/panel po kliknięciu w punkt lub odcinek, płynne przybliżanie do wybranej trasy | frontend | PR-37 | M |

---

## Faza 5: Statystyki

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| PR-40 | Serwis i endpoint statystyk `GET /api/v1/stats?year=`: liczba podróży, kraje, kilometry, dystans wg transportu, liczba miejsc + testy | backend | PR-30, PR-31 | L |
| PR-41 | Frontend: widok statystyk (karty z liczbami, lista odwiedzonych krajów) | frontend | PR-40, PR-15 | M |
| PR-42 | Frontend: filtr roku ("cała historia" / wybrany rok) i wykres dystansu według transportu | frontend | PR-41 | M |

---

## Faza 6: Profile publiczne

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| PR-43 | Backend: publiczne endpointy (lista użytkowników z publicznym profilem, ich podróże, mapa, statystyki) i blokada dostępu do prywatnych (403/404) + testy | backend | PR-19, PR-37, PR-40 | L |
| PR-44 | Frontend: lista publicznych użytkowników i widok profilu innego użytkownika (podróże, mapa, statystyki, tylko do odczytu) | frontend | PR-43 | L |

---

## Faza 7: Jakość i wykończenie

| ID | Zadanie | Obszar | Zależy od | Rozmiar |
|---|---|---|---|---|
| PR-45 | Seeder z danymi demo (użytkownik, przykładowa podróż z Legnicy na Majorkę z README) | backend | PR-22 | M |
| PR-46 | Obsługa błędów i stanów w UI: toasty, strony 404/403, ekrany ładowania i pusty stan | frontend | PR-15 | M |
| PR-47 | Responsywność i dostępność (mobile, kontrast, nawigacja klawiaturą) | frontend | PR-41 | M |
| PR-48 | Optymalizacja: indeksy w bazie, cache wyników tras i geokodowania, eliminacja zapytań N+1 | backend | PR-30 | M |
| PR-49 | Testy end-to-end kluczowej ścieżki (rejestracja, dodanie podróży, mapa, statystyki) | frontend | PR-42 | L |
| PR-50 | Dokumentacja końcowa: aktualizacja README, opis API, instrukcja wdrożenia | docs | wszystkie | M |

---

## Co można zacząć równolegle na starcie

Gdy zespół zaczyna pracę, od razu mogą ruszyć (przy braku zależności):

- **PR-1** (repo + docker), a zaraz po nim **PR-2** (backend) i **PR-3** (frontend) w dwóch osobach,
- **PR-7** (decyzje o technologiach), niezależnie od reszty,
- **PR-28** (serwis dystansu), gdy backend jest już zainicjalizowany.

Dzięki temu na początku każdy ma co robić, a zadania blokujące (pogrubione) są rozdzielone między osoby.

## Zasada utrzymania listy

- Zrobione zadania oznaczaj w Issue (zamknięcie) i skreślaj w tabeli (`~~PR-1~~`).
- Nowe pomysły dopisuj na końcu jako kolejne numery (`PR-51`, `PR-52`, ...), nie zmieniaj numeracji istniejących.
- Zadanie `L` większe niż 2 dni dziel na mniejsze, dodając nowe numery.
