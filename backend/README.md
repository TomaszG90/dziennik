# Backend — Laravel API

Backend udostępnia wersjonowane REST API pod `/api/v1`. Projekt używa Laravel 12, Sanctum i PostgreSQL 16. Lokalne środowisko Docker oraz konfigurację zmiennych opisuje [główny README](../README.md).

## Wymagania

- PHP 8.3+
- Composer
- PostgreSQL 16 (lub środowisko z `docker compose`)

## Uruchomienie lokalne

Z katalogu głównego repozytorium:

```bash
docker compose up -d --build
docker compose exec backend composer install
docker compose exec backend php artisan migrate --seed
```

Jeśli `APP_KEY` jest pusty, wygeneruj go poleceniem `docker compose exec backend php artisan key:generate`; istniejącego klucza nie zmieniaj. W `backend/.env` skonfiguruj dostęp do PostgreSQL i lokalną pocztę; dla pracy bez SMTP użyj `MAIL_MAILER=log`.

API i dokumentacja Swagger są dostępne pod `http://localhost:8000` i `http://localhost:8000/docs/api`.

## Testy i formatowanie

```bash
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
```

## Kontrakt API

Źródłem kontraktu jest backend i Laravel Scramble. Eksport OpenAPI oraz generowanie wspólnych artefaktów opisano w [`shared/README.md`](../shared/README.md). Nie edytuj ręcznie `shared/openapi.yaml` ani wygenerowanych typów frontendu.
