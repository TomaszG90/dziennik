<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getTripsPage, type Trip } from '@/api/trips'

type TripStatus = 'planned' | 'past'

const props = withDefaults(
  defineProps<{
    initialTrips?: Trip[]
    autoFetch?: boolean
  }>(),
  {
    initialTrips: undefined,
    autoFetch: true,
  },
)

const emit = defineEmits<{
  (e: 'create'): void
  (e: 'select', id: number): void
}>()

const router = useRouter()

const trips = ref<Trip[]>(props.initialTrips ?? [])
const isLoading = ref<boolean>(props.autoFetch && props.initialTrips === undefined)
const tripGroups = reactive({
  planned: { currentPage: 1, lastPage: 1, total: 0, loading: false, error: null as string | null },
  past: { currentPage: 1, lastPage: 1, total: 0, loading: false, error: null as string | null },
})
if (props.initialTrips) {
  tripGroups.planned.total = props.initialTrips.filter((trip) => !isPastTrip(trip)).length
  tripGroups.past.total = props.initialTrips.filter(isPastTrip).length
}
const requestIds: Record<TripStatus, number> = { planned: 0, past: 0 }
const visibleMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
const selectedCalendarDate = ref<string | null>(null)
const selectedTripId = ref<number | null>(null)

function getTodayString(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function dateOnly(date: string | null | undefined): string | null {
  return date?.slice(0, 10) ?? null
}

function isPastTrip(trip: Trip): boolean {
  const today = getTodayString()
  const endDate = dateOnly(trip.end_date)
  const startDate = dateOnly(trip.start_date)
  if (endDate) {
    return endDate < today
  }
  if (startDate) {
    return startDate < today
  }
  return false
}

function formatDate(dateStr: string | null | undefined): string {
  const normalizedDate = dateOnly(dateStr)
  if (!normalizedDate) return ''
  const parts = normalizedDate.split('-')
  if (parts.length === 3) {
    const [year, month, day] = parts
    return `${day}.${month}.${year}`
  }
  return normalizedDate
}

function formatDateRange(
  startDate: string | null | undefined,
  endDate: string | null | undefined,
): string {
  if (!startDate && !endDate) return 'Termin do ustalenia'
  const normalizedStart = dateOnly(startDate)
  const normalizedEnd = dateOnly(endDate)
  if (normalizedStart && normalizedEnd) {
    if (normalizedStart === normalizedEnd) return formatDate(normalizedStart)
    return `${formatDate(normalizedStart)} – ${formatDate(normalizedEnd)}`
  }
  if (normalizedStart) return `Od ${formatDate(normalizedStart)}`
  return `Do ${formatDate(normalizedEnd)}`
}

function tripSortDate(trip: Trip): string {
  return dateOnly(trip.start_date) ?? dateOnly(trip.end_date) ?? ''
}

const plannedTrips = computed(() =>
  trips.value
    .filter((trip) => !isPastTrip(trip))
    .sort((a, b) => tripSortDate(a).localeCompare(tripSortDate(b))),
)
const pastTrips = computed(() =>
  trips.value
    .filter((trip) => isPastTrip(trip))
    .sort((a, b) => tripSortDate(b).localeCompare(tripSortDate(a))),
)
const monthLabel = computed(() =>
  new Intl.DateTimeFormat('pl-PL', { month: 'long', year: 'numeric' }).format(visibleMonth.value),
)
const calendarDays = computed<Array<string | null>>(() => {
  const year = visibleMonth.value.getFullYear()
  const month = visibleMonth.value.getMonth()
  const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const cells: Array<string | null> = Array(firstWeekday).fill(null)

  for (let day = 1; day <= daysInMonth; day += 1) {
    cells.push(`${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`)
  }

  while (cells.length % 7 !== 0) cells.push(null)
  return cells
})

function tripDateRange(trip: Trip): [string, string] | null {
  const start = dateOnly(trip.start_date) ?? dateOnly(trip.end_date)
  const end = dateOnly(trip.end_date) ?? dateOnly(trip.start_date)
  if (!start || !end) return null
  return start <= end ? [start, end] : [end, start]
}

function tripsOnDate(date: string): Trip[] {
  return trips.value.filter((trip) => {
    const range = tripDateRange(trip)
    return range !== null && date >= range[0] && date <= range[1]
  })
}

function tripColorClass(trip: Trip): string {
  const index = trips.value.findIndex((listedTrip) => listedTrip.id === trip.id)
  return `calendar-marker--color-${Math.max(index, 0) % 5}`
}

function calendarDateLabel(date: string): string {
  const parsedDate = new Date(`${date}T00:00:00`)
  const tripsForDate = tripsOnDate(date)
  const dateLabel = new Intl.DateTimeFormat('pl-PL', { dateStyle: 'full' }).format(parsedDate)
  if (tripsForDate.length === 0) return dateLabel
  return `${dateLabel}: ${tripsForDate.map((trip) => trip.name).join(', ')}`
}

function selectCalendarDate(date: string) {
  selectedCalendarDate.value = date
  selectedTripId.value = null
}

function selectTripDate(trip: Trip) {
  selectedTripId.value = trip.id
  const date = tripDateRange(trip)?.[0]
  selectedCalendarDate.value = date ?? null
  if (date) {
    const year = Number(date.slice(0, 4))
    const month = Number(date.slice(5, 7))
    visibleMonth.value = new Date(year, month - 1, 1)
  }
}

function changeMonth(offset: number) {
  visibleMonth.value = new Date(
    visibleMonth.value.getFullYear(),
    visibleMonth.value.getMonth() + offset,
    1,
  )
  selectedCalendarDate.value = null
  selectedTripId.value = null
}

function isTripHighlighted(trip: Trip): boolean {
  if (selectedTripId.value !== null) return selectedTripId.value === trip.id
  if (!selectedCalendarDate.value) return false
  const range = tripDateRange(trip)
  return (
    range !== null &&
    selectedCalendarDate.value >= range[0] &&
    selectedCalendarDate.value <= range[1]
  )
}

async function loadTrips(status: TripStatus, page = 1, append = false) {
  const group = tripGroups[status]
  if (append && group.loading) return
  const id = ++requestIds[status]
  group.loading = true
  group.error = null

  try {
    const result = await getTripsPage({ page, status })
    if (id !== requestIds[status]) return
    const otherStatusTrips = trips.value.filter((trip) =>
      status === 'past' ? !isPastTrip(trip) : isPastTrip(trip),
    )
    const statusTrips = append
      ? [
          ...trips.value.filter((trip) => (status === 'past') === isPastTrip(trip)),
          ...result.data.filter((trip) => !trips.value.some(({ id }) => id === trip.id)),
        ]
      : result.data
    trips.value = [...otherStatusTrips, ...statusTrips]
    group.currentPage = result.meta.current_page
    group.lastPage = result.meta.last_page
    group.total = result.meta.total
  } catch (err: unknown) {
    if (id !== requestIds[status]) return
    if (err instanceof Error) {
      group.error = err.message
    } else {
      group.error = 'Nie udało się pobrać listy podróży.'
    }
  } finally {
    if (id === requestIds[status]) group.loading = false
  }
}

function loadMoreTrips(status: TripStatus) {
  const group = tripGroups[status]
  if (group.loading || group.currentPage >= group.lastPage) return
  void loadTrips(status, group.currentPage + 1, true)
}

async function loadTripGroups() {
  isLoading.value = true
  await Promise.all([loadTrips('planned'), loadTrips('past')])
  isLoading.value = false
}

function handleCreateTrip() {
  emit('create')
  if (router) {
    router.push('/trips/new').catch(() => {})
  }
}

function handleViewTrip(id: number) {
  emit('select', id)
  if (router) {
    router.push(`/trips/${id}`).catch(() => {})
  }
}

function handleEditTrip(id: number) {
  router.push(`/trips/${id}/edit`).catch(() => {})
}

onMounted(() => {
  if (props.autoFetch && props.initialTrips === undefined) {
    void loadTripGroups()
  }
})
</script>

<template>
  <div class="user-trips-container">
    <!-- Sekcja 1: Przyciski (Stwórz nową podróż) -->
    <section class="trips-section section-actions" aria-label="Akcje podróży">
      <div class="actions-bar">
        <button
          type="button"
          class="create-trip-button"
          data-testid="create-trip-button"
          @click="handleCreateTrip"
        >
          <svg
            class="btn-icon"
            aria-hidden="true"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
          >
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
          </svg>
          <span>Stwórz nową podróż</span>
        </button>
      </div>
    </section>

    <!-- Stan ładowania -->
    <div
      v-if="isLoading && trips.length === 0"
      class="feedback-card loading-card"
      role="status"
      aria-live="polite"
      data-testid="trips-loading"
    >
      <div class="spinner" aria-hidden="true"></div>
      <p class="loading-text">Ładowanie podróży...</p>
    </div>

    <!-- Załadowana treść -->
    <div v-else class="trips-content">
      <!-- Sekcja 2: Zaplanowane wycieczki (własne i zaproszenia) -->
      <section
        class="trips-section planned-section"
        aria-labelledby="heading-planned-trips"
        data-testid="planned-section"
      >
        <section class="trip-calendar" aria-label="Kalendarz podróży">
          <div class="calendar-header">
            <button
              type="button"
              class="calendar-nav-button"
              aria-label="Poprzedni miesiąc"
              @click="changeMonth(-1)"
            >
              <span aria-hidden="true">&lsaquo;</span>
            </button>
            <h4 class="calendar-month" aria-live="polite">{{ monthLabel }}</h4>
            <button
              type="button"
              class="calendar-nav-button"
              aria-label="Następny miesiąc"
              @click="changeMonth(1)"
            >
              <span aria-hidden="true">&rsaquo;</span>
            </button>
          </div>
          <div class="calendar-grid" role="grid" :aria-label="monthLabel">
            <span
              v-for="weekday in ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So', 'Nd']"
              :key="weekday"
              class="calendar-weekday"
              role="columnheader"
            >
              {{ weekday }}
            </span>
            <template v-for="(date, index) in calendarDays" :key="date ?? `empty-${index}`">
              <span
                v-if="!date"
                class="calendar-day calendar-day--empty"
                role="gridcell"
                aria-hidden="true"
              ></span>
              <button
                v-else
                type="button"
                class="calendar-day"
                :data-date="date"
                :class="{
                  'calendar-day--planned': tripsOnDate(date).some((trip) => !isPastTrip(trip)),
                  'calendar-day--past': tripsOnDate(date).some(isPastTrip),
                  'calendar-day--selected': selectedCalendarDate === date,
                }"
                role="gridcell"
                :aria-label="calendarDateLabel(date)"
                :aria-pressed="selectedCalendarDate === date"
                @click="selectCalendarDate(date)"
              >
                <span>{{ Number(date.slice(-2)) }}</span>
                <span v-if="tripsOnDate(date).length" class="calendar-markers" aria-hidden="true">
                  <i
                    v-for="trip in tripsOnDate(date)"
                    :key="trip.id"
                    class="calendar-marker"
                    :class="tripColorClass(trip)"
                  ></i>
                </span>
              </button>
            </template>
          </div>
          <p class="calendar-hint">
            Wybierz dzień podróży, aby podświetlić ją na liście. Kliknij termin na liście, aby
            przejść do jego miesiąca.
          </p>
        </section>
        <header class="section-header">
          <div>
            <h3 id="heading-planned-trips" class="section-title">Zaplanowane wycieczki</h3>
            <p class="section-subtitle">(własne i zaproszenia na wycieczki od znajomych)</p>
          </div>
          <span v-if="tripGroups.planned.total > 0" class="badge-count">
            {{ tripGroups.planned.total }}
          </span>
        </header>

        <!-- Pusty stan dla zaplanowanych: Pora zaplanować nową wycieczkę -->
        <div
          v-if="
            plannedTrips.length === 0 && tripGroups.planned.total === 0 && !tripGroups.planned.error
          "
          class="empty-card"
          data-testid="planned-empty-state"
        >
          <div class="empty-icon-wrap" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
              <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" />
              <path d="M6 6h10" />
              <path d="M6 10h10" />
            </svg>
          </div>
          <h4 class="empty-title">Pora zaplanować nową wycieczkę</h4>
          <p class="empty-text">
            Nie masz jeszcze żadnych zaplanowanych wycieczek ani zaproszeń od znajomych.
          </p>
          <button type="button" class="empty-action-button" @click="handleCreateTrip">
            Zaplanuj podróż
          </button>
        </div>

        <!-- Lista zaplanowanych podróży -->
        <ul v-else-if="plannedTrips.length > 0" class="trips-list" role="list">
          <li
            v-for="trip in plannedTrips"
            :key="trip.id"
            class="trip-item"
            :class="{ 'trip-item--calendar-highlighted': isTripHighlighted(trip) }"
            data-testid="trip-card"
          >
            <div class="trip-item-main">
              <div class="trip-title-row">
                <h4 class="trip-name">{{ trip.name }}</h4>
                <span
                  class="badge-origin"
                  :class="{
                    'badge-invitation': trip.is_invitation,
                    'badge-guest': trip.is_guest,
                    'badge-own': !trip.is_invitation && !trip.is_guest,
                  }"
                >
                  {{
                    trip.is_invitation
                      ? `Zaproszenie: ${trip.inviter?.name ?? 'od znajomego'}`
                      : trip.is_guest
                        ? 'Gość'
                        : 'Własna'
                  }}
                </span>
              </div>

              <button
                type="button"
                class="trip-date-row trip-date-button"
                :aria-label="`Pokaż termin podróży ${trip.name} w kalendarzu`"
                @click="selectTripDate(trip)"
              >
                <svg
                  class="meta-icon"
                  width="16"
                  height="16"
                  aria-hidden="true"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <span class="trip-date">{{ formatDateRange(trip.start_date, trip.end_date) }}</span>
              </button>

              <p v-if="trip.description" class="trip-description">
                {{ trip.description }}
              </p>
            </div>

            <div class="trip-item-actions">
              <button
                v-if="!trip.is_invitation && !trip.is_guest"
                type="button"
                class="edit-button"
                :aria-label="`Edytuj podróż ${trip.name}`"
                @click="handleEditTrip(trip.id)"
              >
                Edytuj
              </button>
              <button
                type="button"
                class="details-button"
                :aria-label="`Szczegóły podróży ${trip.name}`"
                @click="handleViewTrip(trip.id)"
              >
                Szczegóły
                <svg
                  viewBox="0 0 24 24"
                  width="16"
                  height="16"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  aria-hidden="true"
                >
                  <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </li>
        </ul>
        <div v-if="tripGroups.planned.error" class="feedback-card error-card" role="alert">
          <p class="feedback-text">{{ tripGroups.planned.error }}</p>
          <button
            type="button"
            class="retry-button"
            @click="
              loadTrips(
                'planned',
                tripGroups.planned.currentPage > 1 ? tripGroups.planned.currentPage + 1 : 1,
                tripGroups.planned.currentPage > 1,
              )
            "
          >
            Spróbuj ponownie
          </button>
        </div>
        <button
          v-if="tripGroups.planned.currentPage < tripGroups.planned.lastPage"
          type="button"
          class="load-more-button"
          :disabled="tripGroups.planned.loading"
          @click="loadMoreTrips('planned')"
        >
          {{
            tripGroups.planned.loading ? 'Ładowanie…' : 'Załaduj kolejne 5 zaplanowanych wycieczek'
          }}
        </button>
        <p v-if="tripGroups.planned.total > 0" class="trip-group-count">
          Wyświetlono {{ plannedTrips.length }} z {{ tripGroups.planned.total }} zaplanowanych
          wycieczek
        </p>
      </section>

      <!-- Sekcja 3: Odbyte wycieczki -->
      <section
        class="trips-section past-section"
        aria-labelledby="heading-past-trips"
        data-testid="past-section"
      >
        <header class="section-header">
          <div>
            <h3 id="heading-past-trips" class="section-title">Odbyte wycieczki</h3>
          </div>
          <span v-if="tripGroups.past.total > 0" class="badge-count">
            {{ tripGroups.past.total }}
          </span>
        </header>

        <!-- Pusty stan dla odbytych wycieczek -->
        <div
          v-if="pastTrips.length === 0 && tripGroups.past.total === 0 && !tripGroups.past.error"
          class="empty-card"
          data-testid="past-empty-state"
        >
          <div class="empty-icon-wrap" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <h4 class="empty-title">Brak odbytych wycieczek</h4>
          <p class="empty-text">Twoje zakończone podróże pojawią się w tej sekcji.</p>
        </div>

        <!-- Lista odbytych podróży -->
        <ul v-else-if="pastTrips.length > 0" class="trips-list" role="list">
          <li
            v-for="trip in pastTrips"
            :key="trip.id"
            class="trip-item trip-item--past"
            :class="{ 'trip-item--calendar-highlighted': isTripHighlighted(trip) }"
            data-testid="past-trip-card"
          >
            <div class="trip-item-main">
              <div class="trip-title-row">
                <h4 class="trip-name">{{ trip.name }}</h4>
                <span
                  class="badge-origin"
                  :class="{
                    'badge-invitation': trip.is_invitation,
                    'badge-guest': trip.is_guest,
                    'badge-own': !trip.is_invitation && !trip.is_guest,
                  }"
                >
                  {{
                    trip.is_invitation
                      ? `Zaproszenie: ${trip.inviter?.name ?? 'od znajomego'}`
                      : trip.is_guest
                        ? 'Gość'
                        : 'Własna'
                  }}
                </span>
              </div>

              <button
                type="button"
                class="trip-date-row trip-date-button"
                :aria-label="`Pokaż termin podróży ${trip.name} w kalendarzu`"
                @click="selectTripDate(trip)"
              >
                <svg
                  class="meta-icon"
                  width="16"
                  height="16"
                  aria-hidden="true"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <span class="trip-date">{{ formatDateRange(trip.start_date, trip.end_date) }}</span>
              </button>

              <p v-if="trip.description" class="trip-description">
                {{ trip.description }}
              </p>
            </div>

            <div class="trip-item-actions">
              <button
                v-if="!trip.is_invitation && !trip.is_guest"
                type="button"
                class="edit-button"
                :aria-label="`Edytuj podróż ${trip.name}`"
                @click="handleEditTrip(trip.id)"
              >
                Edytuj
              </button>
              <button
                type="button"
                class="details-button"
                :aria-label="`Szczegóły podróży ${trip.name}`"
                @click="handleViewTrip(trip.id)"
              >
                Szczegóły
                <svg
                  viewBox="0 0 24 24"
                  width="16"
                  height="16"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  aria-hidden="true"
                >
                  <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          </li>
        </ul>
        <div v-if="tripGroups.past.error" class="feedback-card error-card" role="alert">
          <p class="feedback-text">{{ tripGroups.past.error }}</p>
          <button
            type="button"
            class="retry-button"
            @click="
              loadTrips(
                'past',
                tripGroups.past.currentPage > 1 ? tripGroups.past.currentPage + 1 : 1,
                tripGroups.past.currentPage > 1,
              )
            "
          >
            Spróbuj ponownie
          </button>
        </div>
        <button
          v-if="tripGroups.past.currentPage < tripGroups.past.lastPage"
          type="button"
          class="load-more-button"
          :disabled="tripGroups.past.loading"
          @click="loadMoreTrips('past')"
        >
          {{ tripGroups.past.loading ? 'Ładowanie…' : 'Załaduj kolejne 5 odbytych wycieczek' }}
        </button>
        <p v-if="tripGroups.past.total > 0" class="trip-group-count">
          Wyświetlono {{ pastTrips.length }} z {{ tripGroups.past.total }} odbytych wycieczek
        </p>
      </section>
    </div>
  </div>
