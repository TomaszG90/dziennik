<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { searchPlaces, type GeocodeResult } from '@/api/geocoding'
import MapView from '@/components/MapView.vue'
import {
  createTripLeg,
  createTripPlace,
  deleteTripLeg,
  deleteTripPlace,
  getTrip,
  getTripLegs,
  getTripPlaces,
  reorderTripPlaces,
  updateTripLeg,
  type Trip,
  type TripLeg,
  type TripPlace,
  type TripTransport,
} from '@/api/trips'
import { ApiError } from '@/api/types'
import { calculateDistanceKm } from '@/utils/distance'

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
const routeActionError = ref('')
const isSavingRoute = ref(false)
const placeForm = ref({ name: '', latitude: '', longitude: '', country_code: '' })
const selectedPlace = ref<GeocodeResult | null>(null)
const placeSearchResults = ref<GeocodeResult[]>([])
const isSearchingPlaces = ref(false)
const hasSearchedPlaces = ref(false)
const placeSearchError = ref('')
const transportByPair = ref<Record<string, TripTransport>>({})
let placeSearchTimer: ReturnType<typeof setTimeout> | undefined
let placeSearchGeneration = 0
const transports: Array<{ value: TripTransport; label: string }> = [
  { value: 'walking', label: 'Pieszo' },
  { value: 'car', label: 'Samochód' },
  { value: 'plane', label: 'Samolot' },
  { value: 'train', label: 'Pociąg' },
  { value: 'other', label: 'Inny' },
]

watch(
  () => placeForm.value.name,
  (query) => {
    if (placeSearchTimer) clearTimeout(placeSearchTimer)
    const generation = ++placeSearchGeneration
    placeSearchResults.value = []
    placeSearchError.value = ''
    hasSearchedPlaces.value = false

    if (selectedPlace.value?.name === query) return
    selectedPlace.value = null
    placeForm.value.latitude = ''
    placeForm.value.longitude = ''
    placeForm.value.country_code = ''

    const normalizedQuery = query.trim()
    if (normalizedQuery.length < 2) {
      isSearchingPlaces.value = false
      return
    }

    isSearchingPlaces.value = true
    placeSearchTimer = setTimeout(async () => {
      try {
        const results = await searchPlaces(normalizedQuery)
        if (generation === placeSearchGeneration) {
          placeSearchResults.value = results.filter(
            (result) => result.name && result.latitude !== null && result.longitude !== null,
          )
          hasSearchedPlaces.value = true
        }
      } catch (err: unknown) {
        if (generation === placeSearchGeneration) {
          placeSearchError.value =
            err instanceof Error ? err.message : 'Nie udało się wyszukać miejsca.'
        }
      } finally {
        if (generation === placeSearchGeneration) isSearchingPlaces.value = false
      }
    }, 300)
  },
)

const storageKey = computed(() => `trip-details-${String(route.params.id)}-extras`)

watch(
  storageKey,
  (key) => {
    try {
      const saved = JSON.parse(localStorage.getItem(key) ?? '{}') as {
        friends?: string[]
        photoLinks?: string[]
      }
      invitedFriends.value = saved.friends ?? []
      photoLinks.value = saved.photoLinks ?? []
    } catch {
      invitedFriends.value = []
      photoLinks.value = []
    }
  },
  { immediate: true },
)

function saveExtras() {
  localStorage.setItem(
    storageKey.value,
    JSON.stringify({ friends: invitedFriends.value, photoLinks: photoLinks.value }),
  )
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
  try {
    return new URL(url).hostname.replace(/^www\./, '')
  } catch {
    return url
  }
}

const placesById = computed(() => new Map(places.value.map((place) => [place.id, place])))
const visitedCountries = computed(() => [
  ...new Set(
    places.value
      .map((place) => place.country_code?.trim().toUpperCase())
      .filter((countryCode): countryCode is string => Boolean(countryCode)),
  ),
])

function legDistance(leg: TripLeg): number | null {
  if (leg.distance !== null && leg.distance !== '') {
    const distance = Number(leg.distance)
    if (Number.isFinite(distance)) return distance
  }
  const fromPlace = placesById.value.get(leg.from_place_id)
  const toPlace = placesById.value.get(leg.to_place_id)
  return fromPlace && toPlace ? calculateDistanceKm(fromPlace, toPlace) : null
}

const totalDistance = computed(() => {
  if (legs.value.length === 0) return 0
  const distances = legs.value.map(legDistance)
  if (distances.some((distance) => distance === null)) return null
  return distances.reduce<number>((total, distance) => total + (distance ?? 0), 0)
})

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
  if (value === null || value === '') return 'Dystans nieznany'
  const distance = Number(value)
  if (!Number.isFinite(distance)) return 'Dystans nieznany'
  return `${new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 1 }).format(distance)} km`
}

function tripId(): string {
  return String(route.params.id)
}

function showRouteActionError(err: unknown) {
  routeActionError.value = err instanceof Error ? err.message : 'Nie udało się zapisać zmian trasy.'
}

function selectPlace(result: GeocodeResult) {
  if (!result.name || result.latitude === null || result.longitude === null) return
  selectedPlace.value = result
  placeForm.value = {
    name: result.name,
    latitude: String(result.latitude),
    longitude: String(result.longitude),
    country_code: result.country_code?.toUpperCase() ?? '',
  }
  placeSearchResults.value = []
  placeSearchError.value = ''
  hasSearchedPlaces.value = false
}

