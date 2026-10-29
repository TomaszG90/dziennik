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
