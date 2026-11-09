# TASK_ins.md: instrukcja wykonywania zadań

Ta instrukcja opisuje, **jak krok po kroku wykonać zadanie** od wybrania go z listy do zamknięcia. Jeśli będziesz się jej trzymać, nikt w zespole nie będzie miał wątpliwości, co robić i w jakiej kolejności.

Powiązane pliki:
- [TASK.md](TASK.md): lista zadań oraz definicje Ready / Done
- [TASK2.md](TASK2.md): szczegółowa faza rozwoju aplikacji Android
- [CONTRIBUTING.md](CONTRIBUTING.md): zasady Git, commity, styl kodu
- [README.md](README.md): opis projektu i uruchomienie w Dockerze

## Szybka ściągawka
```bash
# start
git checkout main && git pull
git checkout -b feature/pr-x-krotki-opis

# praca
git add path/to/file path/to/other-file
git commit -m "feat: krótki opis"

# przed PR (sprawdzenie w kontenerach)
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
docker compose exec frontend npm run lint
docker compose exec frontend npm run test
docker compose exec frontend npm run build

# Android
cd mobile
./gradlew lintDebug testDebugUnitTest assembleDebug
cd ..

# wysyłka
git push -u origin feature/pr-x-krotki-opis
# → otwórz Pull Request do main

W PowerShell Android uruchom poleceniem `Set-Location mobile; .\gradlew.bat lintDebug testDebugUnitTest assembleDebug; Set-Location ..`.

Sposób 1: Przez interfejs GitHub (najpopularniejszy)
Sposób 2: Przez GitHub CLI (gh) – prosto z terminala
```
---

## Zasady ogólne

1. **Jedno zadanie = jeden branch = jeden Pull Request.**
2. **Nigdy nie pushuj bezpośrednio do `main`.**
3. Robisz **tylko to, co jest w zakresie zadania**. Pomysły na dodatki zapisz jako nowy Issue.
4. Masz wątpliwość? **Pytaj przed pisaniem kodu**, nie po.
5. Frontend rozmawia z backendem **wyłącznie przez API** (`/api/v1`). Mobilka użyje tych samych endpointów.
6. Backend jest źródłem kontraktu OpenAPI (`shared/openapi.yaml`); nie utrzymuj osobnych ręcznych definicji API w Vue ani Androidzie.
7. `mobile/` to natywna aplikacja Android w Kotlinie i Jetpack Compose; decyzja stacku jest w `docs/ADR-002-mobile-stack.md`.
8. Żadnych haseł, kluczy ani plików `.env` w repozytorium.

---

## Cykl życia zadania

```
Backlog → Do zrobienia → W trakcie → Review → Gotowe
```

| Status | Znaczenie |
|---|---|
| Backlog | Pomysł lub zadanie jeszcze nieprecyzyjne |
| Do zrobienia | Spełnia Definition of Ready (z [TASK.md](TASK.md)), czeka na osobę |
| W trakcie | Ktoś jest przypisany i pracuje na branchu |
| Review | Otwarty Pull Request czeka na recenzję |
| Gotowe | Zmergowane do `main`, Issue zamknięte |

Przesuwaj kartę zadania na bieżąco, żeby wszyscy widzieli stan prac.

---

## Krok po kroku

### 1. Wybierz i zrozum zadanie

- Wybierz zadanie ze statusu "Do zrobienia" i **przypisz je do siebie**.
- Przeczytaj cały Issue: kontekst, zakres, wymagania techniczne, kryteria akceptacji.
- Sprawdź sekcję **Zależności** w [TASK.md](TASK.md). Jeśli zadanie od czegoś zależy, a tamto nie jest zmergowane, **nie zaczynaj**.
- Upewnij się, że rozumiesz, co znaczy "skończone" (kryteria akceptacji).

### 2. Przygotuj środowisko

```bash
git checkout main
git pull
```

Upewnij się, że kontenery działają (`docker compose up -d`). Jeśli to pierwszy raz, postępuj zgodnie z [README.md](README.md) (sekcja "Uruchomienie lokalne").

### 3. Utwórz branch

Nazwa: `typ/pr-x-krotki-opis` (małe litery, myślniki, bez dwukropków i polskich znaków).

```bash
git checkout -b feature/pr-19-tworzenie-podrozy
```

| Prefiks | Użycie |
|---|---|
| `feature/` | nowa funkcja |
| `fix/` | poprawka błędu |
| `chore/` | porządki, konfiguracja |
| `docs/` | dokumentacja |

### 4. Pracuj w małych krokach

