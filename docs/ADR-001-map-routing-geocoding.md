# ADR-001: Wybór biblioteki map, API tras i geokodowania

- **Status:** Zaakceptowana
- **Data:** 2026-10-22
- **Obszar:** Frontend, backend, integracje zewnętrzne
- **Powiązane zadanie:** PR-7
- **Kontekst:** Projekt „Dziennik Podróży”

## 1. Kontekst i wymagania

Aplikacja ma prezentować pojedyncze podróże oraz wszystkie podróże użytkownika na interaktywnej mapie. Podróż składa się z punktów i odcinków, a sposób prezentacji odcinka zależy od środka transportu:

- samochód — geometria trasy po sieci dróg oraz dystans zwrócony przez usługę tras;
- samolot — łuk między punktami, a dystans liczony po kuli ziemskiej;
- pociąg i inne środki transportu — linia prosta, a dystans liczony w linii prostej.

Aplikacja wymaga również wyszukiwania miejsc z podpowiedziami podczas dodawania punktu podróży. Integracje zewnętrzne powinny być wywoływane przez backend Laravel, a nie bezpośrednio przez frontend. Pozwoli to ukryć klucze API, stosować cache, limity zapytań i ujednolicić obsługę błędów.

PostgreSQL 16 pozostaje wybraną bazą danych. Niniejsza decyzja go nie zmienia.

## 2. Decyzje

| Obszar | Wybór | Uzasadnienie |
|---|---|---|
| Biblioteka map | **Leaflet** | Lekka biblioteka do map 2D, odpowiednia dla markerów, linii, popupów, legendy i warstw GeoJSON. Wymagania projektu nie uzasadniają na tym etapie bardziej rozbudowanego renderowania wektorowego. |
| Wyznaczanie tras samochodowych | **openrouteservice (ORS) Directions API** | Udostępnia trasę i jej geometrię w formatach przydatnych do prezentacji na mapie, w tym GeoJSON. Wymaga klucza API, który będzie przechowywany wyłącznie w konfiguracji backendu. |
| Geokodowanie i podpowiedzi miejsc | **Photon API** | Jest przeznaczony do wyszukiwania miejsc i obsługi scenariuszy podpowiedzi/autocomplete. Backend udostępni własny endpoint, aby frontend nie był bezpośrednio zależny od dostawcy. |

Wybory dostawców usług zewnętrznych nie oznaczają gwarancji nieograniczonej, bezpłatnej dostępności. Przed wdrożeniem publicznym należy sprawdzić aktualne warunki, limity i zasady korzystania z wybranych instancji/usług.

## 3. Rozważane alternatywy

### 3.1 Biblioteka map: Leaflet lub MapLibre GL

**Wybrano Leaflet**, ponieważ obecny zakres to klasyczna mapa 2D z markerami, liniami, popupami i danymi GeoJSON. To prostszy punkt wyjścia dla SPA w Vue 3.

MapLibre GL pozostaje alternatywą, jeśli w przyszłości pojawi się potrzeba zaawansowanego stylowania map wektorowych, większej liczby warstw lub efektów renderowanych przez GPU. W obecnym zakresie dodatkowe możliwości nie są konieczne.

### 3.2 Trasy: openrouteservice lub OSRM

**Wybrano openrouteservice** jako usługę tras dla pierwszej wersji. API przyjmuje żądania tras i może zwracać wynik jako GeoJSON, co upraszcza przekazanie geometrii do warstwy mapy.

OSRM jest alternatywą open source, którą można uruchomić samodzielnie. Jego publiczne instancje demonstracyjne nie powinny być traktowane jako gwarantowana usługa produkcyjna. Samodzielne utrzymywanie OSRM oznacza dodatkowe obowiązki operacyjne, aktualizowanie danych drogowych i monitorowanie infrastruktury.

### 3.3 Geokodowanie: Photon lub Nominatim

**Wybrano Photon** dla wyszukiwania miejsc z podpowiedziami, ponieważ autocomplete jest wymaganiem interfejsu. Publiczna instancja Photon również może mieć własne ograniczenia i nie należy zakładać gwarantowanej dostępności.

Nominatim jest alternatywą dla wyszukiwania i geokodowania odwrotnego, ale publiczny serwer `nominatim.openstreetmap.org` ma restrykcyjną politykę użycia: między innymi nie zezwala na używanie go do autocomplete po stronie klienta. Nie należy więc budować wymaganego pola podpowiedzi na publicznej instancji Nominatim.

## 4. Architektura integracji

Frontend Vue 3 komunikuje się wyłącznie z API Laravel pod prefiksem `/api/v1`. Backend odpowiada za komunikację z dostawcami zewnętrznymi.

Proponowane endpointy aplikacji:

- `GET /api/v1/geocode?q={query}` — wyszukiwanie miejsc i podpowiedzi;
- wewnętrzny serwis backendu do wyznaczania trasy samochodowej, wykorzystywany podczas zapisu lub przeliczenia odcinka.

