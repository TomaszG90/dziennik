<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import MapView from '@/components/MapView.vue'
import { ApiError } from '@/api/types'
import {
  getPublicAvatarUrl,
  getPublicProfile,
  getPublicProfileMap,
  getPublicProfileStats,
  getPublicProfileTrips,
  type PublicProfile,
  type PublicProfileStats,
} from '@/api/publicProfiles'
import type { Trip, UserMapData } from '@/api/trips'

const route = useRoute()
const profile = ref<PublicProfile | null>(null)
const trips = ref<Trip[]>([])
const availableYears = ref<number[]>([])
const selectedYear = ref('')
const tripsCurrentPage = ref(1)
const tripsLastPage = ref(1)
const tripsTotal = ref(0)
const tripsLoading = ref(false)
const tripsError = ref('')
const map = ref<UserMapData | null>(null)
const stats = ref<PublicProfileStats | null>(null)
const isLoading = ref(true)
const error = ref('')

const displayName = computed(() => {
  if (!profile.value) return ''
  const fullName = [profile.value.first_name, profile.value.last_name].filter(Boolean).join(' ')
  return profile.value.name || fullName || 'Użytkownik'
})

function tripYear(trip: UserMapData['trips'][number]): string {
  return (trip.start_date ?? trip.end_date)?.slice(0, 4) ?? ''
}

const visibleMapTripIds = computed(() => {
  const mapTrips = map.value?.trips ?? []
  return new Set(
    mapTrips
      .filter((trip) => !selectedYear.value || tripYear(trip) === selectedYear.value)
      .map((trip) => trip.id),
  )
})
const visibleMapPlaces = computed(() =>
  (map.value?.places ?? []).filter((place) => visibleMapTripIds.value.has(place.trip_id)),
)
const visibleMapLegs = computed(() =>
  (map.value?.legs ?? []).filter((leg) => visibleMapTripIds.value.has(leg.trip_id)),
)

async function loadProfile() {
  isLoading.value = true
  error.value = ''
  trips.value = []
  selectedYear.value = ''
  tripsCurrentPage.value = 1
  tripsLastPage.value = 1
  tripsTotal.value = 0

  try {
    const id = String(route.params.id)
    profile.value = await getPublicProfile(id)
    const [tripsPage, profileMap, profileStats] = await Promise.all([
      getPublicProfileTrips(id),
      getPublicProfileMap(id),
      getPublicProfileStats(id),
    ])
    trips.value = tripsPage.data
    tripsCurrentPage.value = tripsPage.meta.current_page
    tripsLastPage.value = tripsPage.meta.last_page
    tripsTotal.value = tripsPage.meta.total
    map.value = profileMap
    stats.value = profileStats
    availableYears.value = [
      ...new Set(
        profileMap.trips
          .flatMap((trip) => [trip.start_date, trip.end_date])
          .filter((date): date is string => date !== null)
          .map((date) => Number(date.slice(0, 4))),
      ),
    ].sort((a, b) => b - a)
  } catch (cause: unknown) {
    error.value =
      cause instanceof ApiError && cause.status === 404
        ? 'Profil nie istnieje lub jest prywatny.'
        : cause instanceof Error
          ? cause.message
          : 'Nie udało się załadować publicznego profilu.'
  } finally {
    isLoading.value = false
  }
}

async function loadMoreTrips() {
  if (tripsLoading.value || tripsCurrentPage.value >= tripsLastPage.value) return

  tripsLoading.value = true
  tripsError.value = ''
  try {
    const result = await getPublicProfileTrips(
      String(route.params.id),
      tripsCurrentPage.value + 1,
      selectedYear.value ? Number(selectedYear.value) : undefined,
    )
    trips.value = [
      ...trips.value,
      ...result.data.filter((trip) => !trips.value.some(({ id }) => id === trip.id)),
    ]
    tripsCurrentPage.value = result.meta.current_page
    tripsLastPage.value = result.meta.last_page
    tripsTotal.value = result.meta.total
  } catch (cause: unknown) {
    tripsError.value =
      cause instanceof Error ? cause.message : 'Nie udało się załadować kolejnych podróży.'
  } finally {
    tripsLoading.value = false
  }
}

async function filterTripsByYear() {
  tripsLoading.value = true
  tripsError.value = ''
  trips.value = []
  tripsCurrentPage.value = 1
  try {
    const result = await getPublicProfileTrips(
      String(route.params.id),
      1,
      selectedYear.value ? Number(selectedYear.value) : undefined,
    )
    trips.value = result.data
    tripsCurrentPage.value = result.meta.current_page
    tripsLastPage.value = result.meta.last_page
    tripsTotal.value = result.meta.total
  } catch (cause: unknown) {
    tripsError.value = cause instanceof Error ? cause.message : 'Nie udało się załadować podróży.'
  } finally {
    tripsLoading.value = false
  }
}

watch(
  () => route.params.id,
  () => void loadProfile(),
  { immediate: true },
)
</script>

