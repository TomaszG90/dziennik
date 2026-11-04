<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { deleteTrip, getTrips, type Trip } from '@/api/trips'
import ConfirmModal from '@/components/common/ConfirmModal.vue'

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
  (e: 'edit', id: number): void
  (e: 'delete', id: number): void
}>()

const router = useRouter()

const trips = ref<Trip[]>(props.initialTrips ?? [])
const isLoading = ref<boolean>(props.autoFetch && props.initialTrips === undefined)
const error = ref<string | null>(null)

// Zarządzanie usuwaniem z potwierdzeniem
const tripToDelete = ref<Trip | null>(null)
const isDeleting = ref<boolean>(false)
const deleteError = ref<string | null>(null)

function getTodayString(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function isPastTrip(trip: Trip): boolean {
  const today = getTodayString()
  if (trip.end_date) {
    return trip.end_date < today
  }
  if (trip.start_date) {
    return trip.start_date < today
  }
  return false
}

function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return ''
  const parts = dateStr.slice(0, 10).split('-')
  if (parts.length === 3) {
    const [year, month, day] = parts
    return `${day}.${month}.${year}`
  }
  return dateStr
}

function formatDateRange(
  startDate: string | null | undefined,
  endDate: string | null | undefined,
): string {
  if (!startDate && !endDate) return 'Termin do ustalenia'
  if (startDate && endDate) {
    if (startDate === endDate) return formatDate(startDate)
    return `${formatDate(startDate)} – ${formatDate(endDate)}`
  }
  if (startDate) return `Od ${formatDate(startDate)}`
  return `Do ${formatDate(endDate)}`
}

const plannedTrips = computed(() => trips.value.filter((trip) => !isPastTrip(trip)))
const pastTrips = computed(() => trips.value.filter((trip) => isPastTrip(trip)))

async function loadTrips() {
  isLoading.value = true
  error.value = null

  try {
    const data = await getTrips()
    trips.value = data
  } catch (err: unknown) {
    if (err instanceof Error) {
      error.value = err.message
    } else {
      error.value = 'Nie udało się pobrać listy podróży.'
    }
  } finally {
    isLoading.value = false
  }
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
  emit('edit', id)
  if (router) {
    router.push(`/trips/${id}/edit`).catch(() => {})
  }
}

function promptDeleteTrip(trip: Trip) {
  tripToDelete.value = trip
  deleteError.value = null
}

function cancelDeleteTrip() {
  tripToDelete.value = null
  deleteError.value = null
}

