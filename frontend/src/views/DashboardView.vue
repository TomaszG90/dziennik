<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { getTripsPage, type Trip } from '@/api/trips'

type TripStatus = 'all' | 'planned' | 'past'

const trips = ref<Trip[]>([])
const isLoading = ref(true)
const error = ref('')
const search = ref('')
const selectedYear = ref(String(new Date().getFullYear()))
const selectedStatus = ref<TripStatus>('all')
const currentPage = ref(1)
const lastPage = ref(1)
const totalTrips = ref(0)
const isLoadingMore = ref(false)
const filtersActive = computed(
  () => !!search.value.trim() || !!selectedYear.value || selectedStatus.value !== 'all',
)
let requestId = 0
let searchTimer: ReturnType<typeof setTimeout> | undefined

function dateOnly(value: string | null | undefined): string | null {
  return value?.slice(0, 10) ?? null
}

function tripYear(trip: Trip): string {
  const date = dateOnly(trip.start_date) ?? dateOnly(trip.end_date)
  return date ? date.slice(0, 4) : 'Bez daty'
}

function isPastTrip(trip: Trip): boolean {
  const now = new Date()
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
  const end = dateOnly(trip.end_date) ?? dateOnly(trip.start_date)
  return end !== null && end < today
}

function tripCountLabel(count: number): string {
  if (count === 1) return 'wycieczka'
  const lastDigit = count % 10
  const lastTwoDigits = count % 100
  return lastDigit >= 2 && lastDigit <= 4 && (lastTwoDigits < 12 || lastTwoDigits > 14)
    ? 'wycieczki'
    : 'wycieczek'
}

function formatDate(value: string | null): string {
  if (!value) return ''
  const [year, month, day] = value.slice(0, 10).split('-')
  return year && month && day ? `${day}.${month}.${year}` : value
}

function dateRange(trip: Trip): string {
  const start = dateOnly(trip.start_date)
  const end = dateOnly(trip.end_date)
  if (start && end)
    return start === end ? formatDate(start) : `${formatDate(start)} – ${formatDate(end)}`
  if (start) return `Od ${formatDate(start)}`
  if (end) return `Do ${formatDate(end)}`
  return 'Termin do ustalenia'
}

const groupedTrips = computed(() => {
  const groups = new Map<string, Trip[]>()
  for (const trip of trips.value) {
    const year = tripYear(trip)
    groups.set(year, [...(groups.get(year) ?? []), trip])
  }
  return [...groups.entries()].sort(([a], [b]) => {
    if (a === 'Bez daty') return 1
    if (b === 'Bez daty') return -1
    return Number(b) - Number(a)
  })
})

async function loadTrips(page = 1, append = false) {
  if (append && isLoading.value) return
  const id = ++requestId
  isLoading.value = !append
  isLoadingMore.value = append
  error.value = ''
  try {
    const result = await getTripsPage({
      page,
      search: search.value.trim() || undefined,
      year: selectedYear.value ? Number(selectedYear.value) : undefined,
      status: selectedStatus.value,
    })
    if (id !== requestId) return
    trips.value = append
      ? [
          ...trips.value,
          ...result.data.filter((trip) => !trips.value.some(({ id }) => id === trip.id)),
        ]
      : result.data
    currentPage.value = result.meta.current_page
    lastPage.value = result.meta.last_page
    totalTrips.value = result.meta.total
  } catch (cause: unknown) {
    if (id !== requestId) return
    error.value = cause instanceof Error ? cause.message : 'Nie udało się pobrać listy wycieczek.'
  } finally {
    if (id === requestId) {
      isLoading.value = false
      isLoadingMore.value = false
    }
  }
}

function loadMoreTrips() {
  if (isLoading.value || isLoadingMore.value || currentPage.value >= lastPage.value) return
  void loadTrips(currentPage.value + 1, true)
}

function clearFilters() {
  search.value = ''
  selectedYear.value = ''
  selectedStatus.value = 'all'
}

watch([search, selectedYear, selectedStatus], () => {
  requestId += 1
  currentPage.value = 1
  isLoading.value = true
  isLoadingMore.value = false
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void loadTrips(1), 250)
})

onMounted(() => void loadTrips(1))
onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})
</script>

