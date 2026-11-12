# Shared API contract

`openapi.yaml` is the committed OpenAPI 3.1 contract generated from Laravel routes and request validation through Scramble. Vue types are generated from that file; Android will use the same contract when API integration begins.

Scramble currently exports JSON with `php artisan scramble:export`. The frontend conversion script turns the ignored intermediate `openapi.json` into YAML and invokes Hey API to generate TypeScript definitions:

```bash
docker compose exec backend php artisan scramble:export --path=storage/app/openapi.json
cp backend/storage/app/openapi.json shared/openapi.json
cd frontend
npm run api:generate
```

The interactive Swagger UI for the local API is available at `http://localhost:8000/docs/api`; the raw OpenAPI JSON is at `http://localhost:8000/docs/api.json`.

In GitHub Actions the backend job exports directly to `shared/openapi.json`, then runs the same YAML and TypeScript generation.

Do not edit generated OpenAPI or TypeScript files by hand. Commit the Scramble dependency and generated contract together so CI can verify the committed files against the backend source.

## Statistics

`GET /stats` is available to authenticated users and returns statistics for their own trips. The optional `year` query parameter accepts a calendar year from 1900 through next year. A trip is included when its start or end date falls in that year; all places and legs belonging to an included trip contribute to its statistics.

The response contains `trips_count`, unique ISO country codes in `countries`, `total_distance` in kilometers, `distance_by_transport` in kilometers grouped by transport type, and `places_count`. The endpoint is documented in `openapi.yaml` and its generated TypeScript types are in `frontend/src/api/generated/`.