</template>

<style scoped>
.load-more-button {
  display: block;
  min-height: 2.5rem;
  margin: 1rem auto 0;
  padding: 0.45rem 0.8rem;
  border: 1px solid #cbd8d0;
  border-radius: 0.55rem;
  background: #fff;
  color: #256348;
  font: inherit;
  cursor: pointer;
}

.load-more-button:disabled {
  color: #9aa9a0;
  cursor: default;
}

.trip-group-count {
  margin: 0.75rem 0 0;
  color: #64746c;
}

.user-trips-container {
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
  width: 100%;
}

.trips-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* ── Sekcja 1: Akcje ── */
.section-actions {
  display: flex;
  justify-content: flex-start;
}

.actions-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.create-trip-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0.65rem 1.25rem;
  border: 0;
  border-radius: 0.625rem;
  color: #ffffff;
  background: #386a54;
  font: inherit;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(56, 106, 84, 0.25);
  transition:
    background-color 150ms ease,
    transform 150ms ease,
    box-shadow 150ms ease;
}

.create-trip-button:hover {
  background: #2b5241;
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(56, 106, 84, 0.35);
}

.create-trip-button:focus-visible {
  outline: 2px solid #386a54;
  outline-offset: 2px;
}

.btn-icon {
  width: 1.25rem;
  height: 1.25rem;
}

