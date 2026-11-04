# API — szkic dokumentacji

## Zakres i wersjonowanie

API aplikacji jest udostępniane pod prefiksem `/api/v1`. Nowe endpointy aplikacyjne powinny być dodawane w tej przestrzeni wersji. Dokument opisuje endpointy istniejące obecnie; funkcje oznaczone w `TASK.md` jako planowane nie są jeszcze dostępne.

## Endpointy

### `GET /api/v1/health`

Kontrolny endpoint dostępności API. Nie wymaga uwierzytelnienia.

Przykładowa odpowiedź `200 OK`:

```json
{
  "status": "ok",
  "timestamp": "2026-10-29T17:00:00+00:00"
}
```

`timestamp` jest czasem wygenerowania odpowiedzi w formacie ISO 8601.

### `POST /api/v1/auth/register`

Rejestracja nowego użytkownika. Nie wymaga uwierzytelnienia.

**Żądanie:**

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "profile_visibility": "public"
}
```

Pola:
- `name` (string, required): imię i nazwisko użytkownika, max 255 znaków
- `email` (string, required): adres e-mail, musi być unikalny w systemie, max 255 znaków
- `password` (string, required): hasło, musi być potwierdzone w `password_confirmation`
- `password_confirmation` (string, required): potwierdzenie hasła
- `profile_visibility` (string, required): widoczność profilu, wartości: `public` lub `private`

Podczas rejestracji backend losuje plik PNG z `backend/public/avatars` i zapisuje jego nazwę w `avatar_path` (np. `3.png`).

**Odpowiedź sukcesu `201 Created`:**

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "first_name": null,
    "last_name": null,
    "city": null,
    "birth_date": null,
    "avatar_path": "3.png",
    "profile_visibility": "public",
    "privacy_settings": {
      "name": false,
      "email": false,
      "firstName": false,
      "lastName": false,
      "city": false,
      "birthDate": false,
      "avatar": false
    },
    "created_at": "2026-10-29T17:00:00+00:00",
    "updated_at": "2026-10-29T17:00:00+00:00"
  }
}
```

**Odpowiedź błędu walidacji `422 Unprocessable Entity`:**

```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### `POST /api/v1/auth/login`

Logowanie użytkownika. Nie wymaga uwierzytelnienia. Zwraca token Sanctum do użycia w kolejnych żądaniach.

**Żądanie:**

```json
{
  "email": "john@example.com",
  "password": "Password123!"
}
```

Pola:
- `email` (string, required): adres e-mail użytkownika
- `password` (string, required): hasło użytkownika

**Odpowiedź sukcesu `200 OK`:**

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "first_name": null,
      "last_name": null,
      "city": null,
      "birth_date": null,
      "avatar_path": "3.png",
      "profile_visibility": "public",
      "privacy_settings": {
        "name": false,
        "email": false,
        "firstName": false,
        "lastName": false,
        "city": false,
        "birthDate": false,
        "avatar": false
      },
      "created_at": "2026-10-29T17:00:00+00:00",
      "updated_at": "2026-10-29T17:00:00+00:00"
    },
    "token": "1|abcdef123456..."
  }
}
```

`token` to plain-text token Sanctum, który należy używać w nagłówku `Authorization: Bearer {token}`.

**Odpowiedź błędu walidacji `422 Unprocessable Entity`:**

```json
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "email": ["The provided credentials are incorrect."]
  }
}
```

### `POST /api/v1/auth/logout`

Wylogowanie użytkownika. Wymaga uwierzytelnienia (token w nagłówku `Authorization: Bearer {token}`). Usuwa bieżący token.

**Żądanie:**

Brak danych w ciele żądania. Wymaga nagłówka `Authorization: Bearer {token}`.

**Odpowiedź sukcesu `200 OK`:**

```json
{
  "message": "Successfully logged out"
}
```

**Odpowiedź błędu autoryzacji `401 Unauthorized`:**

```json
{
  "message": "Unauthenticated."
}
```

### `GET /api/v1/me`

Podgląd danych profilu zalogowanego użytkownika. Wymaga uwierzytelnienia (token w nagłówku `Authorization: Bearer {token}`).

**Żądanie:**