async function confirmDeleteTrip() {
  if (!tripToDelete.value) return

  isDeleting.value = true
  deleteError.value = null
  const id = tripToDelete.value.id

  try {
    await deleteTrip(id)
    trips.value = trips.value.filter((t) => t.id !== id)
    emit('delete', id)
    tripToDelete.value = null
  } catch (err: unknown) {
    if (err instanceof Error) {
      deleteError.value = err.message
    } else {
      deleteError.value = 'Nie udało się usunąć podróży.'
    }
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  if (props.autoFetch && props.initialTrips === undefined) {
    void loadTrips()
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

    <!-- Błąd usuwania jeśli wystąpił -->
    <div v-if="deleteError" class="feedback-card error-card" role="alert">
      <p class="feedback-text">{{ deleteError }}</p>
    </div>

    <!-- Stan błędu ładowania -->
    <div v-if="error" class="feedback-card error-card" role="alert">
      <div class="feedback-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
      </div>
      <div class="feedback-content">
        <p class="feedback-title">Błąd ładowania wycieczek</p>
        <p class="feedback-text">{{ error }}</p>
      </div>
      <button type="button" class="retry-button" @click="loadTrips">
        Spróbuj ponownie
      </button>
    </div>

    <!-- Stan ładowania -->
    <div
      v-else-if="isLoading"
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
        <header class="section-header">
          <div>
            <h3 id="heading-planned-trips" class="section-title">Zaplanowane wycieczki</h3>
            <p class="section-subtitle">(własne i zaproszenia na wycieczki od znajomych)</p>
          </div>
          <span v-if="plannedTrips.length > 0" class="badge-count">
            {{ plannedTrips.length }}
          </span>
        </header>

        <!-- Pusty stan dla zaplanowanych: Pora zaplanować nową wycieczkę -->
        <div
          v-if="plannedTrips.length === 0"
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
          <button
            type="button"
            class="empty-action-button"
            @click="handleCreateTrip"
          >
            Zaplanuj podróż
          </button>
        </div>

        <!-- Lista zaplanowanych podróży -->
        <ul v-else class="trips-list" role="list">
          <li
            v-for="trip in plannedTrips"
            :key="trip.id"
            class="trip-item"
            data-testid="trip-card"
          >
            <div class="trip-item-main">
              <div class="trip-title-row">
                <h4 class="trip-name">{{ trip.name }}</h4>
                <span
                  class="badge-origin"
                  :class="{
                    'badge-invitation': trip.is_invitation,
                    'badge-own': !trip.is_invitation,
                  }"
                >
                  {{
                    trip.is_invitation
                      ? `Zaproszenie: ${trip.inviter?.name ?? 'od znajomego'}`
                      : 'Własna'
                  }}
                </span>
              </div>

              <div class="trip-date-row">
                <svg
                  class="meta-icon"
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
              </div>

              <p v-if="trip.description" class="trip-description">
                {{ trip.description }}
              </p>
            </div>

            <div class="trip-item-actions">
              <button
                type="button"
                class="details-button"
                :aria-label="`Szczegóły podróży ${trip.name}`"
                data-testid="details-trip-button"
                @click="handleViewTrip(trip.id)"
              >
                Szczegóły
              </button>

              <button
                type="button"
                class="action-button edit-button"
                :aria-label="`Edytuj podróż ${trip.name}`"
                data-testid="edit-trip-button"
                @click="handleEditTrip(trip.id)"
              >
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
                <span>Edytuj</span>
              </button>

              <button
                type="button"
                class="action-button delete-button"
                :aria-label="`Usuń podróż ${trip.name}`"
                data-testid="delete-trip-button"
                @click="promptDeleteTrip(trip)"
              >
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                </svg>
                <span>Usuń</span>
              </button>
            </div>
          </li>
        </ul>
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
          <span v-if="pastTrips.length > 0" class="badge-count">
            {{ pastTrips.length }}
          </span>
        </header>

        <!-- Pusty stan dla odbytych wycieczek -->
        <div
          v-if="pastTrips.length === 0"
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
          <p class="empty-text">
            Twoje zakończone podróże pojawią się w tej sekcji.
          </p>
        </div>

        <!-- Lista odbytych podróży -->
        <ul v-else class="trips-list" role="list">
          <li
            v-for="trip in pastTrips"
            :key="trip.id"
            class="trip-item trip-item--past"
            data-testid="past-trip-card"
          >
            <div class="trip-item-main">
              <div class="trip-title-row">
                <h4 class="trip-name">{{ trip.name }}</h4>
                <span
                  class="badge-origin"
                  :class="{
                    'badge-invitation': trip.is_invitation,
                    'badge-own': !trip.is_invitation,
                  }"
                >
                  {{
                    trip.is_invitation
                      ? `Zaproszenie: ${trip.inviter?.name ?? 'od znajomego'}`
                      : 'Własna'
                  }}
                </span>
              </div>

              <div class="trip-date-row">
                <svg
                  class="meta-icon"
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
              </div>

              <p v-if="trip.description" class="trip-description">
                {{ trip.description }}
              </p>
            </div>

            <div class="trip-item-actions">
              <button
                type="button"
                class="details-button"
                :aria-label="`Szczegóły podróży ${trip.name}`"
                data-testid="details-trip-button"
                @click="handleViewTrip(trip.id)"
              >
                Szczegóły
              </button>

              <button
                type="button"
                class="action-button edit-button"
                :aria-label="`Edytuj podróż ${trip.name}`"
                data-testid="edit-trip-button"
                @click="handleEditTrip(trip.id)"
              >
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
                <span>Edytuj</span>
              </button>

              <button
                type="button"
                class="action-button delete-button"
                :aria-label="`Usuń podróż ${trip.name}`"
                data-testid="delete-trip-button"
                @click="promptDeleteTrip(trip)"
              >
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                </svg>
                <span>Usuń</span>
              </button>
            </div>
          </li>
        </ul>
      </section>
    </div>

    <!-- Modal potwierdzenia usunięcia podróży z listy -->
    <ConfirmModal
      :is-open="tripToDelete !== null"
      title="Usuwanie podróży"
      :message="`Czy na pewno chcesz usunąć podróż „${tripToDelete?.name || ''}”? Ta operacja jest nieodwracalna.`"
      confirm-label="Usuń podróż"
      cancel-label="Anuluj"
      :is-loading="isDeleting"
      variant="danger"
      @confirm="confirmDeleteTrip"
      @cancel="cancelDeleteTrip"
      @close="cancelDeleteTrip"
    />
  </div>
</template>

<style scoped>
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

.trip-item-main {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  flex: 1;
  min-width: 0;
}

.trip-title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
}

.trip-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #111827;
  word-break: break-word;
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

.trip-date-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  color: #4b5563;
  font-size: 0.875rem;
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
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
  flex-wrap: wrap;
}

.details-button,
.action-button {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.45rem 0.8rem;
  border-radius: 0.5rem;
  font: inherit;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.details-button {
  border: 1px solid rgba(56, 106, 84, 0.35);
  color: #294d3d;
  background: transparent;
}

.details-button:hover {
  background: #386a54;
  color: #ffffff;
  border-color: #386a54;
}

.edit-button {
  border: 1px solid #cbd5e1;
  color: #334155;
  background: #ffffff;
}

.edit-button:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
  color: #0f172a;
}

.delete-button {
  border: 1px solid #fecaca;
  color: #dc2626;
  background: #fff5f5;
}

.delete-button:hover {
  background: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
}

.details-button:focus-visible,
.action-button:focus-visible {
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
    justify-content: flex-start;
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

  .details-button:hover {
    background: #6ee7b7;
    color: #111827;
  }

  .edit-button {
    background: #374151;
    border-color: #4b5563;
    color: #e2e8f0;
  }

  .edit-button:hover {
    background: #4b5563;
    color: #ffffff;
  }

  .delete-button {
    background: rgba(220, 38, 38, 0.15);
    border-color: rgba(220, 38, 38, 0.4);
    color: #f87171;
  }

  .delete-button:hover {
    background: #dc2626;
    color: #ffffff;
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