/* ── Feedback: Błąd i Ładowanie ── */
.feedback-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-radius: 0.875rem;
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

.error-card {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.feedback-icon svg {
  width: 2rem;
  height: 2rem;
  stroke: #dc2626;
}

.feedback-content {
  flex: 1;
}

.feedback-title {
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.feedback-text {
  font-size: 0.9rem;
  color: #7f1d1d;
}

.retry-button {
  padding: 0.5rem 1rem;
  border: 1px solid #dc2626;
  border-radius: 0.5rem;
  color: #dc2626;
  background: transparent;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 150ms ease;
}

.retry-button:hover {
  background: #dc2626;
  color: #ffffff;
}

.loading-card {
  justify-content: center;
  padding: 3rem 1.5rem;
}

.loading-text {
  color: #4b5563;
  font-weight: 500;
}

.spinner {
  width: 1.75rem;
  height: 1.75rem;
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

/* ── Treść sekcji 2 i 3 ── */
.trips-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.section-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.75rem;
  border-bottom: 2px solid rgba(56, 106, 84, 0.15);
  padding-bottom: 0.5rem;
}

.section-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1f2937;
  display: inline;
}

.section-subtitle {
  margin-top: 0.2rem;
  font-size: 0.85rem;
  color: #6b7280;
  font-weight: normal;
}

.badge-count {
  padding: 0.15rem 0.6rem;
  border-radius: 9999px;
  background: rgba(56, 106, 84, 0.15);
  color: #294d3d;
  font-size: 0.8rem;
  font-weight: 700;
}

.trip-calendar {
  align-self: center;
  width: min(100%, 32rem);
  padding: 1rem;
  border: 1px solid rgba(56, 106, 84, 0.18);
  border-radius: 0.875rem;
  background: rgba(255, 255, 255, 0.55);
}

.calendar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.75rem;
}