Brak danych w ciele żądania. Wymaga nagłówka `Authorization: Bearer {token}`.

**Odpowiedź sukcesu `200 OK`:**

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "first_name": null,
    "last_name": null,
    "city": null,
    "birth_date": null,
    "avatar_path": "3.png",
    "profile_visibility": "public",
    "privacy_settings": {
      "name": false,
      "email": false,
      "firstName": false,
      "lastName": false,
      "city": false,
      "birthDate": false,
      "avatar": false
    },
    "created_at": "2026-10-29T17:00:00+00:00",
    "updated_at": "2026-10-29T17:00:00+00:00"
  }
}
```

`privacy_settings` zawiera ustawienia widoczności jako wartości logiczne. Jego klucze (`name`, `email`, `firstName`, `lastName`, `city`, `birthDate`, `avatar`) odpowiadają bezpośrednio kluczom frontendu. Pola profilu są zapisane w bazie jako `first_name`, `last_name`, `city`, `birth_date` i `avatar_path`; `firstName` odpowiada `first_name`, `birthDate` — `birth_date`, a `avatar` — `avatar_path`.

**Odpowiedź błędu autoryzacji `401 Unauthorized`:**

```json
{
  "message": "Unauthenticated."
}
```

### `PATCH /api/v1/me`

Edycja danych profilu zalogowanego użytkownika. Wymaga uwierzytelnienia (token w nagłówku `Authorization: Bearer {token}`). Wszystkie pola są opcjonalne — aktualizowane są tylko przesłane.

**Żądanie:**

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!",
  "profile_visibility": "private"
}
```

Pola:
- `name` (string, optional): imię i nazwisko użytkownika, max 255 znaków
- `email` (string, optional): adres e-mail, musi być unikalny w systemie, max 255 znaków
- `password` (string, optional): nowe hasło, musi być potwierdzone w `password_confirmation`
- `password_confirmation` (string, wymagane przy zmianie `password`): potwierdzenie hasła
- `profile_visibility` (string, optional): widoczność profilu, wartości: `public` lub `private`

**Odpowiedź sukcesu `200 OK`:**

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "first_name": null,
    "last_name": null,
    "city": null,
    "birth_date": null,
    "avatar_path": "3.png",
    "profile_visibility": "private",
    "privacy_settings": {
      "name": false,
      "email": false,
      "firstName": false,
      "lastName": false,
      "city": false,
      "birthDate": false,
      "avatar": false
    },
    "created_at": "2026-10-29T17:00:00+00:00",
    "updated_at": "2026-10-31T10:00:00+00:00"
  }
}
```

**Odpowiedź błędu walidacji `422 Unprocessable Entity`:**

```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

**Odpowiedź błędu autoryzacji `401 Unauthorized`:**

```json
{
  "message": "Unauthenticated."
}
```

### `POST /api/v1/trips/{trip}/places`

Dodanie punktu do podróży. Nowy punkt otrzymuje kolejną pozycję na liście punktów. Wymaga uwierzytelnienia (token w nagłówku `Authorization: Bearer {token}`) i jest dostępny wyłącznie dla właściciela podróży.

**Parametry ścieżki:**
- `trip` (integer, wymagany): identyfikator podróży zalogowanego użytkownika

**Żądanie:**

```json
{
  "name": "Wien",
  "latitude": 48.2082,
  "longitude": 16.3725,
  "country_code": "AT"
}
```

Pola:
- `name` (string, required): nazwa punktu, max 255 znaków
- `latitude` (number, required): szerokość geograficzna w stopniach dziesiętnych, od -90 do 90
- `longitude` (number, required): długość geograficzna w stopniach dziesiętnych, od -180 do 180
- `country_code` (string, optional): kod kraju w formacie ISO 3166-1 alpha-2

Kod kraju przesłany w żądaniu ma pierwszeństwo i nie powoduje zapytania do dostawcy geokodowania; zwykle pochodzi z odpowiedzi `GET /api/v1/geocode`. Backend normalizuje go do wielkich liter, więc wartość `at` jest zapisywana jako `AT`.

