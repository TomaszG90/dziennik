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

### `POST /api/v1/forgot-password`

Wysyła e-mail z jednorazowym linkiem do zmiany hasła. Nie wymaga uwierzytelnienia.

**Żądanie:**

```json
{
  "email": "john@example.com"
}
```

Po kliknięciu w link użytkownik trafia do widoku `/reset-password` we frontendzie z parametrami `token` i `email`.

**Odpowiedź `200 OK`:**

```json
{
  "message": "Jeśli konto z podanym adresem istnieje, otrzymasz wiadomość z linkiem do zmiany hasła."
}
```

Ta sama odpowiedź jest zwracana, gdy adres nie jest przypisany do konta, aby nie ujawniać, którzy użytkownicy są zarejestrowani. Wysłanie kolejnych wiadomości jest ograniczone do 6 żądań na minutę z jednego adresu IP; broker haseł dodatkowo ogranicza ponowne generowanie tokenu dla danego konta.

**Odpowiedź błędu walidacji `422 Unprocessable Entity`:**

Zwracana, gdy pole `email` jest nieobecne lub nie zawiera prawidłowego adresu e-mail.

### `POST /api/v1/reset-password`

Zmienia hasło przy użyciu tokenu otrzymanego e-mailem. Nie wymaga uwierzytelnienia.

**Żądanie:**

```json
{
  "email": "john@example.com",
  "token": "token-from-email",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

`password` musi spełniać domyślne reguły siły haseł aplikacji, a `password_confirmation` musi być z nim zgodne. Po udanej zmianie token resetujący i wszystkie tokeny Sanctum użytkownika zostają unieważnione.

**Odpowiedź sukcesu `200 OK`:**

```json
{
  "message": "Hasło zostało zmienione."
}
```

Nieprawidłowy, wygasły lub wykorzystany token zwraca `422 Unprocessable Entity` z błędem pola `email`. Nieprawidłowe dane żądania zwracają `422` z błędami walidacji.

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

### Podróże

Wszystkie trasy podróży wymagają uwierzytelnienia tokenem Sanctum. Użytkownik może przeglądać i modyfikować wyłącznie własne podróże.

#### `GET /api/v1/trips`

Zwraca podróże zalogowanego użytkownika, posortowane rosnąco według `start_date`.

**Odpowiedź sukcesu `200 OK`:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Wakacje w Austrii",
      "start_date": "2026-10-29",
      "end_date": "2026-11-05",
      "description": null,
      "created_at": "2026-10-29T17:00:00+00:00",
      "updated_at": "2026-10-29T17:00:00+00:00"
    }
  ]
}
```

#### `POST /api/v1/trips`

Tworzy podróż zalogowanego użytkownika.

**Żądanie:**

```json
{
  "name": "Wakacje w Austrii",
  "start_date": "2026-10-29",
  "end_date": "2026-11-05",
  "description": "Wyjazd do Wiednia"
}
```

Pola: `name` (string, wymagane, max 255 znaków), `start_date` (data, wymagana), `end_date` (data, opcjonalna, nie wcześniejsza niż `start_date`), `description` (string lub null, opcjonalne).

**Odpowiedź sukcesu `201 Created`:** obiekt podróży w polu `data`, w formacie takim jak element listy powyżej.

#### `GET /api/v1/trips/{trip}`

Zwraca jedną podróż użytkownika. Odpowiedź `200 OK` zawiera obiekt podróży w polu `data`.

#### `PUT /api/v1/trips/{trip}` i `PATCH /api/v1/trips/{trip}`

Aktualizuje podróż. Wszystkie pola są opcjonalne; przesłane pola muszą spełniać reguły tworzenia. `end_date` nie może być wcześniejsza niż aktualna lub przesłana `start_date`.

**Żądanie:**

```json
{
  "name": "Zmieniona nazwa",
  "end_date": "2026-11-06"
}
```

**Odpowiedź sukcesu `200 OK`:** zaktualizowany obiekt podróży w polu `data`.

#### `DELETE /api/v1/trips/{trip}`

Usuwa podróż. **Odpowiedź sukcesu:** `204 No Content` bez treści.

### Miejsca podróży

Wszystkie trasy miejsc wymagają uwierzytelnienia. Miejsca muszą należeć do podróży wskazanej w ścieżce; ich kolejność jest ustalana przez pole `position`.

#### `GET /api/v1/trips/{trip}/places`