<template>
  <main class="dashboard-page">
    <header class="dashboard-heading">
      <div>
        <p class="eyebrow">Twój dziennik</p>
        <h1>Wycieczki</h1>
        <p>Przeglądaj swoje podróże według roku albo wyszukaj konkretną wycieczkę.</p>
      </div>
      <div class="dashboard-actions">
        <RouterLink class="button-primary" :to="{ name: 'trip-create' }"
          >＋ Nowa wycieczka</RouterLink
        >
        <RouterLink class="button-secondary" :to="{ name: 'user-map' }">Mapa podróży</RouterLink>
      </div>
    </header>

    <section class="trip-filters" aria-label="Filtry wycieczek">
      <label class="search-field">
        <span>Szukaj wycieczki</span>
        <input
          v-model="search"
          type="search"
          placeholder="Nazwa lub opis"
          autocomplete="off"
          data-testid="trip-search"
        />
      </label>
      <label>
        <span>Rok</span>
        <input
          v-model="selectedYear"
          type="number"
          min="1"
          max="9999"
          placeholder="Wszystkie lata"
          data-testid="trip-year-filter"
        />
      </label>
      <label>
        <span>Status</span>
        <select v-model="selectedStatus" data-testid="trip-status-filter">
          <option value="all">Wszystkie</option>
          <option value="planned">Planowane</option>
          <option value="past">Odbyte</option>
        </select>
      </label>
      <button class="reset-filters" type="button" :disabled="!filtersActive" @click="clearFilters">
        Wyczyść filtry
      </button>
    </section>

    <div v-if="isLoading" class="dashboard-state" role="status" aria-live="polite">
      Ładowanie wycieczek…
    </div>
    <section
      v-else-if="error && trips.length === 0"
      class="dashboard-state dashboard-error"
      role="alert"
    >
      <p>{{ error }}</p>
      <button type="button" @click="loadTrips()">Spróbuj ponownie</button>
    </section>
    <section v-else-if="error" class="dashboard-state dashboard-error" role="alert">
      <p>{{ error }}</p>
      <button type="button" @click="loadMoreTrips">Spróbuj ponownie</button>
    </section>
    <section v-else-if="totalTrips === 0 && !filtersActive">
      <h2>Twój dziennik jest jeszcze pusty</h2>
      <p>Dodaj pierwszą wycieczkę, aby zacząć zapisywać swoje podróże.</p>
      <RouterLink class="button-primary" :to="{ name: 'trip-create' }">Dodaj wycieczkę</RouterLink>
    </section>
    <section v-else-if="totalTrips === 0" class="dashboard-state">
      <h2>Brak pasujących wycieczek</h2>
      <p>Zmień kryteria wyszukiwania lub wyczyść filtry.</p>
      <button class="reset-filters" type="button" @click="clearFilters">Wyczyść filtry</button>
    </section>
    <div v-else class="year-groups" data-testid="trip-year-groups">
      <section v-for="[year, yearTrips] in groupedTrips" :key="year" class="year-group">
        <header class="year-heading">
          <h2>{{ year }}</h2>
          <span>{{ yearTrips.length }} {{ tripCountLabel(yearTrips.length) }}</span>
        </header>
        <div class="trip-grid">
          <article v-for="trip in yearTrips" :key="trip.id" class="trip-card">
            <div class="trip-card-content">
              <span class="trip-status" :class="isPastTrip(trip) ? 'is-past' : 'is-planned'">
                {{ isPastTrip(trip) ? 'Odbyta' : 'Planowana' }}
              </span>
              <h3>{{ trip.name }}</h3>
              <p class="trip-dates">{{ dateRange(trip) }}</p>
              <p v-if="trip.description" class="trip-description">{{ trip.description }}</p>
            </div>
            <footer class="trip-card-actions">
              <RouterLink :to="{ name: 'trip-details', params: { id: trip.id } }"
                >Szczegóły</RouterLink
              >
              <RouterLink :to="{ name: 'trip-edit', params: { id: trip.id } }">Edytuj</RouterLink>
            </footer>
          </article>
        </div>
      </section>
    </div>
    <nav v-if="totalTrips > 0" class="trip-pagination" aria-label="Wycieczki">
      <span>
        Wyświetlono {{ trips.length }} z {{ totalTrips }}
        {{ tripCountLabel(totalTrips) }}
      </span>
      <button
        v-if="currentPage < lastPage"
        type="button"
        :disabled="isLoading || isLoadingMore"
        @click="loadMoreTrips"
      >
        {{ isLoadingMore ? 'Ładowanie…' : 'Załaduj kolejne 5 wycieczek' }}
      </button>
    </nav>
  </main>
</template>

<style scoped>
.dashboard-page {
  width: min(100% - 2rem, 1180px);
  margin: 2rem auto 4rem;
}

.dashboard-heading,
.dashboard-actions,
.year-heading,
.trip-card-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.dashboard-heading {
  align-items: end;
  margin-bottom: 1.5rem;
}

.dashboard-heading h1 {
  margin: 0.2rem 0;
  color: #173d30;
  font-size: clamp(2rem, 5vw, 2.8rem);
}

.dashboard-heading p:last-child,
.trip-description,
.dashboard-state p {
  color: #64746c;
}

