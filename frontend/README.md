# Frontend — Vue 3

Frontend to aplikacja SPA napisana w Vue 3, TypeScript i Vite. Wszystkie żądania backendu przechodzą przez klienta API w `src/api/` i korzystają z `/api/v1`.

Lokalne uruchomienie Docker Compose, konfigurację API i adresy usług opisuje [główny README](../README.md).

## Wymagania

- Node.js `^22.18.0` lub `>=24.12.0`
- npm

## Polecenia

W kontenerze frontend:

```bash
docker compose exec frontend npm run dev
docker compose exec frontend npm run lint
docker compose exec frontend npm run test
docker compose exec frontend npm run build
```

Uruchomienie bez Dockera, z katalogu `frontend/`:

```bash
npm ci
npm run dev
```

## Kontrakt API i typy

Wspólny kontrakt OpenAPI znajduje się w [`../shared/openapi.yaml`](../shared/openapi.yaml). Typy w `src/api/generated/` są generowane na jego podstawie. Instrukcja eksportu i generowania jest w [`shared/README.md`](../shared/README.md); nie edytuj wygenerowanych plików ręcznie.