function placeSuggestionDetails(result: GeocodeResult): string {
  const street = [result.street, result.housenumber].filter(Boolean).join(' ')
  const city = [result.postcode, result.city].filter(Boolean).join(' ')
  const region = [result.district, result.county, result.state]
    .filter(
      (value, index, values): value is string => Boolean(value) && values.indexOf(value) === index,
    )
    .join(', ')

  return [street, city, region, result.country, result.country_code?.toUpperCase()]
    .filter((value): value is string => Boolean(value))
    .filter((value, index, values) => values.indexOf(value) === index)
    .join(' · ')
}

async function addPlace() {
  if (!trip.value) return
  if (!selectedPlace.value) {
    placeSearchError.value = 'Wybierz miejsce z listy podpowiedzi, aby uzupełnić jego lokalizację.'
    return
  }
  routeActionError.value = ''
  isSavingRoute.value = true
  try {
    const place = await createTripPlace(tripId(), {
      name: placeForm.value.name.trim(),
      latitude: Number(placeForm.value.latitude),
      longitude: Number(placeForm.value.longitude),
      country_code: placeForm.value.country_code.trim().toUpperCase() || null,
    })
    const updatedPlaces = [...places.value, place].sort((a, b) => a.position - b.position)
    places.value = updatedPlaces
    const placeIndex = updatedPlaces.findIndex((item) => item.id === place.id)
    const previousPlace = updatedPlaces[placeIndex - 1]
    if (
      previousPlace &&
      !legs.value.some(
        (leg) => leg.from_place_id === previousPlace.id && leg.to_place_id === place.id,
      )
    ) {
      const leg = await createTripLeg(tripId(), {
        from_place_id: previousPlace.id,
        to_place_id: place.id,
        transport: 'other',
        distance: calculateDistanceKm(previousPlace, place),
      })
      legs.value = [...legs.value, leg].sort((a, b) => a.position - b.position)
    }
    selectedPlace.value = null
    placeForm.value = { name: '', latitude: '', longitude: '', country_code: '' }
  } catch (err: unknown) {
    showRouteActionError(err)
  } finally {
    isSavingRoute.value = false
  }
}

async function removePlace(place: TripPlace) {
  if (!trip.value) return
  routeActionError.value = ''
  isSavingRoute.value = true
  try {
    await deleteTripPlace(tripId(), place.id)
    places.value = places.value.filter((item) => item.id !== place.id)
    legs.value = legs.value.filter(
      (leg) => leg.from_place_id !== place.id && leg.to_place_id !== place.id,
    )
  } catch (err: unknown) {
    showRouteActionError(err)
  } finally {
    isSavingRoute.value = false
  }
}

async function movePlace(index: number, offset: -1 | 1) {
  if (!trip.value) return
  const targetIndex = index + offset
  if (targetIndex < 0 || targetIndex >= places.value.length) return

  const reordered = [...places.value]
  const currentPlace = reordered[index]
  const targetPlace = reordered[targetIndex]
  if (!currentPlace || !targetPlace) return
  reordered[index] = targetPlace
  reordered[targetIndex] = currentPlace
  routeActionError.value = ''
  isSavingRoute.value = true
  try {
    places.value = (
      await reorderTripPlaces(
        tripId(),
        reordered.map((place) => place.id),
      )
    ).sort((a, b) => a.position - b.position)
    const orderedLegs = [...legs.value].sort((a, b) => a.position - b.position)
    for (const [legIndex, leg] of orderedLegs.entries()) {
      const fromPlace = places.value[legIndex]
      const toPlace = places.value[legIndex + 1]
      if (!fromPlace || !toPlace) continue

      const updatedLeg = await updateTripLeg(tripId(), leg.id, {
        from_place_id: fromPlace.id,
        to_place_id: toPlace.id,
        distance: calculateDistanceKm(fromPlace, toPlace),
      })
      legs.value = legs.value.map((item) => (item.id === leg.id ? updatedLeg : item))
    }
  } catch (err: unknown) {
    showRouteActionError(err)
  } finally {
    isSavingRoute.value = false
  }
}

function pairKey(fromPlaceId: number, toPlaceId: number): string {
  return `${fromPlaceId}-${toPlaceId}`
}

function setPairTransport(fromPlaceId: number, toPlaceId: number, event: Event) {
  transportByPair.value[pairKey(fromPlaceId, toPlaceId)] = (event.target as HTMLSelectElement)
    .value as TripTransport
}

async function addLeg(fromPlace: TripPlace, toPlace: TripPlace) {
  if (!trip.value) return
  const key = pairKey(fromPlace.id, toPlace.id)
  routeActionError.value = ''
  isSavingRoute.value = true
  try {
    const leg = await createTripLeg(tripId(), {
      from_place_id: fromPlace.id,
      to_place_id: toPlace.id,
      transport: transportByPair.value[key] ?? 'other',
      distance: calculateDistanceKm(fromPlace, toPlace),
    })
    legs.value = [...legs.value, leg].sort((a, b) => a.position - b.position)
  } catch (err: unknown) {
    showRouteActionError(err)
  } finally {
    isSavingRoute.value = false
  }
}