Klucz ORS (np. `ROUTING_API_KEY`) powinien znajdować się w `backend/.env` i nie może być udostępniany frontendowi ani commitowany do repozytorium. Adresy usług powinny być konfigurowalne, aby umożliwić zmianę dostawcy bez przebudowy logiki domenowej.

### Przepływ wyszukiwania miejsca

1. Użytkownik wpisuje tekst w polu miejsca.
2. Frontend wysyła zapytanie do `GET /api/v1/geocode?q=...`.
3. Backend sprawdza poprawność i długość zapytania, stosuje ograniczanie częstotliwości i sprawdza cache.
4. Jeśli nie ma wyniku w cache, backend odpytuje Photon.
5. Backend mapuje odpowiedź dostawcy na stabilny format aplikacji i zwraca listę wyników.
6. Frontend wyświetla podpowiedzi; po wyborze miejsca zapisuje nazwę i współrzędne.

### Przepływ wyznaczania trasy samochodowej

1. Backend pobiera współrzędne początku i końca odcinka.
2. Wywołuje ORS Directions API z kluczem przechowywanym po stronie serwera.
3. Waliduje odpowiedź i zapisuje dystans oraz geometrię trasy, jeśli wynik jest poprawny.
4. Frontend otrzymuje dane odcinka z API aplikacji i wyświetla geometrię na mapie Leaflet.
5. Przy błędzie usługi aplikacja nie powinna zapisywać fałszywego dystansu; powinna zwrócić kontrolowany błąd albo oznaczyć odcinek do ponownego przeliczenia.

## 5. Wymagania implementacyjne

- Zewnętrzne API są wywoływane przez backend, nie bezpośrednio przez Vue.
- Klucze i sekrety przechowujemy w zmiennych środowiskowych; nie umieszczamy ich w repozytorium.
- Endpoint geokodowania waliduje `q`, ogranicza liczbę wyników i stosuje rate limiting.
- Dla powtarzających się zapytań geokodowania i tras stosujemy cache, z rozsądnym czasem życia.
- Ustawiamy timeouty i obsługujemy błędy sieci, limity dostawcy, brak wyników i nieprawidłowe odpowiedzi.
- Odpowiedź backendu powinna być niezależna od specyficznego formatu dostawcy.
- Na mapie należy zachować wymagane rozróżnienie środków transportu.
- Dystanse samolotowe i liniowe obliczamy lokalnie, bez wywoływania API tras.
- Należy respektować licencje danych, wymagane atrybucje i warunki korzystania z dostawców map oraz usług.

## 6. Konsekwencje

### Korzyści

- Prosta implementacja mapy 2D zgodna z obecnym zakresem.
- Geometria tras samochodowych może być renderowana jako GeoJSON.
- Wyszukiwanie miejsc jest oddzielone od interfejsu i może zostać przeniesione do innego dostawcy.
- Klucz API tras pozostaje po stronie backendu.
- Cache i limity zapytań ograniczają liczbę zewnętrznych wywołań.

### Koszty i ryzyka

- ORS wymaga klucza API i podlega limitom oraz warunkom usługi.
- Dostępność i limity publicznej instancji Photon nie są gwarantowane.
- Zewnętrzne API mogą zwracać błędy lub nie mieć danych dla niektórych lokalizacji.
- Wymagana jest atrybucja zgodna z warunkami dostawców i źródeł danych.
- Zmiana dostawcy będzie wymagała implementacji adaptera, ale nie powinna wymagać zmian w interfejsie API aplikacji.

## 7. Kryteria akceptacji decyzji

- [ ] W `docs/` znajduje się ten dokument opisujący wybory i alternatywy.
- [ ] Z dokumentu wynika, że mapę obsługuje Leaflet, trasy samochodowe ORS, a wyszukiwanie miejsc Photon.
- [ ] Opisano integrację przez backend, cache, rate limiting, ochronę kluczy i obsługę błędów.
- [ ] PostgreSQL pozostaje bez zmian.
- [ ] README i TASK.md mogą wskazywać ten dokument jako źródło decyzji technologicznych.

## 8. Źródła

- Leaflet — dokumentacja: https://leafletjs.com/reference.html
- MapLibre GL JS — dokumentacja: https://maplibre.org/maplibre-gl-js/docs/
- openrouteservice — Directions API: https://openrouteservice.org/dev/
- openrouteservice — dokumentacja żądań i typów odpowiedzi: https://github.com/GIScience/openrouteservice/blob/main/docs/api-reference/endpoints/directions/requests-and-return-types.md
- OSRM — HTTP API: https://project-osrm.org/docs/v26.6.1/http
- Photon — projekt i dokumentacja: https://github.com/komoot/photon
- Nominatim — polityka użycia publicznej instancji: https://operations.osmfoundation.org/policies/nominatim/

---

**Uwaga wdrożeniowa:** przed uruchomieniem produkcyjnym należy zweryfikować aktualne limity i warunki ORS oraz wybranej instancji Photon. W razie potrzeby można zmienić dostawcę bez zmiany kontraktu endpointów aplikacji.
