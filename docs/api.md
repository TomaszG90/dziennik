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

**Odpowiedź sukcesu `201 Created`:**

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "profile_visibility": "public",
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
      "profile_visibility": "public",
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
    "profile_visibility": "public",
    "created_at": "2026-10-29T17:00:00+00:00",
    "updated_at": "2026-10-29T17:00:00+00:00"
  }
}
```

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
    "profile_visibility": "private",
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