- Commituj często, każdy commit to jedna logiczna zmiana.
- Format commita: `typ: krótki opis w trybie rozkazującym`
    - `feat: dodaj model Trip`
    - `fix: popraw walidację dat`
    - `test: dodaj testy tworzenia podróży`
    - `docs: opisz endpoint POST /trips`
- Dozwolone typy: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.

### 5. Pisz kod zgodnie z zasadami

**Backend (Laravel)**
- Walidacja w **Form Requests**.
- Logika biznesowa w **serwisach**, nie w kontrolerach (kontroler ma być cienki).
- Odpowiedzi przez **API Resources**, spójny format JSON, właściwe kody HTTP.
- Endpointy pod prefiksem **`/api/v1`**.
- Formatowanie: `docker compose exec backend ./vendor/bin/pint`

**Frontend (Vue)**
- Vue 3, Composition API, `<script setup>`, TypeScript.
- Komponenty w **PascalCase**.
- Wywołania API **tylko w `src/api/`**, nigdy bezpośrednio w komponentach.
- Formatowanie i lint: `docker compose exec frontend npm run lint`

**Dla obu**
- Do każdej nowej funkcji **dodaj testy**.
- Nie zostawiaj martwego kodu, `console.log` ani `dd()`.

**Mobile (Android)**
- Sprawdź kolejność, zależności i kryteria PR-55+ w [TASK2.md](TASK2.md); stosuj Kotlin, Jetpack Compose i decyzje z `docs/ADR-002-mobile-stack.md`.
- Nie dodawaj zależności bez uzasadnienia w zadaniu; wersje zapisuj w `mobile/gradle/libs.versions.toml`.
- Zachowuj podział UI, ViewModel i data/repository. Nie wywołuj API ani nie umieszczaj logiki biznesowej bezpośrednio w composable.
- Korzystaj z `/api/v1` i kontraktu `shared/openapi.yaml`; token Sanctum wysyłaj jako Bearer. Nie utrzymuj typów request/response niezgodnych z kontraktem.
- Tokeny przechowuj przez Android Keystore lub zatwierdzoną bibliotekę, nie w zwykłym `SharedPreferences`.
- Adres API konfiguruj dla środowiska; emulator Androida łączy się z hostem przez `10.0.2.2`, urządzenie fizyczne przez adres hosta w sieci lokalnej.
- Nową logikę pokrywaj testami jednostkowymi; przepływy UI/API weryfikuj testami odpowiedniego poziomu. Przed PR uruchom `./gradlew lintDebug testDebugUnitTest assembleDebug` z `mobile/`.
- Nie dodawaj ekranów ani funkcji w zadaniu bootstrapu; realizuj je w osobnych PR-ach.

### 6. Praca nad zadaniami backend + frontend

Gdy funkcja wymaga obu części:

1. Najpierw **ustalcie kontrakt API** (endpoint, body, odpowiedź, kody błędów) i zapiszcie go w `docs/` lub w Issue.
2. Backend i frontend mogą wtedy pracować równolegle, każdy na swoim branchu i w swoim zadaniu.
3. Frontend nie czeka na gotowy backend, jeśli kontrakt jest jasny (można użyć danych testowych).
4. Zmiana kontraktu = powiadom drugą stronę w komentarzu pod Issue.

### Zmiana kontraktu API

Jeśli zmieniasz endpoint, request lub response:

1. Zaktualizuj backend i wygeneruj kontrakt Scramble:
   ```bash
   docker compose exec backend php artisan scramble:export --path=storage/app/openapi.json
   cp backend/storage/app/openapi.json shared/openapi.json
   cd frontend && npm run api:generate
   ```
2. Dołącz `shared/openapi.yaml` i typy z `frontend/src/api/generated/` do tego samego PR-a.
3. Sprawdź zmiany w wygenerowanych plikach. Nie edytuj ich ręcznie.
4. Android korzysta z tego samego kontraktu; nie twórz osobnego API mobilnego.

### 7. Sprawdź przed Pull Requestem

Uruchom kontrole właściwe dla obszaru. Backend i frontend sprawdzaj w kontenerach, Androida przez Gradle Wrapper:

- [ ] `docker compose exec backend php artisan test`
- [ ] `docker compose exec backend ./vendor/bin/pint --test`
- [ ] `docker compose exec frontend npm run lint`
- [ ] `docker compose exec frontend npm run test`
- [ ] `docker compose exec frontend npm run build`
- [ ] `cd mobile && ./gradlew lintDebug testDebugUnitTest assembleDebug` (jeśli zmieniono `mobile/`; PowerShell: `cd mobile; .\gradlew.bat lintDebug testDebugUnitTest assembleDebug`)
- [ ] Zmiany działają lokalnie (sprawdź ręcznie kryteria akceptacji)
- [ ] `shared/openapi.yaml` i typy Vue zaktualizowane (jeśli zmieniły się endpointy lub schematy)
- [ ] Brak sekretów i plików `.env` w commitach