.eyebrow {
  margin: 0;
  color: #4b8066;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.dashboard-actions {
  flex-wrap: wrap;
}

.button-primary,
.button-secondary,
.dashboard-error button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.7rem;
  padding: 0.65rem 1rem;
  border: 1px solid #256348;
  border-radius: 0.65rem;
  font: inherit;
  font-weight: 650;
  text-decoration: none;
  cursor: pointer;
}

.button-primary,
.dashboard-error button {
  background: #256348;
  color: #fff;
}

.button-secondary {
  background: #fff;
  color: #256348;
}

.trip-filters {
  display: grid;
  grid-template-columns: minmax(14rem, 1fr) minmax(9rem, 0.35fr) minmax(9rem, 0.35fr) auto;
  align-items: end;
  gap: 0.9rem;
  margin-bottom: 2rem;
  padding: 1rem;
  border: 1px solid rgba(56, 106, 84, 0.18);
  border-radius: 0.9rem;
  background: #fff;
}

.trip-filters label {
  display: grid;
  gap: 0.4rem;
  color: #40564a;
  font-size: 0.85rem;
  font-weight: 650;
}

.trip-filters input,
.trip-filters select {
  width: 100%;
  min-height: 2.7rem;
  padding: 0.55rem 0.7rem;
  border: 1px solid #cbd8d0;
  border-radius: 0.55rem;
  background: #fff;
  color: #23372c;
  font: inherit;
}

.reset-filters {
  min-height: 2.7rem;
  padding: 0.5rem;
  border: 0;
  background: transparent;
  color: #256348;
  font: inherit;
  font-weight: 650;
  cursor: pointer;
}

.reset-filters:disabled {
  color: #9aa9a0;
  cursor: default;
}

.dashboard-state {
  padding: 2rem;
  border: 1px solid rgba(56, 106, 84, 0.18);
  border-radius: 0.9rem;
  background: #fff;
  color: #52655b;
}

.dashboard-state h2 {
  margin-top: 0;
  color: #173d30;
}

.dashboard-error button {
  border: 0;
}

.year-groups {
  display: grid;
  gap: 2rem;
}

.trip-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1.5rem;
  color: #64746c;
}

.trip-pagination > div {
  display: flex;
  gap: 0.5rem;
}

.trip-pagination button {
  min-height: 2.5rem;
  padding: 0.45rem 0.8rem;
  border: 1px solid #cbd8d0;
  border-radius: 0.55rem;
  background: #fff;
  color: #256348;
  font: inherit;
  cursor: pointer;
}

.trip-pagination button:disabled {
  color: #9aa9a0;
  cursor: default;
}

.year-heading {
  justify-content: start;
  margin-bottom: 0.9rem;
}

.year-heading h2 {
  margin: 0;
  color: #173d30;
  font-size: 1.5rem;
}

.year-heading span {
  color: #75847b;
}

.trip-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr));
  gap: 1rem;
}

.trip-card {
  display: flex;
  min-height: 13rem;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
  border: 1px solid rgba(56, 106, 84, 0.18);
  border-radius: 0.9rem;
  background: #fff;
  box-shadow: 0 8px 24px rgba(30, 65, 49, 0.06);
}

.trip-card-content {
  padding: 1.1rem 1.2rem;
}

.trip-status {
  display: inline-flex;
  padding: 0.2rem 0.55rem;
  border-radius: 99px;
  font-size: 0.75rem;
  font-weight: 700;
}

.is-past {
  background: #f0f2ef;
  color: #64746c;
}

.is-planned {
  background: #e7f4eb;
  color: #256348;
}

.trip-card h3 {
  margin: 0.7rem 0 0.25rem;
  color: #173d30;
  font-size: 1.15rem;
}

.trip-dates {
  margin: 0;
  color: #52655b;
}

.trip-description {
  display: -webkit-box;
  overflow: hidden;
  margin-bottom: 0;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
}

.trip-card-actions {
  justify-content: start;
  padding: 0.8rem 1.2rem;
  border-top: 1px solid #edf1ee;
}

.trip-card-actions a {
  color: #256348;
  font-weight: 650;
  text-decoration: none;
}

.trip-card-actions a:hover,
.user-map-back:hover {
  text-decoration: underline;
}

@media (max-width: 760px) {
  .dashboard-heading {
    align-items: start;
    flex-direction: column;
  }

  .trip-filters {
    grid-template-columns: 1fr 1fr;
  }

  .search-field {
    grid-column: 1 / -1;
  }
}

@media (max-width: 460px) {
  .trip-filters {
    grid-template-columns: 1fr;
  }

  .search-field {
    grid-column: auto;
  }

  .dashboard-actions > * {
    flex: 1;
  }
}
</style>
