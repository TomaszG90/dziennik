<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import MapView from '@/components/MapView.vue'
import { getUserMap, type UserMapData } from '@/api/trips'

const data = ref<UserMapData | null>(null)
const isLoading = ref(true)
const error = ref('')
const selectedYear = ref('all')
const selectedTripIds = ref<number[]>([])
const completedTrips = computed(() =>
  (data.value?.trips ?? []).filter((trip) => {
    const endDate = trip.end_date?.slice(0, 10) ?? trip.start_date?.slice(0, 10)
    return endDate !== undefined && endDate < today()
  }),
)
const availableYears = computed(() =>
  [...new Set(completedTrips.value.map((trip) => tripYear(trip)))].sort(
    (first, second) => Number(second) - Number(first),
  ),
)
const visibleTrips = computed(() =>
  selectedYear.value === 'all'
    ? completedTrips.value
    : completedTrips.value.filter((trip) => tripYear(trip) === selectedYear.value),
)
const selectedTrips = computed(() =>
  visibleTrips.value.filter((trip) => selectedTripIds.value.includes(trip.id)),
)
const selectedTripIdSet = computed(() => new Set(selectedTrips.value.map((trip) => trip.id)))
const visiblePlaces = computed(() =>
  (data.value?.places ?? []).filter((place) => selectedTripIdSet.value.has(place.trip_id)),
)
const visibleLegs = computed(() =>
  (data.value?.legs ?? []).filter((leg) => selectedTripIdSet.value.has(leg.trip_id)),
)
const placeCount = computed(() => visiblePlaces.value.length)
const legCount = computed(() => visibleLegs.value.length)