<template>
  <section class="public-profile-page">
    <div v-if="isLoading" class="profile-message" role="status">Ładowanie profilu…</div>

    <section v-else-if="error" class="profile-message profile-error" role="alert">
      <h1>Nie można wyświetlić profilu</h1>
      <p>{{ error }}</p>
    </section>

    <template v-else-if="profile && map && stats">
      <header class="profile-heading">
        <img
          v-if="profile.avatar_path"
          class="profile-avatar"
          :src="getPublicAvatarUrl(profile.avatar_path)"
          alt=""
        />
        <span v-else class="profile-avatar profile-avatar-placeholder" aria-hidden="true">
          {{ displayName.slice(0, 1).toLocaleUpperCase() }}
        </span>
        <div>
          <p class="eyebrow">Publiczny profil</p>
          <h1>{{ displayName }}</h1>
          <p v-if="profile.city" class="profile-city">{{ profile.city }}</p>
          <p v-if="profile.birth_date" class="profile-city">
            Data urodzenia: {{ profile.birth_date }}
          </p>
        </div>
      </header>

      <section class="stats-grid" aria-label="Statystyki podróży">
        <article>
          <strong>{{ stats.trips_count }}</strong
          ><span>podróży</span>
        </article>
        <article>
          <strong>{{ stats.places_count }}</strong
          ><span>miejsc</span>
        </article>
        <article>
          <strong>{{ stats.countries.length }}</strong
          ><span>odwiedzonych krajów</span>
        </article>
        <article>
          <strong>{{ stats.total_distance.toLocaleString('pl-PL') }} km</strong
          ><span>łącznego dystansu</span>
        </article>
      </section>

      <section class="profile-section">
        <h2>Mapa podróży</h2>
        <p v-if="visibleMapPlaces.length === 0" class="profile-message">
          {{
            selectedYear
              ? `Brak miejsc z podróży z roku ${selectedYear} widocznych na mapie.`
              : 'Ten profil nie ma jeszcze miejsc widocznych na mapie.'
          }}
        </p>
        <MapView v-else class="profile-map" :places="visibleMapPlaces" :legs="visibleMapLegs" />
      </section>

      <section class="profile-section">
        <h2>Podróże</h2>
        <label class="trip-year-filter">
          Rok podróży
          <select v-model="selectedYear" @change="filterTripsByYear">
            <option value="">Wszystkie lata</option>
            <option v-for="year in availableYears" :key="year" :value="String(year)">
              {{ year }}
            </option>
          </select>
        </label>
        <div
          v-if="tripsError && trips.length === 0"
          class="profile-message profile-error"
          role="alert"
        >
          {{ tripsError }}
          <button type="button" @click="filterTripsByYear">Spróbuj ponownie</button>
        </div>
        <p v-else-if="tripsLoading && trips.length === 0" class="profile-message" role="status">
          Ładowanie podróży…
        </p>
        <p v-else-if="trips.length === 0" class="profile-message">
          {{
            selectedYear
              ? `Ten profil nie ma udostępnionych podróży z roku ${selectedYear}.`
              : 'Ten profil nie ma jeszcze udostępnionych podróży.'
          }}
        </p>
        <ul v-if="trips.length > 0" class="trips-list">
          <li v-for="trip in trips" :key="trip.id">
            <h3>{{ trip.name }}</h3>
            <p v-if="trip.start_date">
              {{ trip.start_date }}<span v-if="trip.end_date"> – {{ trip.end_date }}</span>
            </p>
            <p v-if="trip.description">{{ trip.description }}</p>
          </li>
        </ul>
        <div
          v-if="tripsError && trips.length > 0"
          class="profile-message profile-error"
          role="alert"
        >
          <p>{{ tripsError }}</p>
          <button type="button" @click="loadMoreTrips">Spróbuj ponownie</button>
        </div>
        <div v-if="tripsTotal > 0" class="trips-pagination">
          <span>Wyświetlono {{ trips.length }} z {{ tripsTotal }} podróży</span>
          <button
            v-if="tripsCurrentPage < tripsLastPage"
            type="button"
            :disabled="tripsLoading"
            @click="loadMoreTrips"
          >
            {{ tripsLoading ? 'Ładowanie…' : 'Załaduj kolejne 5 podróży' }}
          </button>
        </div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.public-profile-page {
  width: min(100% - 2rem, 1100px);
  margin: 2rem auto 4rem;
}

.profile-heading {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.profile-avatar {
  display: grid;
  flex: 0 0 4.5rem;
  place-items: center;
  width: 4.5rem;
  height: 4.5rem;
  border-radius: 50%;
}

.profile-avatar-placeholder {
  color: white;
  background: #386a54;
  font-size: 1.8rem;
  font-weight: 700;
}

img.profile-avatar {
  object-fit: cover;
}

.profile-heading h1 {
  margin: 0.2rem 0;
  color: #173d30;
  font-size: clamp(1.8rem, 4vw, 2.5rem);
}

.profile-city,
.trips-list p {
  margin: 0.25rem 0 0;
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

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.75rem;
  margin-bottom: 2rem;
}

.stats-grid article {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 1rem;
  border: 1px solid var(--color-border);
  border-radius: 0.8rem;
}

.stats-grid strong {
  color: #173d30;
  font-size: 1.25rem;
}

.stats-grid span {
  color: #64746c;
  font-size: 0.9rem;
}

.profile-section {
  margin-top: 2rem;
}

.profile-section h2 {
  margin-bottom: 0.85rem;
  color: #173d30;
}

.trip-year-filter {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
  color: #40564a;
  font-weight: 650;
}

.trip-year-filter select,
.trips-pagination button,
.profile-error button {
  min-height: 2.5rem;
  padding: 0.45rem 0.7rem;
  border: 1px solid #cbd8d0;
  border-radius: 0.55rem;
  background: #fff;
  color: #256348;
  font: inherit;
}

.trips-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
  color: #64746c;
}

.trips-pagination button {
  cursor: pointer;
}

.trips-pagination button:disabled {
  cursor: default;
}

.profile-map {
  height: min(60vh, 500px);
  min-height: 300px;
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-radius: 1rem;
}

.trips-list {
  display: grid;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.trips-list li,
.profile-message {
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: 0.8rem;
}

.trips-list h3 {
  margin: 0;
  color: #173d30;
}

.profile-error {
  color: #9f2424;
}

.profile-error a {
  color: #386a54;
}
</style>
