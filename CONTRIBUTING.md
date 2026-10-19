Ten dokument opisuje zasady pracy w repozytorium. Przeczytaj go przed pierwszym commitem.

## Struktura repozytorium

```
backend/    # Laravel: REST API (Sanctum)
frontend/   # Vue 3 SPA (Vite)
docs/       # dokumentacja, opis API
mobile/     # rozwój aplikacji mobilnej
```

Frontend komunikuje się z backendem wyłącznie przez API. Dzięki temu przyszła aplikacja mobilna użyje tych samych endpointów.

## Pierwsze kroki

1. Sklonuj repo: `git clone <url-repo>`
2. Backend:
```
   cd backend
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   php artisan serve
```
3. Frontend (w drugim terminalu):
```
   cd frontend
   npm install
   cp .env.example .env
   npm run dev
```

## Praca z Gitem (krok po kroku)

1. Wybierz zadanie z zakładki Issues i przypisz je do siebie.
2. Zaktualizuj `main` i utwórz branch:
```
   git checkout main
   git pull
   git checkout -b feature/krotki-opis
```
3. Pracuj w małych krokach, commituj często.
4. Wypchnij branch: `git push -u origin feature/krotki-opis`
5. Otwórz Pull Request do `main` i opisz, co zmieniłeś i jak to sprawdzić.
6. Poproś kogoś z zespołu o review. Nanieś poprawki.
7. Po akceptacji i zielonych testach zrób merge (squash) i usuń branch.

**Nigdy nie pushuj bezpośrednio do `main`.**

## Nazwy branchy

- `feature/...`: nowa funkcja
- `fix/...`: poprawka błędu
- `chore/...`: porządki, konfiguracja
- `docs/...`: dokumentacja

## Commity

Format: `typ: krótki opis w trybie rozkazującym`

Przykłady: `feat: dodaj logowanie`, `fix: popraw walidację e-maila`, `docs: uzupełnij README`.

Typy: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.

## Zasady dla kodu

**Backend (Laravel)**
- Walidacja w Form Requests, logika biznesowa w serwisach, nie w kontrolerach.
- Odpowiedzi API przez API Resources, ze spójnym formatem JSON i właściwymi kodami HTTP.
- Endpointy pod prefiksem `/api/v1`, żeby mobilka mogła korzystać z wersjonowanego API.
- Styl kodu: `./vendor/bin/pint`

**Frontend (Vue)**
- Vue 3, Composition API, `<script setup>`.
- Komponenty w PascalCase, wywołania API wydzielone do osobnego folderu (np. `src/api/`), nie w komponentach.
- Styl kodu: `npm run lint`

**Dla obu**
- Do każdej nowej funkcji dodaj testy.
- Żadnych haseł, kluczy ani plików `.env` w repozytorium.

## Przed otwarciem Pull Requesta

- [ ] `php artisan test` przechodzi
- [ ] `npm run lint` i `npm run test` przechodzą
- [ ] Zmiany działają lokalnie
- [ ] Opis PR wyjaśnia, co i dlaczego
- [ ] Dokumentacja API zaktualizowana (jeśli zmieniły się endpointy)