.calendar-month {
  margin: 0;
  color: #294d3d;
  font-size: 1.05rem;
  font-weight: 700;
  text-transform: capitalize;
}

.calendar-nav-button {
  display: grid;
  width: 2.25rem;
  height: 2.25rem;
  place-items: center;
  border: 1px solid rgba(56, 106, 84, 0.25);
  border-radius: 0.5rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-size: 1.5rem;
  cursor: pointer;
}

.calendar-nav-button:hover,
.calendar-nav-button:focus-visible {
  background: rgba(56, 106, 84, 0.12);
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 0.25rem;
}

.calendar-weekday {
  padding: 0.35rem 0;
  color: #6b7280;
  text-align: center;
  font-size: 0.75rem;
  font-weight: 700;
}

.calendar-day {
  display: flex;
  min-width: 0;
  min-height: 2.75rem;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.2rem;
  padding: 0.25rem;
  border: 1px solid transparent;
  border-radius: 0.5rem;
  color: #1f2937;
  background: transparent;
  font: inherit;
  font-size: 0.875rem;
}

button.calendar-day {
  cursor: pointer;
}

button.calendar-day:hover,
button.calendar-day:focus-visible {
  border-color: #386a54;
  outline: none;
}

.calendar-day--planned {
  background: rgba(56, 106, 84, 0.08);
}

