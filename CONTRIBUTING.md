Ten dokument opisuje zasady pracy w repozytorium. Przeczytaj go przed pierwszym commitem.

## Struktura repozytorium

```
backend/    # Laravel: REST API (Sanctum)
frontend/   # Vue 3 SPA (Vite)
docker/     # konfiguracja środowiska Docker Compose (PHP, Nginx, Node)
docs/       # dokumentacja, opis API
mobile/     # rozwój aplikacji mobilnej (w planach)
```

Frontend komunikuje się z backendem wyłącznie przez API (`/api/v1`). Dzięki temu przyszła aplikacja mobilna użyje tych samych endpointów.

## Pierwsze kroki (Docker)

1. Sklonuj repo:
   ```bash
   git clone <url-repo>
   cd dziennik
   ```

2. Skonfiguruj pliki środowiskowe:
   ```bash
   cp backend/.env.example backend/.env
   cp frontend/.env.example frontend/.env
   ```

3. Uruchom środowisko w Dockerze:
   ```bash
   docker compose up -d --build
   ```

4. Zainicjalizuj backend:
   ```bash
   docker compose exec backend php artisan key:generate
   docker compose exec backend php artisan migrate --seed
   ```

Usługi dostępne lokalnie:
- Frontend: [http://localhost:5173](http://localhost:5173)
- Backend API: [http://localhost:8000](http://localhost:8000)

## Praca z Gitem (krok po kroku)

1. Wybierz zadanie z listy [TASK.md](TASK.md) (lub zakładki Issues) i przypisz je do siebie.
2. Zaktualizuj `main` i utwórz branch:
   ```bash
   git checkout main
   git pull
   git checkout -b feature/pr-x-krotki-opis
   ```
3. Pracuj w małych krokach, commituj często.
4. Wypchnij branch:
   ```bash
   git push -u origin feature/pr-x-krotki-opis
   ```
5. Otwórz Pull Request do `main` według szablonu z [TASK_ins.md](TASK_ins.md).
6. Poproś kogoś z zespołu o review. Nanieś poprawki.
7. Po akceptacji i zielonych testach zrób merge (squash) i usuń branch.

**Nigdy nie pushuj bezpośrednio do `main`.**

## Nazwy branchy

Format: `typ/pr-x-krotki-opis` (małe litery, myślniki, bez dwukropków i polskich znaków).
- `feature/...`: nowa funkcja
- `fix/...`: poprawka błędu
- `chore/...`: porządki, konfiguracja
- `docs/...`: dokumentacja

## Commity

Format: `typ: krótki opis w trybie rozkazującym`

Przykłady: `feat: dodaj logowanie`, `fix: popraw walidację e-maila`, `docs: uzupełnij README`.

Dozwolone typy: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.

## Zasady dla kodu

**Backend (Laravel)**
- Walidacja w Form Requests, logika biznesowa w serwisach, nie w kontrolerach.
- Odpowiedzi API przez API Resources, ze spójnym formatem JSON i właściwymi kodami HTTP.
- Endpointy pod prefiksem `/api/v1`, żeby frontend i aplikacja mobilna korzystały ze spójnego API.
- Styl kodu: `docker compose exec backend ./vendor/bin/pint`

**Frontend (Vue)**
- Vue 3, Composition API, `<script setup>`, TypeScript.
- Komponenty w PascalCase, wywołania API wydzielone do osobnego folderu (`src/api/`), nie w komponentach.
- Styl kodu: `docker compose exec frontend npm run lint`

**Dla obu**
- Do każdej nowej funkcji dodaj testy.
- Żadnych haseł, kluczy ani plików `.env` w repozytorium.

## Przed otwarciem Pull Requesta

Upewnij się, że poniższe kroki przechodzą w kontenerach:

- [ ] `docker compose exec backend php artisan test` przechodzi
- [ ] `docker compose exec backend ./vendor/bin/pint --test` przechodzi
- [ ] `docker compose exec frontend npm run lint` i `npm run test` przechodzą
- [ ] Zmiany działają lokalnie i spełniają kryteria akceptacji z zadania
- [ ] Opis PR wyjaśnia, co i dlaczego (zgodnie z [TASK_ins.md](TASK_ins.md))
- [ ] Dokumentacja API zaktualizowana (jeśli zmieniły się endpointy)
- [ ] Brak plików `.env` i sekretów w commitach