function today(): string {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function tripYear(trip: UserMapData['trips'][number]): string {
  return (trip.start_date ?? trip.end_date)?.slice(0, 4) ?? ''
}

function selectYear(event: Event) {
  selectedYear.value = (event.target as HTMLSelectElement).value
  selectedTripIds.value = visibleTrips.value.map((trip) => trip.id)
}

function selectAllTrips() {
  selectedTripIds.value = visibleTrips.value.map((trip) => trip.id)
}

function clearSelectedTrips() {
  selectedTripIds.value = []
}

async function loadMap() {
  isLoading.value = true
  error.value = ''
  try {
    data.value = await getUserMap()
    selectedTripIds.value = completedTrips.value.map((trip) => trip.id)
  } catch (cause: unknown) {
    error.value = cause instanceof Error ? cause.message : 'Nie udało się załadować mapy podróży.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => void loadMap())
</script>

<template>
  <main class="user-map-page">
    <header class="user-map-heading">
      <div>
        <p class="eyebrow">Twój dziennik</p>
        <h1>Mapa podróży</h1>
        <p class="user-map-intro">Wybierz ukończone podróże, które chcesz zobaczyć na mapie.</p>
      </div>
      <RouterLink class="user-map-back" :to="{ name: 'me' }">Wróć do dziennika</RouterLink>
    </header>

    <div v-if="isLoading" class="user-map-status" role="status" aria-live="polite">
      Ładowanie mapy podróży…
    </div>

    <section v-else-if="error" class="user-map-status user-map-error" role="alert">
      <p>{{ error }}</p>
      <button type="button" @click="loadMap">Spróbuj ponownie</button>
    </section>

    <div v-else class="user-map-content">
      <section class="user-map-filters" aria-label="Filtry mapy podróży">
        <div class="year-filter">
          <label for="map-year">Rok podróży</label>
          <select id="map-year" :value="selectedYear" @change="selectYear">
            <option value="all">Wszystkie lata</option>
            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>

        <fieldset v-if="visibleTrips.length" class="trip-filter">
          <legend>Odbyte podróże</legend>
          <div class="trip-filter-actions">
            <button type="button" @click="selectAllTrips">Zaznacz wszystkie</button>
            <button type="button" @click="clearSelectedTrips">Wyczyść wybór</button>
          </div>
          <ul class="trip-filter-list">
            <li v-for="trip in visibleTrips" :key="trip.id">
              <label class="trip-filter-option">
                <input v-model="selectedTripIds" type="checkbox" :value="trip.id" />
                <span class="trip-filter-name">{{ trip.name }}</span>
                <span class="trip-filter-year">{{ tripYear(trip) }}</span>
              </label>
              <RouterLink
                class="trip-details-link"
                :to="{ name: 'trip-details', params: { id: trip.id } }"
                :aria-label="`Szczegóły podróży ${trip.name}`"
              >
                Szczegóły
              </RouterLink>
            </li>
          </ul>
        </fieldset>
      </section>

      <section v-if="completedTrips.length === 0" class="user-map-status" aria-live="polite">
        <h2>Nie ma jeszcze odbytych podróży</h2>
        <p>Ukończone podróże pojawią się tutaj.</p>
      </section>

      <section v-else-if="selectedTrips.length === 0" class="user-map-status" aria-live="polite">
        <h2>Nie wybrano podróży</h2>
        <p>Zaznacz co najmniej jedną podróż, aby wyświetlić ją na mapie.</p>
      </section>

      <section v-else-if="placeCount === 0" class="user-map-status" aria-live="polite">
        <h2>Wybrane podróże nie mają jeszcze miejsc</h2>
        <p>Dodaj miejsca do wybranych podróży, aby zobaczyć je tutaj.</p>
        <RouterLink :to="{ name: 'me' }">Otwórz dziennik</RouterLink>
      </section>

      <section v-else class="user-map-card" aria-label="Mapa wybranych podróży">
        <div class="user-map-summary" aria-live="polite">
          <span
            ><strong>{{ selectedTrips.length }}</strong> podróży</span
          >
          <span
            ><strong>{{ placeCount }}</strong> {{ placeCount === 1 ? 'miejsce' : 'miejsc' }}</span
          >
          <span
            ><strong>{{ legCount }}</strong> {{ legCount === 1 ? 'odcinek' : 'odcinków' }}</span
          >
        </div>
        <MapView
          class="user-map-canvas"
          data-testid="user-map-canvas"
          :places="visiblePlaces"
          :legs="visibleLegs"
        />
      </section>
    </div>
  </main>
</template>

<style scoped>
.user-map-page {
  width: min(100% - 2rem, 1280px);
  margin: 2rem auto 4rem;
}

.user-map-heading {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.user-map-heading h1 {
  margin: 0.2rem 0 0;
  color: #173d30;
  font-size: clamp(1.8rem, 4vw, 2.5rem);
}

.eyebrow {
  margin: 0;
  color: #4b8066;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.user-map-intro {
  margin: 0.5rem 0 0;
  color: #64746c;
}

.user-map-content {
  display: grid;
  gap: 1rem;
}

.user-map-filters {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
  border: 1px solid rgba(56, 106, 84, 0.18);
  border-radius: 1rem;
  background: #fff;
  box-shadow: 0 12px 32px rgba(30, 65, 49, 0.08);
}

.year-filter {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.year-filter label,
.trip-filter legend {
  color: #173d30;
  font-weight: 700;
}

.year-filter select {
  min-height: 2.5rem;
  padding: 0.45rem 2rem 0.45rem 0.7rem;
  border: 1px solid #b8c8bf;
  border-radius: 0.55rem;
  color: #173d30;
  background: #fff;
  font: inherit;
}

.trip-filter {
  display: grid;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  border: 0;
}

.trip-filter-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.trip-filter-actions button {
  padding: 0;
  border: 0;
  color: #256348;
  background: transparent;
  font: inherit;
  font-weight: 650;
  text-decoration: underline;
  cursor: pointer;
}

.trip-filter-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr));
  gap: 0.5rem;
  max-height: 14rem;
  overflow: auto;
  margin: 0;
  padding: 0;
  list-style: none;
}

.trip-filter-list li {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.trip-filter-option {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex: 1;
  min-height: 2.5rem;
  padding: 0.45rem 0.65rem;
  border: 1px solid rgba(56, 106, 84, 0.14);
  border-radius: 0.6rem;
  color: #33483d;
  cursor: pointer;
}

.trip-filter-list input {
  accent-color: #256348;
}

.trip-filter-name {
  flex: 1;
}

.trip-details-link {
  color: #256348;
  font-size: 0.85rem;
  font-weight: 650;
  text-decoration: underline;
}

.trip-filter-year {
  color: #64746c;
  font-size: 0.85rem;
}

.user-map-back,
.user-map-status a {
  color: #256348;
  font-weight: 650;
}

.user-map-card,
.user-map-status {
  overflow: hidden;
  border: 1px solid rgba(56, 106, 84, 0.18);
  border-radius: 1rem;
  background: #fff;
  box-shadow: 0 12px 32px rgba(30, 65, 49, 0.08);
}

.user-map-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  padding: 1rem 1.25rem;
  color: #52655b;
}

.user-map-summary strong {
  color: #173d30;
}

.user-map-canvas {
  height: min(68vh, 760px);
  min-height: 26rem;
  border-radius: 0;
}

.user-map-status {
  padding: 2rem;
  color: #52655b;
}

.user-map-status h2 {
  margin-top: 0;
  color: #173d30;
}

.user-map-error button {
  padding: 0.65rem 1rem;
  border: 0;
  border-radius: 0.6rem;
  background: #256348;
  color: #fff;
  cursor: pointer;
}

@media (max-width: 640px) {
  .user-map-heading {
    align-items: start;
    flex-direction: column;
  }

  .user-map-canvas {
    height: 62vh;
    min-height: 22rem;
  }
}
</style>
