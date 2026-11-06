<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { getTrip, getTripLegs, getTripPlaces, type Trip, type TripLeg, type TripPlace } from '@/api/trips'
import { ApiError } from '@/api/types'

const route = useRoute()
const trip = ref<Trip | null>(null)
const places = ref<TripPlace[]>([])
const legs = ref<TripLeg[]>([])
const isLoading = ref(true)
const error = ref('')
const friendSearch = ref('')
const invitedFriends = ref<string[]>([])
const photoLink = ref('')
const photoLinks = ref<string[]>([])
const socialError = ref('')

const storageKey = computed(() => `trip-details-${String(route.params.id)}-extras`)

watch(storageKey, (key) => {
  try {
    const saved = JSON.parse(localStorage.getItem(key) ?? '{}') as { friends?: string[]; photoLinks?: string[] }
    invitedFriends.value = saved.friends ?? []
    photoLinks.value = saved.photoLinks ?? []
  } catch {
    invitedFriends.value = []
    photoLinks.value = []
  }
}, { immediate: true })

function saveExtras() {
  localStorage.setItem(storageKey.value, JSON.stringify({ friends: invitedFriends.value, photoLinks: photoLinks.value }))
}

function inviteFriend() {
  const name = friendSearch.value.trim()
  socialError.value = ''
  if (!name) {
    socialError.value = 'Wyszukaj znajomego z aplikacji.'
    return
  }
  if (invitedFriends.value.includes(name)) {
    socialError.value = 'Ta osoba jest już dodana do podróży.'
    return
  }
  invitedFriends.value.push(name)
  friendSearch.value = ''
  saveExtras()
}

function removeFriend(name: string) {
  invitedFriends.value = invitedFriends.value.filter((item) => item !== name)
  saveExtras()
}

function addPhotoLink() {
  socialError.value = ''
  try {
    const input = photoLink.value.trim()
    const normalizedInput = /^www\./i.test(input) ? `https://${input}` : input
    const url = new URL(normalizedInput)
    if (!['http:', 'https:'].includes(url.protocol)) throw new Error('invalid protocol')
    if (photoLinks.value.includes(url.href)) {
      socialError.value = 'Ten link jest już dodany.'
      return
    }
    photoLinks.value.push(url.href)
    photoLink.value = ''
    saveExtras()
  } catch {
    socialError.value = 'Wpisz poprawny link zaczynający się od https:// lub www.'
  }
}

function removePhotoLink(url: string) {
  photoLinks.value = photoLinks.value.filter((item) => item !== url)
  saveExtras()
}

function photoPortalLabel(url: string): string {
  try { return new URL(url).hostname.replace(/^www\./, '') } catch { return url }
}

const placesById = computed(() => new Map(places.value.map((place) => [place.id, place])))
const totalDistance = computed(() => legs.value.reduce((total, leg) => total + (Number(leg.distance) || 0), 0))

function formatDate(date: string | null): string {
  if (!date) return ''
  const [year, month, day] = date.slice(0, 10).split('-')
  return `${day}.${month}.${year}`
}

const dateRange = computed(() => {
  if (!trip.value?.start_date) return 'Termin do ustalenia'
  const start = formatDate(trip.value.start_date)
  if (!trip.value.end_date) return `Od ${start}`
  const end = formatDate(trip.value.end_date)
  return start === end ? start : `${start} – ${end}`
})

function formatDistance(value: string | number | null): string {
  const distance = Number(value)
  if (!Number.isFinite(distance)) return 'Dystans nieznany'
  return `${new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 1 }).format(distance)} km`
}

function transportLabel(transport: string): string {
  const labels: Record<string, string> = {
    car: 'Samochód', bus: 'Autobus', train: 'Pociąg', plane: 'Samolot',
    bicycle: 'Rower', walking: 'Pieszo', boat: 'Statek', other: 'Inny środek transportu',
  }
  return labels[transport.toLowerCase()] ?? transport
}

