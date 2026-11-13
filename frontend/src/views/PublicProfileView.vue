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
const map = ref<UserMapData | null>(null)
const stats = ref<PublicProfileStats | null>(null)
const isLoading = ref(true)
const error = ref('')

const displayName = computed(() => {
  if (!profile.value) return ''
  const fullName = [profile.value.first_name, profile.value.last_name].filter(Boolean).join(' ')
  return profile.value.name || fullName || 'Użytkownik'
})

async function loadProfile() {
  isLoading.value = true
  error.value = ''

  try {
    const id = String(route.params.id)
    profile.value = await getPublicProfile(id)
    ;[trips.value, map.value, stats.value] = await Promise.all([
      getPublicProfileTrips(id),
      getPublicProfileMap(id),
      getPublicProfileStats(id),
    ])
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
        <p v-if="map.places.length === 0" class="profile-message">
          Ten profil nie ma jeszcze miejsc widocznych na mapie.
        </p>
        <MapView v-else class="profile-map" :places="map.places" :legs="map.legs" />
      </section>

      <section class="profile-section">
        <h2>Podróże</h2>
        <p v-if="trips.length === 0" class="profile-message">
          Ten profil nie ma jeszcze udostępnionych podróży.
        </p>
        <ul v-else class="trips-list">
          <li v-for="trip in trips" :key="trip.id">
            <h3>{{ trip.name }}</h3>
            <p v-if="trip.start_date">
              {{ trip.start_date }}<span v-if="trip.end_date"> – {{ trip.end_date }}</span>
            </p>
            <p v-if="trip.description">{{ trip.description }}</p>
          </li>
        </ul>
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
