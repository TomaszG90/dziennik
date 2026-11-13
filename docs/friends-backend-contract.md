# Kontrakt backendu: wyszukiwanie i znajomi

> **Status: propozycja — endpointy relacji znajomości nie są jeszcze zaimplementowane.**
> Frontend pokazuje przycisk „Dodaj znajomego” w stanie nieaktywnym, dopóki backend nie
> udostępni wysyłania i obsługi zaproszeń.

## Zakres

Kontrakt opisuje wyszukiwanie publicznych profili oraz pełny cykl zaproszenia do znajomych.
Wszystkie endpointy poza istniejącym wyszukiwaniem publicznych profili wymagają tokenu
Sanctum (`Authorization: Bearer <token>`). Identyfikatory są identyfikatorami użytkowników
lub zaproszeń zwracanymi przez API.

Model użytkownika nie ma obecnie osobnego, unikalnego loginu/nazwy użytkownika. Do czasu
wprowadzenia takiego pola wyszukiwanie parametrem `search` dopasowuje publicznie widoczne
pola `name`, `first_name`, `last_name` i `city`.

## Istniejące wyszukiwanie profili

### `GET /api/v1/public/users?search={query}&page={page}`

Endpoint publiczny i już zaimplementowany. `search` jest opcjonalnym ciągiem do 100 znaków.
Wyszukiwanie może dopasować wyłącznie pola włączone w ustawieniach prywatności profilu.
Wyniki zawierają tylko profile z `profile_visibility: public`, są stronicowane po 20
rekordów i nie ujawniają e-maila.

Zakładka „Znajomi” nie wysyła żądania listy przy otwarciu; żądanie wyszukiwania jest
wykonywane po wysłaniu formularza. Puste wyszukiwanie nie powinno zwracać katalogu profili.

## Proponowane endpointy relacji

### `GET /api/v1/friends`

Zwraca zaakceptowane relacje bieżącego użytkownika. Każdy element zawiera drugiego
uczestnika relacji (publiczny zasób profilu) oraz datę utworzenia. Lista nie zawiera
zaproszeń oczekujących ani odrzuconych.

Przykład `200 OK`:

```json
{
  "data": [
    {
      "id": 82,
      "name": "Jan Kowalski",
      "avatar_path": "3.png",
      "created_at": "2026-11-13T10:00:00Z"
    }
  ]
}
```

### `GET /api/v1/friend-requests?direction=incoming|outgoing`

Zwraca oczekujące zaproszenia do użytkownika (`incoming`) albo wysłane przez niego
(`outgoing`). `direction` jest wymagany. Każdy rekord zawiera `id`, `status: "pending"`,
daty oraz bezpieczny publiczny zasób drugiego uczestnika.

### `POST /api/v1/friend-requests`

Wysyła zaproszenie do użytkownika z `user_id`.

Żądanie:

```json
{
  "user_id": 82
}
```

Odpowiedź `201 Created`:

```json
{
  "data": {
    "id": 12,
    "status": "pending",
    "sender_id": 7,
    "recipient_id": 82,
    "created_at": "2026-11-13T10:00:00Z"
  }
}
```

Walidacja i reguły:

- nie można wysłać zaproszenia do siebie (`422`);
- odbiorca musi istnieć i mieć profil publiczny; użytkownik nieistniejący lub prywatny
  zwraca `404`, aby nie ujawniać prywatnych kont;
- nie można utworzyć drugiej oczekującej ani zaakceptowanej relacji między tą samą parą
  użytkowników (`409 Conflict`);
- jeśli istnieje oczekujące zaproszenie przychodzące od adresata, backend może zwrócić
  istniejące zaproszenie i wskazać wymagane zaakceptowanie go zamiast tworzenia duplikatu.

### `PATCH /api/v1/friend-requests/{request}`

Odbiorca zaproszenia może je zaakceptować albo odrzucić.

Żądanie:

```json
{
  "action": "accept"
}
```

`action` przyjmuje `accept` lub `decline`. Odpowiedź `200 OK` zawiera zaktualizowane
zaproszenie. Tylko odbiorca może wykonać tę operację; brak zasobu zwraca `404`, brak
uprawnień `403`, a nieoczekiwany status zasobu (np. zaproszenie już obsłużone) `409`.
Po zaakceptowaniu relacja pojawia się w `GET /friends`.

### `DELETE /api/v1/friend-requests/{request}`

Nadawca może wycofać własne oczekujące zaproszenie. Zwraca `204 No Content`. Odbiorca nie
może wycofać zaproszenia tą trasą; zasób nieistniejący zwraca `404`, brak uprawnień `403`,
a zaproszenie nieoczekujące `409`.

### `DELETE /api/v1/friends/{user}`

Usuwa zaakceptowaną relację z użytkownikiem. Usunięcie działa symetrycznie dla obu stron
i zwraca `204 No Content`. Jeśli relacja nie istnieje, odpowiedź to `404`.

## Model danych i integralność

Proponowana tabela `friend_requests`:

| Kolumna | Typ / reguła |
| --- | --- |
| `id` | klucz główny |
| `sender_id` | FK do `users`, kasowanie kaskadowe |
| `recipient_id` | FK do `users`, kasowanie kaskadowe |
| `status` | `pending`, `accepted` lub `declined` |
| `created_at`, `updated_at` | znaczniki czasu |

Wymagane reguły:

- `sender_id` i `recipient_id` są różne;
- istnieje najwyżej jedna oczekująca lub zaakceptowana relacja dla nieuporządkowanej pary
  użytkowników — zapobiega to równoległym zaproszeniom w przeciwnych kierunkach;
- akceptacja zaproszenia i zmiana statusu są atomowe;
- odrzucone zaproszenie nie jest relacją znajomych; ponowne zaproszenie po odrzuceniu może
  utworzyć nowy rekord lub wznowić poprzedni, zgodnie z przyjętą polityką;
- API nigdy nie zwraca e-maila ani pól profilu wyłączonych w `privacy_settings`;
- usunięcie użytkownika usuwa jego zaproszenia i relacje.

## Format błędów

Walidacja używa standardowej odpowiedzi Laravel `422` (`message`, `errors`). Brak
uwierzytelnienia zwraca `401`, brak uprawnień `403`, brak/ukryty zasób `404`, a konflikt
stanu relacji `409`. Błędy nie mogą ujawniać prywatnych danych użytkownika.

## Wdrożenie we frontendzie

Po wdrożeniu endpointów należy:

1. dodać do klienta API wysyłanie, listowanie, akceptację, odrzucenie i wycofanie zaproszeń;
2. włączyć przycisk „Dodaj znajomego” oraz prezentować stany `pending`, `friends` i błąd;
3. w zakładce „Znajomi” pokazać zaakceptowanych znajomych i osobne listy zaproszeń
   przychodzących/wysłanych;
4. dodać testy uprawnień, duplikatów, prywatnych kont, konfliktów statusu i operacji obu stron.