Gdy `country_code` nie zostanie przesłany, backend ustala kraj na podstawie współrzędnych i zapisuje go przy punkcie. Jeżeli dostawca geokodowania jest niedostępny, punkt nadal jest zapisywany, a `country_code` pozostaje `null` — zapis punktu nie jest blokowany przez zewnętrzną usługę.

**Odpowiedź sukcesu `201 Created`:**

```json
{
  "data": {
    "id": 7,
    "trip_id": 3,
    "name": "Wien",
    "latitude": "48.2082000",
    "longitude": "16.3725000",
    "position": 1,
    "country_code": "AT",
    "created_at": "2026-11-04T10:00:00+00:00",
    "updated_at": "2026-11-04T10:00:00+00:00"
  }
}
```

Pola punktu:
- `latitude` i `longitude` (string): współrzędne w stopniach dziesiętnych z 7 miejscami po przecinku
- `position` (integer): kolejność punktu w podróży, liczona od 1
- `country_code` (string|null): kod kraju w formacie ISO 3166-1 alpha-2 albo `null`, gdy kraju nie udało się ustalić

**Odpowiedź błędu walidacji `422 Unprocessable Entity`:**

```json
{
  "message": "The country code field must be 2 characters.",
  "errors": {
    "country_code": ["The country code field must be 2 characters."]
  }
}
```

**Odpowiedź błędu autoryzacji `401 Unauthorized`:**

```json
{
  "message": "Unauthenticated."
}
```

Gdy podróż należy do innego użytkownika, endpoint zwraca `403 Forbidden`. Format błędów opisuje sekcja „Błędy”.

### `PATCH /api/v1/trips/{trip}/places/{place}`

Edycja punktu podróży. Wymaga uwierzytelnienia (token w nagłówku `Authorization: Bearer {token}`) i jest dostępny wyłącznie dla właściciela podróży. Wszystkie pola są opcjonalne — aktualizowane są tylko przesłane.

**Parametry ścieżki:**
- `trip` (integer, wymagany): identyfikator podróży zalogowanego użytkownika
- `place` (integer, wymagany): identyfikator punktu należącego do wskazanej podróży

**Żądanie:**

```json
{
  "name": "Wien Zentrum",
  "latitude": 48.2100,
  "longitude": 16.3700
}
```

Pola:
- `name` (string, optional): nazwa punktu, max 255 znaków
- `latitude` (number, optional): szerokość geograficzna w stopniach dziesiętnych, od -90 do 90
- `longitude` (number, optional): długość geograficzna w stopniach dziesiętnych, od -180 do 180
- `country_code` (string|null, optional): kod kraju w formacie ISO 3166-1 alpha-2

Przesłany `country_code` ma pierwszeństwo przed ustalaniem kraju ze współrzędnych i jest normalizowany do wielkich liter. Wartość `null` w tym polu oznacza ponowne ustalenie kraju ze współrzędnych.

Gdy `country_code` nie zostanie przesłany, a zmienią się współrzędne punktu, backend ustala kraj ponownie i nadpisuje zapisaną wartość. Zmiana wyłącznie nazwy punktu zachowuje dotychczasowy kod kraju, więc taka edycja nie powoduje zapytania do dostawcy geokodowania. Jeżeli dostawca jest niedostępny, odpowiedź nadal ma status `200 OK`, a `country_code` przyjmuje wartość `null`.

**Odpowiedź sukcesu `200 OK`:**

```json
{
  "data": {
    "id": 7,
    "trip_id": 3,
    "name": "Wien Zentrum",
    "latitude": "48.2100000",
    "longitude": "16.3700000",
    "position": 1,
    "country_code": "AT",
    "created_at": "2026-11-04T10:00:00+00:00",
    "updated_at": "2026-11-04T10:05:00+00:00"
  }
}
```

**Odpowiedź błędu walidacji `422 Unprocessable Entity`:**

```json
{
  "message": "The latitude field must be between -90 and 90.",
  "errors": {
    "latitude": ["The latitude field must be between -90 and 90."]
  }
}
```

**Odpowiedź błędu autoryzacji `401 Unauthorized`:**

```json
{
  "message": "Unauthenticated."
}
```

Punkt spoza wskazanej podróży kończy się odpowiedzią `404 Not Found`, a podróż innego użytkownika — `403 Forbidden`. Format błędów opisuje sekcja „Błędy”.