async function changeLegTransport(leg: TripLeg, event: Event) {
  if (!trip.value) return
  const transport = (event.target as HTMLSelectElement).value as TripTransport
  routeActionError.value = ''
  isSavingRoute.value = true
  try {
    const updatedLeg = await updateTripLeg(tripId(), leg.id, { transport })
    legs.value = legs.value.map((item) => (item.id === leg.id ? updatedLeg : item))
  } catch (err: unknown) {
    showRouteActionError(err)
  } finally {
    isSavingRoute.value = false
  }
}

async function removeLeg(leg: TripLeg) {
  if (!trip.value) return
  routeActionError.value = ''
  isSavingRoute.value = true
  try {
    await deleteTripLeg(tripId(), leg.id)
    legs.value = legs.value.filter((item) => item.id !== leg.id)
  } catch (err: unknown) {
    showRouteActionError(err)
  } finally {
    isSavingRoute.value = false
  }
}

async function loadTrip() {
  isLoading.value = true
  error.value = ''
  try {
    const id = String(route.params.id)
    const [tripData, placeData, legData] = await Promise.all([
      getTrip(id),
      getTripPlaces(id),
      getTripLegs(id),
    ])
    trip.value = tripData
    places.value = placeData.sort((a, b) => a.position - b.position)
    legs.value = legData.sort((a, b) => a.position - b.position)
  } catch (err: unknown) {
    error.value =
      err instanceof ApiError && err.status === 404
        ? 'Nie znaleziono podróży o podanym identyfikatorze.'
        : err instanceof Error
          ? err.message
          : 'Nie udało się załadować szczegółów podróży.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => void loadTrip())
onBeforeUnmount(() => {
  if (placeSearchTimer) clearTimeout(placeSearchTimer)
  placeSearchGeneration += 1
})
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
            <RouterLink class="edit-link" :to="{ name: 'trip-edit', params: { id: trip.id } }"
              >Edytuj podróż</RouterLink
            >
          </div>
          <p v-if="trip.description" class="trip-description">{{ trip.description }}</p>
          <div class="trip-stats" aria-label="Podsumowanie podróży">
            <div class="trip-stat">
              <span class="trip-stat-label">Punkty</span>
              <strong>{{ places.length }}</strong
              ><span class="trip-stat-detail">{{ places.length === 1 ? 'punkt' : 'punktów' }}</span>
            </div>
            <div class="trip-stat trip-stat-countries">
              <span class="trip-stat-label">Kraje</span>
              <div
                v-if="visitedCountries.length"
                class="country-code-list"
                aria-label="Odwiedzone kraje"
              >
                <span v-for="countryCode in visitedCountries" :key="countryCode">{{
                  countryCode
                }}</span>
              </div>
              <span v-else class="trip-stat-detail">Brak danych</span>
            </div>
            <div class="trip-stat trip-stat-guests">
              <span class="trip-stat-label">Goście</span>
              <div
                v-if="invitedFriends.length"
                class="guest-avatars"
                aria-label="Zaproszeni goście"
              >
                <span
                  v-for="name in invitedFriends"
                  :key="name"
                  class="guest-avatar"
                  role="img"
                  :aria-label="name"
                  :title="name"
                >
                  {{ name.trim().charAt(0).toUpperCase() }}
                </span>
              </div>
              <span v-else class="trip-stat-detail">Brak gości</span>
            </div>
            <div class="trip-stat">
              <span class="trip-stat-label">Łączny dystans</span>
              <strong>{{ formatDistance(totalDistance) }}</strong>
            </div>
          </div>
        </header>

        <div class="itinerary-layout">
          <section class="itinerary" aria-labelledby="itinerary-title">
            <div class="section-heading">
              <div>
                <p class="eyebrow">Plan trasy</p>
                <h2 id="itinerary-title">Punkty i odcinki</h2>
              </div>
              <span class="count-badge"
                >{{ places.length }} {{ places.length === 1 ? 'punkt' : 'punktów' }}</span
              >
            </div>

            <form class="place-form" @submit.prevent="addPlace">
              <h3>Dodaj punkt trasy</h3>
              <div class="place-form-fields">
                <div class="place-search-field">
                  <label for="place-search-input">Nazwa miejsca</label>
                  <input
                    id="place-search-input"
                    v-model="placeForm.name"
                    required
                    maxlength="255"
                    minlength="2"
                    autocomplete="off"
                    placeholder="Wpisz nazwę miasta lub miejsca"
                    aria-describedby="place-search-help"
                    aria-controls="place-search-results"
                    :aria-expanded="placeSearchResults.length > 0"
                  />
                  <span id="place-search-help" class="field-help"
                    >Wybierz miejsce z podpowiedzi, aby uzupełnić współrzędne i kraj.</span
                  >
                  <span v-if="isSearchingPlaces" class="field-help" role="status"
                    >Wyszukiwanie miejsc…</span
                  >
                  <ul
                    v-if="placeSearchResults.length"
                    id="place-search-results"
                    class="place-search-results"
                    aria-label="Wyniki wyszukiwania miejsc"
                  >
                    <li
                      v-for="(result, index) in placeSearchResults"
                      :key="`${result.name}-${result.street}-${result.housenumber}-${result.city}-${result.state}-${result.latitude}-${result.longitude}`"
                    >
                      <button
                        type="button"
                        :data-testid="`place-suggestion-${index}`"
                        @click="selectPlace(result)"
                      >
                        <strong>{{ result.name }}</strong>
                        <span class="suggestion-details">{{ placeSuggestionDetails(result) }}</span>
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
              <p
                v-if="selectedPlace"
                class="selected-place-summary"
                data-testid="selected-place-summary"
              >
                Wybrano: {{ selectedPlace.name }}
                <span v-if="placeSuggestionDetails(selectedPlace)">
                  {{ placeSuggestionDetails(selectedPlace) }}
                </span>
                <small>{{ placeForm.latitude }}, {{ placeForm.longitude }}</small>
              </p>
              <p v-if="placeSearchError" class="route-action-error" role="alert">
                {{ placeSearchError }}
              </p>
              <p
                v-else-if="hasSearchedPlaces && placeSearchResults.length === 0"
                class="field-help"
                role="status"
              >
                Nie znaleziono pasujących miejsc. Spróbuj wpisać inną nazwę.
              </p>
              <button class="add-button" type="submit" :disabled="isSavingRoute">
                Dodaj punkt
              </button>
              <p class="distance-note">
                Dystans odcinków jest szacowany w linii prostej na podstawie współrzędnych, także
                między różnymi krajami.
              </p>
            </form>

            <p v-if="routeActionError" class="route-action-error" role="alert">
              {{ routeActionError }}
            </p>

            <div v-if="places.length === 0" class="empty-itinerary">
              <span class="empty-icon" aria-hidden="true">⌖</span>
              <h3>Trasa czeka na pierwszy punkt</h3>
              <p>Dodane miejsca i łączące je odcinki pojawią się tutaj w kolejności podróży.</p>
            </div>

            <ol v-else class="route-list">
              <li
                v-for="(place, index) in places"
                :key="place.id"
                class="route-stop"
                data-testid="route-stop"
              >
                <article class="place-card">
                  <span class="stop-marker" aria-hidden="true">{{
                    String(index + 1).padStart(2, '0')
                  }}</span>
                  <div class="place-copy">
                    <span class="place-label">Punkt {{ index + 1 }}</span>
                    <h3>{{ place.name }}</h3>
                    <p v-if="place.country_code" class="country-code">{{ place.country_code }}</p>
                  </div>
                  <div class="place-actions">
                    <button
                      type="button"
                      class="icon-button"
                      :aria-label="`Przenieś ${place.name} wyżej`"
                      :disabled="isSavingRoute || index === 0"
                      @click="movePlace(index, -1)"
                    >
                      ↑
                    </button>
                    <button
                      type="button"
                      class="icon-button"
                      :aria-label="`Przenieś ${place.name} niżej`"
                      :disabled="isSavingRoute || index === places.length - 1"
                      @click="movePlace(index, 1)"
                    >
                      ↓
                    </button>
                    <button
                      type="button"
                      class="remove-button"
                      :disabled="isSavingRoute"
                      :aria-label="`Usuń punkt ${place.name}`"
                      @click="removePlace(place)"
                    >
                      Usuń
                    </button>
                  </div>
                </article>

                <div v-if="index < places.length - 1" class="leg-row">
                  <span class="route-line" aria-hidden="true"></span>
                  <article
                    v-for="leg in legs.filter(
                      (item) =>
                        item.from_place_id === place.id &&
                        item.to_place_id === places[index + 1]?.id,
                    )"
                    :key="leg.id"
                    class="leg-card"
                    data-testid="route-leg"
                  >
                    <div class="leg-icon" aria-hidden="true">↗</div>
                    <div class="leg-copy">
                      <label class="sr-only" :for="`transport-${leg.id}`">Środek transportu</label>
                      <select
                        :id="`transport-${leg.id}`"
                        :value="leg.transport"
                        :disabled="isSavingRoute"
                        @change="changeLegTransport(leg, $event)"
                      >
                        <option
                          v-for="transport in transports"
                          :key="transport.value"
                          :value="transport.value"
                        >
                          {{ transport.label }}
                        </option>
                      </select>
                      <span
                        >{{ place.name }} <span aria-hidden="true">→</span>
                        {{ placesById.get(leg.to_place_id)?.name ?? 'Kolejny punkt' }}</span
                      >
                    </div>
                    <span class="leg-distance">{{ formatDistance(legDistance(leg)) }}</span>
                    <button
                      type="button"
                      class="remove-button"
                      :disabled="isSavingRoute"
                      :aria-label="`Usuń odcinek ${place.name} – ${placesById.get(leg.to_place_id)?.name ?? 'kolejny punkt'}`"
                      @click="removeLeg(leg)"
                    >
                      Usuń
                    </button>
                  </article>
                  <form
                    v-if="
                      !legs.some(
                        (leg) =>
                          leg.from_place_id === place.id &&
                          leg.to_place_id === places[index + 1]?.id,
                      )
                    "
                    class="missing-leg"
                    @submit.prevent="addLeg(place, places[index + 1]!)"
                  >
                    <span>Brak informacji o odcinku</span>
                    <label
                      class="sr-only"
                      :for="`new-transport-${place.id}-${places[index + 1]?.id}`"
                      >Wybierz środek transportu</label
                    >
                    <select
                      :id="`new-transport-${place.id}-${places[index + 1]?.id}`"
                      :value="transportByPair[pairKey(place.id, places[index + 1]!.id)] ?? 'other'"
                      :disabled="isSavingRoute"
                      @change="setPairTransport(place.id, places[index + 1]!.id, $event)"
                    >
                      <option
                        v-for="transport in transports"
                        :key="transport.value"
                        :value="transport.value"
                      >
                        {{ transport.label }}
                      </option>
                    </select>
                    <button class="add-button" type="submit" :disabled="isSavingRoute">
                      Dodaj odcinek
                    </button>
                  </form>
                </div>
              </li>
            </ol>
            <p class="itinerary-hint">
              Odcinki układają się według kolejności punktów trasy. Przesuń punkty, aby zmienić
              przebieg podróży.
            </p>
          </section>

          <aside class="map-panel" aria-labelledby="route-map-title">
            <div class="map-panel-heading">
              <div>
                <p class="eyebrow">Podgląd trasy</p>
                <h2 id="route-map-title">Mapa podróży</h2>
              </div>
              <span class="count-badge">{{ places.length }} punktów</span>
            </div>
            <MapView
              class="map-canvas"
              data-testid="route-map-canvas"
              aria-label="Mapa podróży"
            />
            <p class="map-panel-note">
              Mapa bazowa. Markery punktów i przebieg trasy pojawią się w kolejnych etapach.
            </p>
          </aside>
        </div>

        <section class="extras-grid" aria-label="Znajomi i zdjęcia z podróży">
          <section class="extra-card" aria-labelledby="friends-title">
            <p class="eyebrow">Wspólna podróż</p>
            <h2 id="friends-title">Znajomi</h2>
            <form class="add-row" @submit.prevent="inviteFriend">
              <label class="sr-only" for="friend-search">Wyszukaj znajomych</label>
              <input
                id="friend-search"
                v-model="friendSearch"
                type="search"
                placeholder="Wyszukaj znajomych"
                autocomplete="off"
              />
              <button type="submit" class="add-button">Zaproś</button>
            </form>
            <p v-if="invitedFriends.length === 0" class="extra-empty">
              Nie zaproszono jeszcze znajomych do tej podróży.
            </p>
            <ul v-else class="extra-list">
              <li v-for="name in invitedFriends" :key="name">
                <span class="avatar-placeholder" aria-hidden="true">{{
                  name.charAt(0).toUpperCase()
                }}</span>
                <span class="extra-value">{{ name }}</span>
                <button
                  type="button"
                  class="remove-button"
                  :aria-label="`Usuń ${name}`"
                  @click="removeFriend(name)"
                >
                  Usuń
                </button>
              </li>
            </ul>
          </section>

          <section class="extra-card" aria-labelledby="photos-title">
            <p class="eyebrow">Wspomnienia</p>
            <h2 id="photos-title">Zdjęcia z podróży</h2>
            <p class="extra-intro">
              Dodaj linki do albumów ze zdjęciami, np. Google Photos lub Flickr.
            </p>
            <form class="add-row" @submit.prevent="addPhotoLink">
              <label class="sr-only" for="photo-link">Link do albumu ze zdjęciami</label>
              <input
                id="photo-link"
                v-model="photoLink"
                type="text"
                inputmode="url"
                placeholder="https://… lub www…"
              />
              <button type="submit" class="add-button">Dodaj link</button>
            </form>
            <p v-if="photoLinks.length === 0" class="extra-empty">
              Linki do albumów pojawią się tutaj.
            </p>
            <ul v-else class="extra-list photo-list">
              <li v-for="url in photoLinks" :key="url">
                <span class="photo-icon" aria-hidden="true">▧</span>
                <a
                  class="extra-value portal-link"
                  :href="url"
                  target="_blank"
                  rel="noopener noreferrer"
                  >{{ photoPortalLabel(url)
                  }}<span class="sr-only"> (otwiera się w nowej karcie)</span></a
                >
                <button
                  type="button"
                  class="remove-button"
                  :aria-label="`Usuń link ${photoPortalLabel(url)}`"
                  @click="removePhotoLink(url)"
                >
                  Usuń
                </button>
              </li>
            </ul>
          </section>
          <p v-if="socialError" class="social-error" role="alert">{{ socialError }}</p>
          <p class="local-note">
            Listy znajomych i linków są na razie zapisywane tylko w tej przeglądarce.
          </p>
        </section>
      </template>
    </div>
  </main>
</template>

<style scoped>
.details-page {
  width: 100%;
  padding: 1rem 0 3rem;
  color: #17251e;
}
.details-container {
  display: grid;
  gap: 1.25rem;
  width: min(100% - 2rem, 84rem);
  margin: 0 auto;
}
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  width: fit-content;
  color: #386a54;
  font-weight: 700;
}
.back-link span {
  font-size: 1.3rem;
}
.trip-hero,
.itinerary,
.status-card {
  border: 1px solid #e2e9e3;
  border-radius: 1.25rem;
  background: #fff;
  box-shadow: 0 12px 32px rgb(30 55 40 / 5%);
}
.trip-hero {
  padding: clamp(1.25rem, 5vw, 2.5rem);
  background: linear-gradient(135deg, #e9f5ef, #f5f2e9);
}
.eyebrow {
  margin: 0 0 0.55rem;
  color: #386a54;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
.hero-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}
h1 {
  margin: 0;
  font-size: clamp(1.8rem, 5vw, 2.7rem);
  line-height: 1.1;
}
.trip-dates {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.8rem 0 0;
  color: #53645a;
  font-weight: 600;
}
.edit-link,
.button-secondary {
  flex: 0 0 auto;
  padding: 0.65rem 0.9rem;
  border: 1px solid #386a54;
  border-radius: 0.65rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-weight: 700;
}
.trip-description {
  max-width: 65ch;
  margin: 1.25rem 0 0;
  color: #53645a;
  line-height: 1.65;
  white-space: pre-line;
}
.trip-stats {
  display: grid;
  grid-template-columns: minmax(5rem, 0.65fr) repeat(2, minmax(8rem, 1fr)) minmax(9rem, 1.15fr);
  gap: 0.75rem;
  margin-top: 1.5rem;
}
.trip-stats > .trip-stat {
  display: grid;
  align-content: start;
  gap: 0.45rem;
  min-width: 0;
  min-height: 6rem;
  padding: 0.9rem 1rem;
  border: 1px solid rgb(56 106 84 / 12%);
  border-radius: 0.8rem;
  background: rgb(255 255 255 / 65%);
}
.trip-stats strong {
  color: #294d3d;
  font-size: 1.15rem;
  overflow-wrap: anywhere;
}
.trip-stat-label {
  color: #647268;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.trip-stat-detail {
  color: #647268;
  font-size: 0.8rem;
}
.country-code-list,
.guest-avatars {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  min-height: 1.5rem;
}
.country-code-list span {
  padding: 0.2rem 0.4rem;
  border-radius: 0.35rem;
  color: #386a54;
  background: #e9f5ef;
  font-size: 0.72rem;
  font-weight: 800;
}
.guest-avatar {
  display: grid;
  width: 1.65rem;
  height: 1.65rem;
  place-items: center;
  border: 2px solid #fff;
  border-radius: 50%;
  color: #fff;
  background: #386a54;
  font-size: 0.7rem;
  font-weight: 800;
}
.itinerary {
  padding: clamp(1.25rem, 5vw, 2rem);
}
.itinerary-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(20rem, 0.9fr);
  align-items: start;
  gap: 1.25rem;
}
.map-panel {
  position: sticky;
  top: 1.25rem;
  display: grid;
  gap: 1rem;
  min-width: 0;
  padding: clamp(1.25rem, 3vw, 2rem);
  border: 1px solid #e2e9e3;
  border-radius: 1.25rem;
  background: #fff;
  box-shadow: 0 12px 32px rgb(30 55 40 / 5%);
}
.map-panel-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}
.map-panel-heading .eyebrow {
  margin-bottom: 0.25rem;
}
.map-canvas {
  display: block;
  width: 100%;
  height: clamp(20rem, 55vh, 34rem);
  min-height: 20rem;
  overflow: hidden;
  border: 1px solid #d5e0d7;
  border-radius: 0.9rem;
}
.map-panel-note {
  margin: 0;
  color: #6b786e;
  font-size: 0.82rem;
  line-height: 1.5;
}
.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.section-heading .eyebrow {
  margin-bottom: 0.25rem;
}
h2 {
  margin: 0;
  font-size: 1.5rem;
}
.count-badge {
  padding: 0.4rem 0.7rem;
  border-radius: 2rem;
  color: #386a54;
  background: #e9f5ef;
  font-size: 0.8rem;
  font-weight: 700;
}
.place-form {
  display: grid;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  padding: 1rem;
  border: 1px solid #e5ebe6;
  border-radius: 0.9rem;
  background: #f8faf8;
}
.place-form h3 {
  margin: 0;
  font-size: 1rem;
}
.place-form-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}
.place-form-fields label {
  display: grid;
  gap: 0.3rem;
  color: #53645a;
  font-size: 0.8rem;
  font-weight: 600;
}
.place-search-field {
  display: grid;
  gap: 0.3rem;
  position: relative;
  grid-column: 1 / -1;
}
.field-help {
  color: #6b786e;
  font-size: 0.75rem;
  font-weight: 400;
}
.place-search-results {
  position: absolute;
  z-index: 3;
  top: calc(100% - 1.4rem);
  right: 0;
  left: 0;
  max-height: 15rem;
  overflow: auto;
  padding: 0.25rem;
  margin: 0;
  border: 1px solid #cbd5ce;
  border-radius: 0.6rem;
  background: #fff;
  box-shadow: 0 0.5rem 1rem rgb(0 0 0 / 12%);
  list-style: none;
}
.place-search-results button {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  width: 100%;
  padding: 0.6rem;
  border: 0;
  border-radius: 0.4rem;
  color: #17251e;
  background: transparent;
  text-align: left;
  font: inherit;
  cursor: pointer;
}
.place-search-results button:hover,
.place-search-results button:focus-visible {
  background: #e9f5ef;
  outline: none;
}
.place-search-results button span {
  color: #6b786e;
  font-size: 0.8rem;
}
.place-search-results .suggestion-details {
  display: block;
  max-width: 60%;
  overflow-wrap: anywhere;
  text-align: right;
  white-space: normal;
  word-spacing: normal;
}
.selected-place-summary {
  display: grid;
  gap: 0.2rem;
  margin: 0;
  color: #294d3d;
  font-size: 0.85rem;
  font-weight: 700;
}
.selected-place-summary small,
.distance-note {
  color: #6b786e;
  font-size: 0.75rem;
  font-weight: 400;
}
.distance-note {
  margin: 0;
}
.place-form input,
.leg-card select,
.missing-leg select {
  min-width: 0;
  min-height: 2.5rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid #cbd5ce;
  border-radius: 0.55rem;
  color: #17251e;
  background: #fff;
  font: inherit;
}
.place-search-results {
  border-color: #4b5563;
  background: #111827;
}
.place-search-results button {
  color: #f3f4f6;
}
.place-search-results button:hover,
.place-search-results button:focus-visible {
  background: #263329;
}
.place-search-results button span {
  color: #9ca3af;
}
.place-search-results .suggestion-details {
  color: #9ca3af;
}
.place-form .add-button {
  justify-self: start;
}
.route-action-error {
  margin: 0 0 1rem;
  color: #b42318;
  font-size: 0.9rem;
}
.route-list {
  padding: 0;
  margin: 0;
  list-style: none;
}
.route-stop {
  position: relative;
}
.place-card {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 1rem;
  min-height: 6rem;
  padding: 1rem;
  border: 1px solid #e5ebe6;
  border-radius: 0.9rem;
  background: #fff;
}
.stop-marker {
  display: grid;
  flex: 0 0 2.65rem;
  place-items: center;
  width: 2.65rem;
  height: 2.65rem;
  border-radius: 50%;
  color: #fff;
  background: #386a54;
  font-size: 0.78rem;
  font-weight: 800;
}
.place-copy {
  min-width: 0;
  flex: 1;
}
.place-label {
  color: #77847a;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.place-copy h3 {
  margin: 0.15rem 0 0;
  font-size: 1.1rem;
  overflow-wrap: anywhere;
}
.country-code {
  margin: 0.2rem 0 0;
  color: #738078;
  font-size: 0.8rem;
  text-transform: uppercase;
}
.place-icon {
  color: #90a398;
  font-size: 1.4rem;
}
.place-actions {
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.icon-button {
  display: grid;
  width: 2rem;
  height: 2rem;
  place-items: center;
  border: 1px solid #cbd9ce;
  border-radius: 0.5rem;
  color: #386a54;
  background: #fff;
  font: inherit;
  cursor: pointer;
}
.icon-button:disabled,
.remove-button:disabled,
.add-button:disabled,
.leg-card select:disabled,
.missing-leg select:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}
.leg-row {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 5rem;
  padding: 0.6rem 0 0.6rem 3.25rem;
}
.route-line {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 1.3rem;
  width: 2px;
  background: #cbd9ce;
}
.leg-card {
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #e8ede9;
  border-radius: 0.8rem;
  background: #f8faf8;
}
.leg-card select {
  min-height: 2rem;
  padding: 0.2rem 0.4rem;
  font-size: 0.85rem;
  font-weight: 700;
}
.leg-icon {
  display: grid;
  flex: 0 0 2rem;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  color: #386a54;
  background: #e5f1e9;
  font-weight: 800;
}
.leg-copy {
  display: grid;
  flex: 1;
  gap: 0.2rem;
  min-width: 0;
}
.leg-copy strong {
  font-size: 0.88rem;
}
.leg-copy span {
  overflow: hidden;
  color: #77847a;
  font-size: 0.78rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.leg-distance {
  color: #386a54;
  font-size: 0.82rem;
  font-weight: 800;
  white-space: nowrap;
}
.missing-leg {
  z-index: 1;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  padding: 0.65rem 0.85rem;
  border-radius: 0.7rem;
  color: #78847b;
  background: #f5f7f5;
  font-size: 0.82rem;
}
.missing-leg select {
  min-height: 2.1rem;
  padding: 0.25rem 0.4rem;
  font-size: 0.8rem;
}
.missing-leg .add-button {
  padding: 0.4rem 0.6rem;
  font-size: 0.8rem;
}
.itinerary-hint {
  margin: 1rem 0 0;
  color: #6b786e;
  font-size: 0.82rem;
}
.empty-itinerary {
  display: grid;
  justify-items: center;
  padding: 3rem 1rem;
  border: 1px dashed #cdd9d0;
  border-radius: 0.9rem;
  text-align: center;
}
.empty-icon {
  display: grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  margin-bottom: 0.8rem;
  border-radius: 50%;
  color: #386a54;
  background: #e9f5ef;
  font-size: 1.5rem;
}
.empty-itinerary h3 {
  margin: 0;
  font-size: 1.1rem;
}
.empty-itinerary p {
  max-width: 38ch;
  margin: 0.5rem 0 0;
  color: #6b786e;
  line-height: 1.5;
}
.status-card {
  display: grid;
  justify-items: center;
  gap: 0.75rem;
  padding: 3rem 1rem;
  text-align: center;
}
.extras-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}
.extra-card {
  min-width: 0;
  padding: clamp(1.1rem, 4vw, 1.5rem);
  border: 1px solid #e2e9e3;
  border-radius: 1.1rem;
  background: #fff;
}
.extra-card h2 {
  font-size: 1.25rem;
}
.extra-intro,
.extra-empty {
  color: #6b786e;
  font-size: 0.88rem;
  line-height: 1.5;
}
.add-row {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
}
.add-row input {
  flex: 1;
  min-width: 0;
  min-height: 2.7rem;
  padding: 0.55rem 0.7rem;
  border: 1px solid #cbd5ce;
  border-radius: 0.6rem;
  color: #17251e;
  background: #fff;
  font: inherit;
}
.add-button {
  padding: 0.55rem 0.8rem;
  border: 0;
  border-radius: 0.6rem;
  color: #fff;
  background: #386a54;
  font: inherit;
  font-weight: 700;
  white-space: nowrap;
}
.extra-list {
  display: grid;
  gap: 0.45rem;
  padding: 0;
  margin: 1rem 0 0;
  list-style: none;
}
.extra-list li {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 0;
  padding: 0.55rem 0.6rem;
  border-radius: 0.65rem;
  background: #f5f8f5;
}
.avatar-placeholder,
.photo-icon {
  display: grid;
  flex: 0 0 2rem;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  color: #386a54;
  background: #e5f1e9;
  font-size: 0.85rem;
  font-weight: 800;
}
.extra-value {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: #34443a;
  font-size: 0.85rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.photo-icon {
  border-radius: 0.5rem;
  font-size: 1.1rem;
}
.portal-link {
  color: #386a54;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 2px;
}
.remove-button {
  padding: 0.3rem 0.4rem;
  border: 0;
  color: #68756c;
  background: transparent;
  font: inherit;
  font-size: 0.75rem;
  cursor: pointer;
}
.remove-button:hover {
  color: #b42318;
  text-decoration: underline;
}
.social-error {
  grid-column: 1 / -1;
  margin: 0;
  color: #b42318;
  font-size: 0.85rem;
}
.local-note {
  grid-column: 1 / -1;
  margin: -0.5rem 0 0;
  color: #77847a;
  font-size: 0.78rem;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
.status-card p {
  margin: 0;
  color: #58665d;
}
.error-card {
  border-color: #fecaca;
  background: #fffafa;
}
.error-card h1 {
  font-size: 1.5rem;
}
.spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid #e5e7eb;
  border-top-color: #386a54;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
@media (max-width: 700px) {
  .extras-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 900px) {
  .itinerary-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .map-panel {
    position: static;
  }
}
@media (max-width: 560px) {
  .hero-title-row {
    flex-direction: column;
  }
  .trip-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.45rem;
  }
  .trip-stats > .trip-stat {
    padding: 0.7rem 0.55rem;
    min-height: 5.5rem;
  }
  .trip-stats strong {
    font-size: 1rem;
  }
  .trip-stat-label {
    font-size: 0.72rem;
  }
  .leg-row {
    padding-left: 2.7rem;
  }
  .route-line {
    left: 1.05rem;
  }
  .leg-card {
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.65rem;
  }
  .leg-distance {
    margin-left: 2.5rem;
  }
  .add-row {
    flex-direction: column;
  }
  .add-button {
    min-height: 2.6rem;
  }
  .place-form-fields {
    grid-template-columns: minmax(0, 1fr);
  }
  .place-card {
    gap: 0.5rem;
    padding: 0.7rem;
  }
  .place-actions {
    flex-wrap: wrap;
    justify-content: flex-end;
  }
}
@media (prefers-color-scheme: dark) {
  .details-page {
    color: #f3f4f6;
  }
  .back-link {
    color: #6ee7b7;
  }
  .trip-hero {
    border-color: #37483d;
    background: linear-gradient(135deg, #1a2e24, #2a2618);
  }
  .trip-hero,
  .itinerary,
  .map-panel,
  .status-card,
  .place-card,
  .extra-card {
    background-color: #1f2937;
    border-color: #374151;
  }
  .trip-dates,
  .trip-description,
  .empty-itinerary p,
  .status-card p {
    color: #a6b2a9;
  }
  .edit-link,
  .button-secondary {
    border-color: #6ee7b7;
    color: #6ee7b7;
  }
  .trip-stats > .trip-stat {
    border-color: #37483d;
    background: rgb(17 24 39 / 45%);
  }
  .trip-stats strong,
  .leg-distance {
    color: #6ee7b7;
  }
  .trip-stat-label,
  .trip-stat-detail,
  .place-label,
  .country-code,
  .leg-copy span,
  .itinerary-hint,
  .place-form-fields label {
    color: #9ca3af;
  }
  .country-code-list span {
    color: #6ee7b7;
    background: #263329;
  }
  .guest-avatar {
    border-color: #1f2937;
    color: #111827;
    background: #6ee7b7;
  }
  .place-card,
  .leg-card,
  .missing-leg,
  .extra-list li,
  .place-form {
    background: #263329;
    border-color: #37483d;
  }
  .map-panel {
    background: #1f2937;
  }
  .map-panel-note {
    color: #9ca3af;
  }
  .place-form input,
  .leg-card select,
  .missing-leg select {
    color: #f3f4f6;
    background: #111827;
    border-color: #4b5563;
  }
  .place-search-results {
    border-color: #4b5563;
    background: #111827;
  }
  .place-search-results button {
    color: #f3f4f6;
  }
  .place-search-results button:hover,
  .place-search-results button:focus-visible {
    background: #263329;
  }
  .place-search-results button span {
    color: #9ca3af;
  }
  .count-badge,
  .empty-icon,
  .leg-icon,
  .avatar-placeholder,
  .photo-icon {
    color: #6ee7b7;
    background: #243a2e;
  }
  .route-line {
    background: #496454;
  }
  .empty-itinerary {
    border-color: #496454;
  }
  .error-card {
    background: #2b2020;
  }
  .extra-intro,
  .extra-empty,
  .extra-value,
  .remove-button,
  .local-note {
    color: #a6b2a9;
  }
  .portal-link {
    color: #6ee7b7;
  }
  .add-row input {
    color: #f3f4f6;
    background: #111827;
    border-color: #4b5563;
  }
  .social-error,
  .route-action-error {
    color: #fca5a5;
  }
  .icon-button {
    color: #6ee7b7;
    background: #1f2937;
    border-color: #496454;
  }
}
</style>