async function loadTrip() {
  isLoading.value = true
  error.value = ''
  try {
    const id = String(route.params.id)
    const [tripData, placeData, legData] = await Promise.all([
      getTrip(id), getTripPlaces(id), getTripLegs(id),
    ])
    trip.value = tripData
    places.value = placeData.sort((a, b) => a.position - b.position)
    legs.value = legData.sort((a, b) => a.position - b.position)
  } catch (err: unknown) {
    error.value = err instanceof ApiError && err.status === 404
      ? 'Nie znaleziono podróży o podanym identyfikatorze.'
      : err instanceof Error ? err.message : 'Nie udało się załadować szczegółów podróży.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => void loadTrip())
</script>

<template>
  <main class="details-page">
    <div class="details-container">
      <RouterLink class="back-link" :to="{ name: 'me' }">
        <span aria-hidden="true">←</span> Wróć do podróży
      </RouterLink>

      <div v-if="isLoading" class="status-card" role="status" aria-live="polite">
        <span class="spinner" aria-hidden="true"></span>
        <p>Ładowanie szczegółów podróży…</p>
      </div>

      <section v-else-if="error" class="status-card error-card" role="alert">
        <h1>Nie udało się otworzyć podróży</h1>
        <p>{{ error }}</p>
        <button class="button-secondary" type="button" @click="loadTrip">Spróbuj ponownie</button>
      </section>

      <template v-else-if="trip">
        <header class="trip-hero">
          <p class="eyebrow">Dziennik podróży</p>
          <div class="hero-title-row">
            <div>
              <h1>{{ trip.name }}</h1>
              <p class="trip-dates"><span aria-hidden="true">▦</span> {{ dateRange }}</p>
            </div>
            <RouterLink class="edit-link" :to="{ name: 'trip-edit', params: { id: trip.id } }">Edytuj podróż</RouterLink>
          </div>
          <p v-if="trip.description" class="trip-description">{{ trip.description }}</p>
          <div class="trip-stats" aria-label="Podsumowanie podróży">
            <div><strong>{{ places.length }}</strong><span>{{ places.length === 1 ? 'punkt' : 'punkty' }}</span></div>
            <div><strong>{{ legs.length }}</strong><span>{{ legs.length === 1 ? 'odcinek' : 'odcinków' }}</span></div>
            <div><strong>{{ formatDistance(totalDistance) }}</strong><span>łączny dystans</span></div>
          </div>
        </header>

        <section class="itinerary" aria-labelledby="itinerary-title">
          <div class="section-heading">
            <div>
              <p class="eyebrow">Plan trasy</p>
              <h2 id="itinerary-title">Punkty i odcinki</h2>
            </div>
            <span class="count-badge">{{ places.length }} {{ places.length === 1 ? 'punkt' : 'punktów' }}</span>
          </div>

          <div v-if="places.length === 0" class="empty-itinerary">
            <span class="empty-icon" aria-hidden="true">⌖</span>
            <h3>Trasa czeka na pierwszy punkt</h3>
            <p>Dodane miejsca i łączące je odcinki pojawią się tutaj w kolejności podróży.</p>
          </div>

          <ol v-else class="route-list">
            <li v-for="(place, index) in places" :key="place.id" class="route-stop">
              <article class="place-card">
                <span class="stop-marker" aria-hidden="true">{{ String(index + 1).padStart(2, '0') }}</span>
                <div class="place-copy">
                  <span class="place-label">Punkt {{ index + 1 }}</span>
                  <h3>{{ place.name }}</h3>
                  <p v-if="place.country_code" class="country-code">{{ place.country_code }}</p>
                </div>
                <span class="place-icon" aria-hidden="true">⌖</span>
              </article>

              <div v-if="index < places.length - 1" class="leg-row">
                <span class="route-line" aria-hidden="true"></span>
                <article v-if="legs.find((leg) => leg.from_place_id === place.id && leg.to_place_id === places[index + 1]?.id)" class="leg-card">
                  <template v-for="leg in legs.filter((item) => item.from_place_id === place.id && item.to_place_id === places[index + 1]?.id)" :key="leg.id">
                    <div class="leg-icon" aria-hidden="true">↗</div>
                    <div class="leg-copy">
                      <strong>{{ transportLabel(leg.transport) }}</strong>
                      <span>{{ place.name }} <span aria-hidden="true">→</span> {{ placesById.get(leg.to_place_id)?.name ?? 'Kolejny punkt' }}</span>
                    </div>
                    <span class="leg-distance">{{ formatDistance(leg.distance) }}</span>
                  </template>
                </article>
                <p v-else class="missing-leg">Brak informacji o odcinku</p>
              </div>
            </li>
          </ol>
        </section>

        <section class="extras-grid" aria-label="Znajomi i zdjęcia z podróży">
          <section class="extra-card" aria-labelledby="friends-title">
            <p class="eyebrow">Wspólna podróż</p>
            <h2 id="friends-title">Znajomi</h2>
            <form class="add-row" @submit.prevent="inviteFriend">
              <label class="sr-only" for="friend-search">Wyszukaj znajomych</label>
              <input id="friend-search" v-model="friendSearch" type="search" placeholder="Wyszukaj znajomych" autocomplete="off" />
              <button type="submit" class="add-button">Zaproś</button>
            </form>
            <p v-if="invitedFriends.length === 0" class="extra-empty">Nie zaproszono jeszcze znajomych do tej podróży.</p>
            <ul v-else class="extra-list">
              <li v-for="name in invitedFriends" :key="name">
                <span class="avatar-placeholder" aria-hidden="true">{{ name.charAt(0).toUpperCase() }}</span>
                <span class="extra-value">{{ name }}</span>
                <button type="button" class="remove-button" :aria-label="`Usuń ${name}`" @click="removeFriend(name)">Usuń</button>
              </li>
            </ul>
          </section>

          <section class="extra-card" aria-labelledby="photos-title">
            <p class="eyebrow">Wspomnienia</p>
            <h2 id="photos-title">Zdjęcia z podróży</h2>
            <p class="extra-intro">Dodaj linki do albumów ze zdjęciami, np. Google Photos lub Flickr.</p>
            <form class="add-row" @submit.prevent="addPhotoLink">
              <label class="sr-only" for="photo-link">Link do albumu ze zdjęciami</label>
              <input id="photo-link" v-model="photoLink" type="text" inputmode="url" placeholder="https://… lub www…" />
              <button type="submit" class="add-button">Dodaj link</button>
            </form>
            <p v-if="photoLinks.length === 0" class="extra-empty">Linki do albumów pojawią się tutaj.</p>
            <ul v-else class="extra-list photo-list">
              <li v-for="url in photoLinks" :key="url">
                <span class="photo-icon" aria-hidden="true">▧</span>
                <a class="extra-value portal-link" :href="url" target="_blank" rel="noopener noreferrer">{{ photoPortalLabel(url) }}<span class="sr-only"> (otwiera się w nowej karcie)</span></a>
                <button type="button" class="remove-button" :aria-label="`Usuń link ${photoPortalLabel(url)}`" @click="removePhotoLink(url)">Usuń</button>
              </li>
            </ul>
          </section>
          <p v-if="socialError" class="social-error" role="alert">{{ socialError }}</p>
          <p class="local-note">Listy znajomych i linków są na razie zapisywane tylko w tej przeglądarce.</p>
        </section>
      </template>
    </div>
  </main>
</template>

<style scoped>
.details-page { width: 100%; padding: 1rem 0 3rem; color: #17251e; }
.details-container { display: grid; gap: 1.25rem; width: min(100%, 58rem); margin: 0 auto; }
.back-link { display: inline-flex; align-items: center; gap: .5rem; width: fit-content; color: #386a54; font-weight: 700; }
.back-link span { font-size: 1.3rem; }
.trip-hero, .itinerary, .status-card { border: 1px solid #e2e9e3; border-radius: 1.25rem; background: #fff; box-shadow: 0 12px 32px rgb(30 55 40 / 5%); }
.trip-hero { padding: clamp(1.25rem, 5vw, 2.5rem); background: linear-gradient(135deg, #e9f5ef, #f5f2e9); }
.eyebrow { margin: 0 0 .55rem; color: #386a54; font-size: .75rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.hero-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
h1 { margin: 0; font-size: clamp(1.8rem, 5vw, 2.7rem); line-height: 1.1; }
.trip-dates { display: flex; align-items: center; gap: .5rem; margin: .8rem 0 0; color: #53645a; font-weight: 600; }
.edit-link, .button-secondary { flex: 0 0 auto; padding: .65rem .9rem; border: 1px solid #386a54; border-radius: .65rem; color: #294d3d; background: transparent; font: inherit; font-weight: 700; }
.trip-description { max-width: 65ch; margin: 1.25rem 0 0; color: #53645a; line-height: 1.65; white-space: pre-line; }
.trip-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; margin-top: 1.5rem; }
.trip-stats div { display: grid; gap: .2rem; padding: .9rem 1rem; border: 1px solid rgb(56 106 84 / 12%); border-radius: .8rem; background: rgb(255 255 255 / 65%); }
.trip-stats strong { color: #294d3d; font-size: 1.15rem; }
.trip-stats span { color: #647268; font-size: .8rem; }
.itinerary { padding: clamp(1.25rem, 5vw, 2rem); }
.section-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
.section-heading .eyebrow { margin-bottom: .25rem; }
h2 { margin: 0; font-size: 1.5rem; }
.count-badge { padding: .4rem .7rem; border-radius: 2rem; color: #386a54; background: #e9f5ef; font-size: .8rem; font-weight: 700; }
.route-list { padding: 0; margin: 0; list-style: none; }
.route-stop { position: relative; }
.place-card { position: relative; z-index: 1; display: flex; align-items: center; gap: 1rem; min-height: 6rem; padding: 1rem; border: 1px solid #e5ebe6; border-radius: .9rem; background: #fff; }
.stop-marker { display: grid; flex: 0 0 2.65rem; place-items: center; width: 2.65rem; height: 2.65rem; border-radius: 50%; color: #fff; background: #386a54; font-size: .78rem; font-weight: 800; }
.place-copy { min-width: 0; flex: 1; }
.place-label { color: #77847a; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.place-copy h3 { margin: .15rem 0 0; font-size: 1.1rem; overflow-wrap: anywhere; }
.country-code { margin: .2rem 0 0; color: #738078; font-size: .8rem; text-transform: uppercase; }
.place-icon { color: #90a398; font-size: 1.4rem; }
.leg-row { position: relative; display: flex; align-items: center; min-height: 5rem; padding: .6rem 0 .6rem 3.25rem; }
.route-line { position: absolute; top: 0; bottom: 0; left: 1.3rem; width: 2px; background: #cbd9ce; }
.leg-card { z-index: 1; display: flex; align-items: center; gap: .75rem; width: 100%; padding: .75rem 1rem; border: 1px solid #e8ede9; border-radius: .8rem; background: #f8faf8; }
.leg-icon { display: grid; flex: 0 0 2rem; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; color: #386a54; background: #e5f1e9; font-weight: 800; }
.leg-copy { display: grid; flex: 1; gap: .2rem; min-width: 0; }
.leg-copy strong { font-size: .88rem; }
.leg-copy span { overflow: hidden; color: #77847a; font-size: .78rem; text-overflow: ellipsis; white-space: nowrap; }
.leg-distance { color: #386a54; font-size: .82rem; font-weight: 800; white-space: nowrap; }
.missing-leg { z-index: 1; margin: 0; padding: .65rem .85rem; border-radius: .7rem; color: #78847b; background: #f5f7f5; font-size: .82rem; }
.empty-itinerary { display: grid; justify-items: center; padding: 3rem 1rem; border: 1px dashed #cdd9d0; border-radius: .9rem; text-align: center; }
.empty-icon { display: grid; place-items: center; width: 3rem; height: 3rem; margin-bottom: .8rem; border-radius: 50%; color: #386a54; background: #e9f5ef; font-size: 1.5rem; }
.empty-itinerary h3 { margin: 0; font-size: 1.1rem; }
.empty-itinerary p { max-width: 38ch; margin: .5rem 0 0; color: #6b786e; line-height: 1.5; }
.status-card { display: grid; justify-items: center; gap: .75rem; padding: 3rem 1rem; text-align: center; }
.extras-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.extra-card { min-width: 0; padding: clamp(1.1rem, 4vw, 1.5rem); border: 1px solid #e2e9e3; border-radius: 1.1rem; background: #fff; }
.extra-card h2 { font-size: 1.25rem; }
.extra-intro, .extra-empty { color: #6b786e; font-size: .88rem; line-height: 1.5; }
.add-row { display: flex; gap: .5rem; margin-top: 1rem; }
.add-row input { flex: 1; min-width: 0; min-height: 2.7rem; padding: .55rem .7rem; border: 1px solid #cbd5ce; border-radius: .6rem; color: #17251e; background: #fff; font: inherit; }
.add-button { padding: .55rem .8rem; border: 0; border-radius: .6rem; color: #fff; background: #386a54; font: inherit; font-weight: 700; white-space: nowrap; }
.extra-list { display: grid; gap: .45rem; padding: 0; margin: 1rem 0 0; list-style: none; }
.extra-list li { display: flex; align-items: center; gap: .65rem; min-width: 0; padding: .55rem .6rem; border-radius: .65rem; background: #f5f8f5; }
.avatar-placeholder, .photo-icon { display: grid; flex: 0 0 2rem; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; color: #386a54; background: #e5f1e9; font-size: .85rem; font-weight: 800; }
.extra-value { flex: 1; min-width: 0; overflow: hidden; color: #34443a; font-size: .85rem; text-overflow: ellipsis; white-space: nowrap; }
.photo-icon { border-radius: .5rem; font-size: 1.1rem; }
.portal-link { color: #386a54; font-weight: 700; text-decoration: underline; text-underline-offset: 2px; }
.remove-button { padding: .3rem .4rem; border: 0; color: #68756c; background: transparent; font: inherit; font-size: .75rem; cursor: pointer; }
.remove-button:hover { color: #b42318; text-decoration: underline; }
.social-error { grid-column: 1 / -1; margin: 0; color: #b42318; font-size: .85rem; }
.local-note { grid-column: 1 / -1; margin: -.5rem 0 0; color: #77847a; font-size: .78rem; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
.status-card p { margin: 0; color: #58665d; }
.error-card { border-color: #fecaca; background: #fffafa; }
.error-card h1 { font-size: 1.5rem; }
.spinner { width: 2rem; height: 2rem; border: 3px solid #e5e7eb; border-top-color: #386a54; border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 700px) { .extras-grid { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 560px) { .hero-title-row { flex-direction: column; } .trip-stats { gap: .45rem; } .trip-stats div { padding: .7rem .55rem; } .trip-stats strong { font-size: 1rem; } .trip-stats span { font-size: .72rem; } .leg-row { padding-left: 2.7rem; } .route-line { left: 1.05rem; } .leg-card { flex-wrap: wrap; gap: .5rem; padding: .65rem; } .leg-distance { margin-left: 2.5rem; } .add-row { flex-direction: column; } .add-button { min-height: 2.6rem; } }
@media (prefers-color-scheme: dark) { .details-page { color: #f3f4f6; } .back-link { color: #6ee7b7; } .trip-hero { border-color: #37483d; background: linear-gradient(135deg, #1a2e24, #2a2618); } .trip-hero, .itinerary, .status-card, .place-card, .extra-card { background-color: #1f2937; border-color: #374151; } .trip-dates, .trip-description, .empty-itinerary p, .status-card p { color: #a6b2a9; } .edit-link, .button-secondary { border-color: #6ee7b7; color: #6ee7b7; } .trip-stats div { border-color: #37483d; background: rgb(17 24 39 / 45%); } .trip-stats strong, .leg-distance { color: #6ee7b7; } .trip-stats span, .place-label, .country-code, .leg-copy span { color: #9ca3af; } .place-card, .leg-card, .missing-leg, .extra-list li { background: #263329; border-color: #37483d; } .count-badge, .empty-icon, .leg-icon, .avatar-placeholder, .photo-icon { color: #6ee7b7; background: #243a2e; } .route-line { background: #496454; } .empty-itinerary { border-color: #496454; } .error-card { background: #2b2020; } .extra-intro, .extra-empty, .extra-value, .remove-button, .local-note { color: #a6b2a9; } .portal-link { color: #6ee7b7; } .add-row input { color: #f3f4f6; background: #111827; border-color: #4b5563; } .social-error { color: #fca5a5; } }
</style>