### `GET /api/v1/geocode`

Wyszukiwanie miejsc na potrzeby podpowiedzi przy dodawaniu punktu podróży. Nie wymaga uwierzytelnienia. Backend odpytuje dostawcę geokodowania (Photon) i zwraca wyniki w formacie aplikacji, niezależnym od dostawcy, dzięki czemu zmiana dostawcy nie wymaga zmian po stronie klienta.

**Żądanie:**

Parametry zapytania:
- `q` (string, wymagany): szukana fraza, od 2 do 255 znaków

Przykład: `GET /api/v1/geocode?q=Wien`

**Odpowiedź sukcesu `200 OK`:**

```json
{
  "data": [
    {
      "name": "Wien",
      "latitude": 48.2082,
      "longitude": 16.3725,
      "country_code": "AT"
    }
  ]
}
```

Pola wyniku:
- `name` (string|null): nazwa miejsca zwrócona przez dostawcę
- `latitude` (number|null): szerokość geograficzna w stopniach dziesiętnych
- `longitude` (number|null): długość geograficzna w stopniach dziesiętnych
- `country_code` (string|null): kod kraju w formacie ISO 3166-1 alpha-2

Lista zawiera maksymalnie 10 wyników. Brak trafień kończy się odpowiedzią `200 OK` z pustą tablicą (`{"data": []}`), co pozwala klientowi odróżnić brak wyników od błędu. Wyniki trafiają do cache na 24 godziny, natomiast błędów dostawcy nie zapisujemy w cache.

**Odpowiedź błędu walidacji `422 Unprocessable Entity`:**

```json
{
  "message": "The q field must be at least 2 characters.",
  "errors": {
    "q": ["The q field must be at least 2 characters."]
  }
}
```

**Odpowiedź przekroczenia limitu `429 Too Many Requests`:**

Endpoint jest ograniczony do 60 zapytań na minutę z jednego adresu IP. Odpowiedź zawiera nagłówek `Retry-After` z liczbą sekund do kolejnej próby.

```json
{
  "message": "Too Many Attempts."
}
```

**Odpowiedź błędu dostawcy `502 Bad Gateway`:**

Gdy dostawca geokodowania jest niedostępny (błąd sieci, przekroczenie czasu, odpowiedź inna niż `2xx`) albo zwróci odpowiedź niezgodną z oczekiwanym formatem, endpoint zwraca `502 Bad Gateway`. Szczegóły odpowiedzi dostawcy nie są ujawniane klientowi.

Klient powinien traktować ten kod jako błąd przejściowy i ponowić żądanie później, a nie jako brak wyników — pustą listę zwraca wyłącznie poprawne wyszukiwanie bez trafień.

```json
{
  "message": "Usługa geokodowania jest chwilowo niedostępna."
}
```

## Błędy

Błędy tras API są zwracane jako JSON. Dla nieistniejącej trasy odpowiedź ma status `404 Not Found` i zawiera komunikat:

```json
{
  "message": "Zasób nie został znaleziony."
}
```

Pozostałe błędy używają standardowego formatu obsługi wyjątków Laravel, chyba że endpoint lub walidacja określają inaczej. Klient powinien opierać obsługę błędu na kodzie HTTP i dostępnych polach odpowiedzi; treść komunikatu może być prezentowana użytkownikowi.

## Endpointy spoza wersjonowanego API

Laravel udostępnia również chronioną trasę `GET /api/user` z middleware Sanctum. Jest to trasa szkieletowa poza `/api/v1`, a nie część docelowego kontraktu wersjonowanego API.

## Utrzymywanie dokumentacji

Każda zmiana endpointu lub jego kontraktu — ścieżki, metody HTTP, uwierzytelniania, parametrów, formatu żądania lub odpowiedzi, kodów statusu albo błędów — musi aktualizować ten dokument w tym samym Pull Requeście. Pull Request powinien zawierać dokumentację wszystkich dodanych lub zmienionych endpointów. Przy dodawaniu funkcji aktualizuj opis przed scaleniem, tak aby dokumentacja odpowiadała kodowi w `main`.
