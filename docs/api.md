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
  "timestamp": "2026-10-28T12:00:00+00:00"
}
```

`timestamp` jest czasem wygenerowania odpowiedzi w formacie ISO 8601.

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