.calendar-day--past {
  background: rgba(37, 99, 235, 0.1);
}

.calendar-day--selected {
  color: #fff;
  background: #386a54;
  font-weight: 700;
}

.calendar-day--empty {
  pointer-events: none;
}

.calendar-markers {
  display: flex;
  min-height: 0.35rem;
  align-items: center;
  justify-content: center;
  gap: 0.15rem;
}

.calendar-marker {
  width: 0.35rem;
  height: 0.35rem;
  flex: 0 0 auto;
  border-radius: 50%;
  background: #386a54;
}

.calendar-marker--color-1 {
  background: #2563eb;
}
.calendar-marker--color-2 {
  background: #c2410c;
}
.calendar-marker--color-3 {
  background: #9333ea;
}
.calendar-marker--color-4 {
  background: #be123c;
}

.calendar-day--selected .calendar-marker {
  background: #fff;
}

.calendar-hint {
  margin: 0.75rem 0 0;
  color: #6b7280;
  font-size: 0.8rem;
}

/* ── Puste stany ── */
.empty-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 2.25rem 1.5rem;
  border-radius: 0.875rem;
  border: 2px dashed rgba(56, 106, 84, 0.25);
  background: rgba(255, 255, 255, 0.5);
  gap: 0.5rem;
}

.empty-icon-wrap {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 50%;
  background: rgba(56, 106, 84, 0.1);
  color: #386a54;
  display: grid;
  place-items: center;
  margin-bottom: 0.5rem;
}