Zwraca miejsca podróży posortowane rosnąco według `position`. **Odpowiedź `200 OK`:** tablica obiektów w polu `data`. Każdy obiekt zawiera `id`, `trip_id`, `name`, `latitude`, `longitude`, `position`, `country_code`, `created_at` i `updated_at`.

#### `POST /api/v1/trips/{trip}/places`

Dodaje miejsce na końcu kolejności podróży; `position` przypisywane jest automatycznie.

**Żądanie:**

```json
{
  "name": "Wien",
  "latitude": 48.2082,
  "longitude": 16.3725,
  "country_code": "AT"
}
```

`name` (string, wymagane, max 255 znaków), `latitude` (liczba od -90 do 90, wymagana), `longitude` (liczba od -180 do 180, wymagana), `country_code` (opcjonalny, dwuliterowy ciąg alfabetyczny lub null).

**Odpowiedź sukcesu `201 Created`:** utworzone miejsce w polu `data`.

#### `GET /api/v1/trips/{trip}/places/{place}`

Zwraca pojedyncze miejsce należące do podróży. **Odpowiedź `200 OK`:** obiekt miejsca w polu `data`.

#### `PATCH /api/v1/trips/{trip}/places/{place}`

Aktualizuje miejsce. Opcjonalne pola: `name`, `latitude`, `longitude`, `country_code`; podane wartości podlegają tym samym regułom co przy tworzeniu. Kolejność zmienia się przez trasę `reorder`.

**Odpowiedź sukcesu `200 OK`:** zaktualizowane miejsce w polu `data`.

#### `DELETE /api/v1/trips/{trip}/places/{place}`

Usuwa miejsce. **Odpowiedź sukcesu:** `204 No Content` bez treści.

#### `PATCH /api/v1/trips/{trip}/places/reorder`

Ustawia kolejność wszystkich miejsc w podróży.

**Żądanie:**

```json
{
  "place_ids": [3, 1, 2]
}
```

`place_ids` musi zawierać każdy identyfikator miejsca z tej podróży dokładnie raz. Nieznane, powtórzone lub niekompletne identyfikatory powodują `422 Unprocessable Entity`.

**Odpowiedź sukcesu `200 OK`:** tablica zaktualizowanych miejsc w nowej kolejności w polu `data`.

### Odcinki podróży

Wszystkie trasy odcinków wymagają uwierzytelnienia. Odcinki należą do podróży wskazanej w ścieżce; miejsca początkowe i końcowe muszą należeć do tej samej podróży.

#### `GET /api/v1/trips/{trip}/legs`

Zwraca odcinki podróży posortowane rosnąco według `position`. **Odpowiedź `200 OK`:** tablica obiektów w polu `data`. Każdy obiekt zawiera `id`, `trip_id`, `from_place_id`, `to_place_id`, `transport`, `distance`, `position`, `created_at` i `updated_at`.

#### `POST /api/v1/trips/{trip}/legs`

Dodaje odcinek na końcu kolejności podróży; `position` przypisywane jest automatycznie.

**Żądanie:**

```json
{
  "from_place_id": 1,
  "to_place_id": 2,
  "transport": "train",
  "distance": 250.5
}
```

`from_place_id` i `to_place_id` (identyfikatory miejsc z tej podróży, wymagane i różne), `transport` (wymagany; `car`, `plane`, `train` lub `other`), `distance` (opcjonalna, liczba nieujemna lub null).

**Odpowiedź sukcesu `201 Created`:** utworzony odcinek w polu `data`.

#### `GET /api/v1/trips/{trip}/legs/{leg}`

Zwraca pojedynczy odcinek należący do podróży. **Odpowiedź `200 OK`:** obiekt odcinka w polu `data`.

#### `PATCH /api/v1/trips/{trip}/legs/{leg}`

Aktualizuje odcinek. Wszystkie pola tworzenia są opcjonalne, ale podane wartości podlegają tym samym regułom.

**Odpowiedź sukcesu `200 OK`:** zaktualizowany odcinek w polu `data`.

#### `DELETE /api/v1/trips/{trip}/legs/{leg}`

Usuwa odcinek. **Odpowiedź sukcesu:** `204 No Content` bez treści.

W tych grupach brak uwierzytelnienia zwraca `401 Unauthorized`, brak dostępu do podróży `403 Forbidden`, a nieistniejąca podróż lub zasób podrzędny `404 Not Found`. Nieprawidłowe dane żądania zwracają `422 Unprocessable Entity`.

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
