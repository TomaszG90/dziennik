# Shared API contract

`openapi.yaml` is the committed OpenAPI 3.1 contract generated from Laravel routes and request validation through Scramble. Vue types are generated from that file; Android will use the same contract when API integration begins.

Scramble currently exports JSON with `php artisan scramble:export`. The frontend conversion script turns the ignored intermediate `openapi.json` into YAML and invokes Hey API to generate TypeScript definitions:

```bash
docker compose exec backend php artisan scramble:export --path=storage/app/openapi.json
cp backend/storage/app/openapi.json shared/openapi.json
cd frontend
npm run api:generate
```

In GitHub Actions the backend job exports directly to `shared/openapi.json`, then runs the same YAML and TypeScript generation.

Do not edit generated OpenAPI or TypeScript files by hand. Commit the Scramble dependency and generated contract together so CI can verify the committed files against the backend source.