.empty-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1f2937;
}

.empty-text {
  font-size: 0.9rem;
  color: #4b5563;
  max-width: 26rem;
}

.empty-action-button {
  margin-top: 0.5rem;
  padding: 0.55rem 1.1rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: #386a54;
  background: transparent;
  font: inherit;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 150ms ease;
}

.empty-action-button:hover {
  background: #386a54;
  color: #ffffff;
}

/* ── Lista podróży (Karty) ── */
.trips-list {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
  list-style: none;
  padding: 0;
  margin: 0;
}

.trip-item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.125rem 1.25rem;
  border-radius: 0.875rem;
  background: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition:
    transform 150ms ease,
    box-shadow 150ms ease,
    border-color 150ms ease;
}

.trip-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.07);
  border-color: rgba(56, 106, 84, 0.3);
}

.trip-item--calendar-highlighted {
  border-color: #386a54;
  background: #edf6f0;
  box-shadow: 0 0 0 2px rgba(56, 106, 84, 0.18);
}

.trip-item-main {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  flex: 1 1 14rem;
  min-width: min(100%, 14rem);
}

.trip-title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
}

.trip-name {
  min-width: 5ch;
  font-size: 1.1rem;
  font-weight: 700;
  color: #111827;
  word-break: keep-all;
  overflow-wrap: normal;
}