### 8. Wypchnij branch i otwórz Pull Request

```bash
git push -u origin feature/pr-x-krotki-opis
```

Otwórz PR do `main`, korzystając z `.github/PULL_REQUEST_TEMPLATE.md`:

```markdown
## Co zostało zmienione?
- Krótki opis zmian.

## Dlaczego?
- Powiązanie z zadaniem: Closes #numer-issue

## Testy
- [ ] testy przechodzą
- [ ] build działa
- [ ] manual QA wykonane

## Zmiany techniczne
- ...
```

Zapis `Closes #numer` automatycznie zamknie Issue po merge.

### 9. Review

**Jako autor:**
- Poproś o review co najmniej jedną osobę z zespołu.
- Odpowiadaj na komentarze i nanoś poprawki w kolejnych commitach.
- Nie merguj własnego PR bez akceptacji.

**Jako recenzent:**
- Sprawdź, czy PR spełnia **kryteria akceptacji** z Issue.
- Sprawdź zgodność z zasadami kodu (Form Requests, serwisy, `src/api/` itd.).
- Sprawdź, czy są testy i czy mają sens.
- Pisz konkretnie i uprzejmie. Komentuj kod, nie osobę.
- Odpowiedz w ciągu **1 dnia roboczego**, żeby nie blokować innych.

### 10. Merge i sprzątanie

Po akceptacji i zielonych testach (CI):

1. Zrób merge metodą **squash**. Tytuł commita w formacie `typ: opis`.
2. Usuń branch (zdalny i lokalny):

```bash
git checkout main
git pull
git branch -d feature/pr-x-krotki-opis
```

3. Sprawdź, że Issue jest zamknięte, a karta w statusie "Gotowe".

---

## Wątpliwości, blokady i zmiany zakresu

| Sytuacja | Co robisz |
|---|---|
| Niejasne wymagania | Napisz pytanie w komentarzu pod Issue i oznacz autora (@login). Nie zgaduj. |
| Zadanie jest za duże | Zgłoś to w komentarzu, zaproponuj podział na mniejsze Issue. |
| Zablokowała Cię inna osoba / zadanie | Napisz w Issue, co jest blokadą, i ustaw status "Zablokowane" lub dodaj etykietę `blocked`. |
| Znalazłeś błąd poza zakresem zadania | Utwórz osobny Issue (`fix/...`), nie naprawiaj przy okazji. |
| Masz pomysł na dodatkową funkcję | Utwórz osobny Issue w Backlogu. |
| Zakres trzeba zmienić | Zmiana wymaga zgody autora zadania. Zapisz ją w Issue. |
| Konflikt z `main` | Zaktualizuj branch: `git fetch && git rebase origin/main` (lub merge), rozwiąż konflikty lokalnie, uruchom testy ponownie. |
| Nie wiesz, czy możesz zacząć | Sprawdź Definition of Ready w [TASK.md](TASK.md). Jeśli czegoś brakuje, dopytaj. |

**Nie siedź na problemie dłużej niż 1–2 godziny.** Poproś o pomoc w zespole.

---

## Kontrole automatyczne

Pull Request uruchamia workflow GitHub Actions: testy backendu i build frontendu (`ci.yml`), kontrolę formatowania backendu (`lint.yml`) oraz lint/test/build Vue przy zmianach frontendu (`frontend-ci.yml`). Zmiany w `mobile/` uruchamiają `assembleDebug` w `android-ci.yml`. Android Lint i testy jednostkowe uruchamiaj lokalnie; dodaj je do CI wraz z pierwszymi zadaniami funkcjonalnymi. Workflow bezpieczeństwa audytuje zależności Composera, CodeQL analizuje JavaScript/TypeScript, a Dependabot sprawdza aktualizacje co tydzień.

## Najczęstsze błędy (unikaj)

- Praca bezpośrednio na `main`.
- Jeden wielki PR z wieloma niezwiązanymi zmianami.
- Logika biznesowa w kontrolerze/composable lub wywołania API bezpośrednio z komponentu.
- Brak testów "bo działa u mnie".
- Commit pliku `.env` lub kluczy API.
- Zmiana endpointu bez aktualizacji dokumentacji i informacji dla frontendu.
- Zaczynanie zadania z niejasnym zakresem.
