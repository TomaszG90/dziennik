# Wspólny kontrakt API

`openapi.yaml` to wersjonowany kontrakt OpenAPI 3.1 generowany z tras i walidacji Laravel przez Scramble. Typy TypeScript frontendu powstają na podstawie tego pliku; Android również będzie korzystać z tego samego kontraktu.

## Aktualizacja kontraktu

Po zmianie endpointów, requestów lub odpowiedzi:

```bash
docker compose exec backend php artisan scramble:export --path=storage/app/openapi.json
docker compose cp backend:/var/www/backend/storage/app/openapi.json shared/openapi.json
docker compose exec frontend npm run api:generate
```

Skrypt frontendu konwertuje tymczasowy `openapi.json` do `openapi.yaml` i generuje TypeScript w `frontend/src/api/generated/`. Sprawdź i commituj `openapi.yaml` oraz wygenerowane typy razem ze zmianą API. Nie edytuj ręcznie wygenerowanych plików.

Swagger UI lokalnego API: `http://localhost:8000/docs/api`. Surowy dokument JSON: `http://localhost:8000/docs/api.json`.

CI eksportuje kontrakt i sprawdza, czy pliki OpenAPI oraz typy pozostają aktualne.

## Statystyki

`GET /api/v1/stats` wymaga uwierzytelnienia i zwraca statystyki podróży użytkownika. Opcjonalny parametr `year` ogranicza wynik do podróży przecinających wskazany rok kalendarzowy. Odpowiedź zawiera liczbę podróży, unikalne kody krajów, łączny dystans w kilometrach, dystans według transportu i liczbę miejsc.