.badge-origin {
  padding: 0.15rem 0.55rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.badge-own {
  background: #e8f5e9;
  color: #246845;
  border: 1px solid #c8e6c9;
}

.badge-invitation {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

.badge-guest {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
}

.trip-date-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  color: #4b5563;
  font-size: 0.875rem;
}

.trip-date-button {
  width: fit-content;
  max-width: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.trip-date-button:focus-visible {
  border-radius: 0.25rem;
  outline: 2px solid #386a54;
  outline-offset: 2px;
}

.meta-icon {
  flex-shrink: 0;
  color: #386a54;
}

.trip-description {
  font-size: 0.9rem;
  color: #4b5563;
  margin-top: 0.15rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.trip-item-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  flex: 0 1 auto;
  margin-left: auto;
}

.edit-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.4rem;
  padding: 0.45rem 0.85rem;
  border: 1px solid rgba(56, 106, 84, 0.35);
  border-radius: 0.5rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.edit-button:hover {
  background: rgba(56, 106, 84, 0.1);
}

.details-button {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 0.9rem;
  border: 1px solid rgba(56, 106, 84, 0.35);
  border-radius: 0.5rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.details-button:hover {
  background: #386a54;
  color: #ffffff;
  border-color: #386a54;
}

.details-button:focus-visible {
  outline: 2px solid #386a54;
  outline-offset: 2px;
}

/* ── Odbyte wycieczki (lekko stonowane) ── */
.trip-item--past {
  opacity: 0.92;
  border-left: 4px solid #9ca3af;
}

/* ── Responsywność ── */
@media (max-width: 640px) {
  .trip-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .trip-item-actions {
    width: 100%;
  }

  .details-button {
    width: 100%;
    justify-content: center;
  }

  .edit-button {
    width: 100%;
  }
}

/* ── Dark mode ── */
@media (prefers-color-scheme: dark) {
  .section-title {
    color: #f3f4f6;
  }

  .section-subtitle {
    color: #9ca3af;
  }

  .section-header {
    border-bottom-color: rgba(110, 231, 183, 0.2);
  }

  .badge-count {
    background: rgba(110, 231, 183, 0.15);
    color: #6ee7b7;
  }

  .trip-calendar {
    border-color: rgba(110, 231, 183, 0.2);
    background: rgba(0, 0, 0, 0.2);
  }

  .calendar-month,
  .calendar-nav-button {
    color: #6ee7b7;
  }

  .calendar-nav-button {
    border-color: rgba(110, 231, 183, 0.3);
  }

  .calendar-nav-button:hover,
  .calendar-nav-button:focus-visible {
    background: rgba(110, 231, 183, 0.12);
  }

  .calendar-weekday,
  .calendar-hint {
    color: #9ca3af;
  }

  .calendar-day {
    color: #f3f4f6;
  }

  .calendar-day--planned {
    background: rgba(110, 231, 183, 0.1);
  }

  .calendar-day--past {
    background: rgba(96, 165, 250, 0.16);
  }

  .calendar-day--selected {
    color: #111827;
    background: #6ee7b7;
  }

  .calendar-marker {
    background: #6ee7b7;
  }

  .trip-item--calendar-highlighted {
    border-color: #6ee7b7;
    background: #1a2e24;
    box-shadow: 0 0 0 2px rgba(110, 231, 183, 0.18);
  }

  .feedback-card {
    background: #1f2937;
    border-color: #374151;
  }

  .error-card {
    background: #3f1a1a;
    border-color: #7f1d1d;
    color: #fca5a5;
  }

  .feedback-text {
    color: #f87171;
  }

  .loading-text {
    color: #9ca3af;
  }

  .spinner {
    border-color: #374151;
    border-top-color: #6ee7b7;
  }

  .empty-card {
    background: rgba(0, 0, 0, 0.25);
    border-color: rgba(110, 231, 183, 0.25);
  }

  .empty-icon-wrap {
    background: rgba(110, 231, 183, 0.15);
    color: #6ee7b7;
  }

  .empty-title {
    color: #f3f4f6;
  }

  .empty-text {
    color: #9ca3af;
  }

  .empty-action-button {
    border-color: #6ee7b7;
    color: #6ee7b7;
  }

  .empty-action-button:hover {
    background: #6ee7b7;
    color: #111827;
  }

  .trip-item {
    background: #1f2937;
    border-color: #374151;
    box-shadow: none;
  }

  .trip-item:hover {
    border-color: #6ee7b7;
  }

  .trip-item.trip-item--calendar-highlighted {
    border-color: #6ee7b7;
    background: #1a2e24;
    box-shadow: 0 0 0 2px rgba(110, 231, 183, 0.18);
  }

  .trip-name {
    color: #f3f4f6;
  }

  .badge-own {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
    border-color: rgba(16, 185, 129, 0.3);
  }

  .badge-invitation {
    background: rgba(59, 130, 246, 0.15);
    color: #93c5fd;
    border-color: rgba(59, 130, 246, 0.3);
  }

  .badge-guest {
    background: rgba(245, 158, 11, 0.15);
    color: #fcd34d;
    border-color: rgba(245, 158, 11, 0.3);
  }

  .trip-date-row {
    color: #9ca3af;
  }

  .meta-icon {
    color: #6ee7b7;
  }

  .trip-description {
    color: #d1d5db;
  }

  .details-button {
    border-color: #6ee7b7;
    color: #6ee7b7;
  }

  .edit-button {
    border-color: #6ee7b7;
    color: #6ee7b7;
  }

  .details-button:hover {
    background: #6ee7b7;
    color: #111827;
  }

  .create-trip-button {
    background: #234e3b;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
  }

  .create-trip-button:hover {
    background: #2e664e;
  }
}
</style>
