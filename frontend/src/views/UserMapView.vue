<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import MapView from '@/components/MapView.vue'
import { getUserMap, type UserMapData } from '@/api/trips'

const data = ref<UserMapData | null>(null)
const isLoading = ref(true)
const error = ref('')
const placeCount = computed(() => data.value?.places.length ?? 0)
const legCount = computed(() => data.value?.legs.length ?? 0)

async function loadMap() {
  isLoading.value = true
  error.value = ''
  try {
    data.value = await getUserMap()
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
        <p class="user-map-intro">Wszystkie zapisane miejsca i trasy zebrane na jednej mapie.</p>
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

    <section v-else-if="placeCount === 0" class="user-map-status" aria-live="polite">
      <h2>Nie ma jeszcze miejsc na mapie</h2>
      <p>Dodaj miejsca do swoich podróży, aby zobaczyć je tutaj.</p>
      <RouterLink :to="{ name: 'me' }">Otwórz dziennik</RouterLink>
    </section>

    <section v-else class="user-map-card" aria-label="Mapa wszystkich podróży">
      <div class="user-map-summary" aria-live="polite">
        <span><strong>{{ data?.trips.length ?? 0 }}</strong> podróży</span>
        <span><strong>{{ placeCount }}</strong> {{ placeCount === 1 ? 'miejsce' : 'miejsc' }}</span>
        <span><strong>{{ legCount }}</strong> {{ legCount === 1 ? 'odcinek' : 'odcinków' }}</span>
      </div>
      <MapView
        class="user-map-canvas"
        data-testid="user-map-canvas"
        :places="data?.places ?? []"
        :legs="data?.legs ?? []"
      />
    </section>
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
