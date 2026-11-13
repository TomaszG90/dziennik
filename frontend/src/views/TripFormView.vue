<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  createTrip,
  deleteTrip,
  getTrip,
  updateTrip,
  type Trip,
  type TripPayload,
} from '@/api/trips'
import {
  getFriends,
  inviteToTrip,
  type Friend,
  type TripInvitation,
} from '@/api/friends'
import { ApiError } from '@/api/types'
import ConfirmModal from '@/components/common/ConfirmModal.vue'

const props = defineProps<{
  tripId?: string | number
}>()

const route = useRoute()
const router = useRouter()

const targetTripId = computed<string | number | undefined>(() => {
  if (props.tripId !== undefined) return props.tripId
  const param = route.params.id
  if (typeof param === 'string' && param !== 'new') return param
  return undefined
})

const isEditMode = computed<boolean>(() => targetTripId.value !== undefined)

const formData = reactive<TripPayload>({
  name: '',
  start_date: '',
  end_date: '',
  description: '',
})

const errors = reactive<{
  name: string[]
  start_date: string[]
  end_date: string[]
  description: string[]
}>({
  name: [],
  start_date: [],
  end_date: [],
  description: [],
})

const formError = ref<string>('')
const isLoadingTrip = ref<boolean>(false)
const loadTripError = ref<string>('')
const isSubmitting = ref<boolean>(false)

// Potwierdzenie usuwania
const showDeleteConfirm = ref<boolean>(false)
const isDeleting = ref<boolean>(false)
const currentTrip = ref<Trip | null>(null)

// Zaproszenia znajomych do wycieczki
const showInviteDialog = ref<boolean>(false)
const friends = ref<Friend[]>([])
const isLoadingFriends = ref<boolean>(false)
const selectedFriends = ref<Set<number>>(new Set())
const isInviting = ref<boolean>(false)
const inviteError = ref<string>('')
const inviteSuccess = ref<string>('')
const existingInvitations = ref<TripInvitation[]>([])

function clearErrors() {
  errors.name = []
  errors.start_date = []
  errors.end_date = []
  errors.description = []
  formError.value = ''
}

function validateClient(): boolean {
  clearErrors()
  let isValid = true

  const trimmedName = formData.name.trim()
  if (!trimmedName) {
    errors.name.push('Nazwa podróży jest wymagana.')
    isValid = false
  } else if (trimmedName.length > 255) {
    errors.name.push('Nazwa podróży może mieć maksymalnie 255 znaków.')
    isValid = false
  }

  if (!formData.start_date) {
    errors.start_date.push('Data rozpoczęcia jest wymagana.')
    isValid = false
  }

  if (formData.start_date && formData.end_date) {
    if (formData.end_date < formData.start_date) {
      errors.end_date.push('Data zakończenia nie może być wcześniejsza niż data rozpoczęcia.')
      isValid = false
    }
  }

  return isValid
}

async function fetchTripData(id: string | number) {
  isLoadingTrip.value = true
  loadTripError.value = ''

  try {
    const trip = await getTrip(id)
    currentTrip.value = trip
    formData.name = trip.name
    formData.start_date = trip.start_date ?? ''
    formData.end_date = trip.end_date ?? ''
    formData.description = trip.description ?? ''
  } catch (err: unknown) {
    if (err instanceof ApiError && err.status === 404) {
      loadTripError.value = 'Nie znaleziono podróży o podanym identyfikatorze.'
    } else if (err instanceof Error) {
      loadTripError.value = err.message
    } else {
      loadTripError.value = 'Nie udało się załadować danych podróży.'
    }
  } finally {
    isLoadingTrip.value = false
  }
}

async function handleSubmit() {
  if (!validateClient()) return

  isSubmitting.value = true
  formError.value = ''

  const payload: TripPayload = {
    name: formData.name.trim(),
    start_date: formData.start_date,
    end_date: formData.end_date ? formData.end_date : null,
    description: formData.description?.trim() ? formData.description.trim() : null,
  }

  try {
    if (isEditMode.value && targetTripId.value !== undefined) {
      await updateTrip(targetTripId.value, payload)
    } else {
      await createTrip(payload)
    }

    await router.push('/me')
  } catch (err: unknown) {
    if (err instanceof ApiError) {
      if (err.validationErrors) {
        if (err.validationErrors.name) errors.name = err.validationErrors.name
        if (err.validationErrors.start_date) errors.start_date = err.validationErrors.start_date
        if (err.validationErrors.end_date) errors.end_date = err.validationErrors.end_date
        if (err.validationErrors.description) errors.description = err.validationErrors.description
      }
      formError.value = err.message || 'Wystąpił błąd podczas zapisywania podróży.'
    } else if (err instanceof Error) {
      formError.value = err.message
    } else {
      formError.value = 'Wystąpił nieoczekiwany błąd.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function handleCancel() {
  router.push('/me').catch(() => {})
}

function openDeleteModal() {
  showDeleteConfirm.value = true
}

function closeDeleteModal() {
  showDeleteConfirm.value = false
}

async function confirmDeleteTrip() {
  if (targetTripId.value === undefined) return

  isDeleting.value = true
  try {
    await deleteTrip(targetTripId.value)
    showDeleteConfirm.value = false
    await router.push('/me')
  } catch (err: unknown) {
    if (err instanceof Error) {
      formError.value = err.message
    } else {
      formError.value = 'Nie udało się usunąć podróży.'
    }
    showDeleteConfirm.value = false
  } finally {
    isDeleting.value = false
  }
}

// ── Zaproszenia znajomych ──

async function openInviteDialog() {
  showInviteDialog.value = true
  inviteError.value = ''
  inviteSuccess.value = ''
  selectedFriends.value = new Set()
  await loadFriends()
}

function closeInviteDialog() {
  showInviteDialog.value = false
}

async function loadFriends() {
  isLoadingFriends.value = true
  try {
    friends.value = await getFriends()
  } catch {
    friends.value = []
  } finally {
    isLoadingFriends.value = false
  }
}

function toggleFriendSelection(friendId: number) {
  const next = new Set(selectedFriends.value)
  if (next.has(friendId)) {
    next.delete(friendId)
  } else {
    next.add(friendId)
  }
  selectedFriends.value = next
}

function isFriendSelected(friendId: number): boolean {
  return selectedFriends.value.has(friendId)
}

function isFriendInvited(friendId: number): boolean {
  return existingInvitations.value.some(
    (inv) => inv.invitee_id === friendId && inv.status === 'pending',
  )
}

function isFriendAccepted(friendId: number): boolean {
  return existingInvitations.value.some(
    (inv) => inv.invitee_id === friendId && inv.status === 'accepted',
  )
}

async function sendInvitations() {
  if (selectedFriends.value.size === 0) return
  if (currentTrip.value === null) return

  isInviting.value = true
  inviteError.value = ''
  inviteSuccess.value = ''

  const tripId = currentTrip.value.id
  let successCount = 0

  for (const friendId of selectedFriends.value) {
    try {
      await inviteToTrip(tripId, friendId)
      successCount++
    } catch {
      // skip failed invitations
    }
  }

  selectedFriends.value = new Set()
  inviteSuccess.value = `Zaproszono ${successCount} znajomych do wycieczki.`
  isInviting.value = false
}

onMounted(() => {
  if (isEditMode.value && targetTripId.value !== undefined) {
    void fetchTripData(targetTripId.value)
  }
})
</script>

<template>
  <main class="trip-form-page">
    <div class="trip-form-container">
      <header class="page-header">
        <button
          type="button"
          class="back-button"
          aria-label="Wróć do listy podróży"
          @click="handleCancel"
        >
          <svg
            viewBox="0 0 24 24"
            width="18"
            height="18"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
          >
            <path d="M19 12H5M12 19l-7-7 7-7" />
          </svg>
          <span>Powrót</span>
        </button>

        <h1 class="page-title">
          {{ isEditMode ? 'Edytuj podróż' : 'Stwórz nową podróż' }}
        </h1>
        <p class="page-intro">
          {{
            isEditMode
              ? 'Zaktualizuj nazwę, daty lub opis swojej wycieczki.'
              : 'Wypełnij poniższe dane, aby zaplanować kolejną niezapomnianą przygodę.'
          }}
        </p>
      </header>

      <!-- Stan ładowania danych podróży (w trybie edycji) -->
      <div v-if="isLoadingTrip" class="status-card loading-card" role="status" aria-live="polite">
        <div class="spinner" aria-hidden="true"></div>
        <p>Ładowanie danych podróży...</p>
      </div>

      <!-- Błąd ładowania danych podróży -->
      <div v-else-if="loadTripError" class="status-card error-card" role="alert">
        <p class="error-title">Błąd pobierania danych</p>
        <p class="error-text">{{ loadTripError }}</p>
        <button type="button" class="action-btn-secondary" @click="handleCancel">
          Wróć do profilu
        </button>
      </div>

      <!-- Formularz -->
      <div v-else class="form-card">
        <div v-if="formError" class="alert-banner" role="alert" data-testid="form-error-banner">
          <svg
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
          >
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <span>{{ formError }}</span>
        </div>

        <form class="trip-form" @submit.prevent="handleSubmit">
          <!-- Nazwa podróży -->
          <div class="field" :class="{ 'field--invalid': errors.name.length > 0 }">
            <label for="trip-name"
              >Nazwa podróży <span class="required" aria-hidden="true">*</span></label
            >
            <input
              id="trip-name"
              v-model="formData.name"
              type="text"
              name="name"
              required
              maxlength="255"
              placeholder="np. Wyprawa w Bieszczady"
              :aria-invalid="errors.name.length > 0"
              :aria-describedby="errors.name.length > 0 ? 'name-error' : undefined"
              data-testid="trip-name-input"
            />
            <p
              v-if="errors.name.length > 0"
              id="name-error"
              class="field-error"
              data-testid="name-error"
            >
              {{ errors.name[0] }}
            </p>
          </div>

          <!-- Daty: Rozpoczęcie i Zakończenie -->
          <div class="dates-grid">
            <div class="field" :class="{ 'field--invalid': errors.start_date.length > 0 }">
              <label for="trip-start-date"
                >Data rozpoczęcia <span class="required" aria-hidden="true">*</span></label
              >
              <input
                id="trip-start-date"
                v-model="formData.start_date"
                type="date"
                name="start_date"
                required
                :aria-invalid="errors.start_date.length > 0"
                :aria-describedby="errors.start_date.length > 0 ? 'start-date-error' : undefined"
                data-testid="trip-start-date-input"
              />
              <p
                v-if="errors.start_date.length > 0"
                id="start-date-error"
                class="field-error"
                data-testid="start-date-error"
              >
                {{ errors.start_date[0] }}
              </p>
            </div>

            <div class="field" :class="{ 'field--invalid': errors.end_date.length > 0 }">
              <label for="trip-end-date"
                >Data zakończenia <span class="optional">(opcjonalnie)</span></label
              >
              <input
                id="trip-end-date"
                v-model="formData.end_date"
                type="date"
                name="end_date"
                :min="formData.start_date || undefined"
                :aria-invalid="errors.end_date.length > 0"
                :aria-describedby="errors.end_date.length > 0 ? 'end-date-error' : undefined"
                data-testid="trip-end-date-input"
              />
              <p
                v-if="errors.end_date.length > 0"
                id="end-date-error"
                class="field-error"
                data-testid="end-date-error"
              >
                {{ errors.end_date[0] }}
              </p>
            </div>
          </div>

          <!-- Opis -->
          <div class="field" :class="{ 'field--invalid': errors.description.length > 0 }">
            <label for="trip-description"
              >Opis podróży <span class="optional">(opcjonalnie)</span></label
            >
            <textarea
              id="trip-description"
              v-model="formData.description"
              name="description"
              rows="4"
              placeholder="Dodaj notatki, plan wyjazdu lub listę miejsc do odwiedzenia..."
              :aria-invalid="errors.description.length > 0"
              :aria-describedby="errors.description.length > 0 ? 'description-error' : undefined"
              data-testid="trip-description-input"
            ></textarea>
            <p
              v-if="errors.description.length > 0"
              id="description-error"
              class="field-error"
              data-testid="description-error"
            >
              {{ errors.description[0] }}
            </p>
          </div>

          <div v-if="isEditMode" class="invite-friends-row">
            <button
              type="button"
              class="btn-secondary"
              data-testid="invite-friends-button"
              @click="openInviteDialog"
            >
              Zaproś znajomych
            </button>
            <p class="invite-friends-hint">
              {{ friends.length > 0 ? `Masz ${friends.length} znajomych` : 'Brak znajomych na liście' }}
            </p>
          </div>
          <div v-else class="invite-friends-row">
            <button
              type="button"
              class="btn-secondary"
              disabled
              data-testid="invite-friends-button"
            >
              Zaproś znajomych
            </button>
            <p class="invite-friends-hint">Zaproszenia dostępne po utworzeniu wycieczki.</p>
          </div>

          <!-- Przyciski akcji formularza -->
          <div class="form-actions-row">
            <div class="left-actions">
              <RouterLink
                v-if="isEditMode && targetTripId !== undefined"
                :to="{ name: 'trip-details', params: { id: targetTripId } }"
                class="btn-secondary"
                data-testid="trip-details-button"
              >
                Szczegóły podróży
              </RouterLink>
              <button
                v-if="isEditMode"
                type="button"
                class="btn-danger-outline"
                :disabled="isSubmitting || isDeleting"
                data-testid="delete-trip-trigger"
                @click="openDeleteModal"
              >
                <svg
                  viewBox="0 0 24 24"
                  width="16"
                  height="16"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  aria-hidden="true"
                >
                  <path
                    d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                  />
                </svg>
                <span>Usuń podróż</span>
              </button>
            </div>

            <div class="right-actions">
              <button
                type="button"
                class="btn-secondary"
                :disabled="isSubmitting || isDeleting"
                data-testid="cancel-trip-button"
                @click="handleCancel"
              >
                Anuluj
              </button>

              <button
                type="submit"
                class="btn-primary"
                :disabled="isSubmitting || isDeleting"
                data-testid="submit-trip-button"
              >
                <span v-if="isSubmitting" class="spinner-small" aria-hidden="true"></span>
                <span>{{ isEditMode ? 'Zapisz zmiany' : 'Utwórz podróż' }}</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal potwierdzenia usunięcia wycieczki -->
    <ConfirmModal
      :is-open="showDeleteConfirm"
      title="Usuwanie podróży"
      :message="`Czy na pewno chcesz usunąć podróż „${formData.name || 'tę podróż'}”? Ta operacja jest nieodwracalna.`"
      confirm-label="Usuń podróż"
      cancel-label="Anuluj"
      :is-loading="isDeleting"
      variant="danger"
      @confirm="confirmDeleteTrip"
      @cancel="closeDeleteModal"
      @close="closeDeleteModal"
    />

    <!-- Dialog zaproszenia znajomych -->
    <dialog
      :open="showInviteDialog"
      class="invite-dialog"
      aria-labelledby="invite-dialog-title"
      @click.self="closeInviteDialog"
    >
      <div class="invite-dialog-content">
        <header class="dialog-header">
          <h2 id="invite-dialog-title">Zaproś znajomych do wycieczki</h2>
          <button
            class="dialog-close"
            type="button"
            aria-label="Zamknij"
            @click="closeInviteDialog"
          >
            ×
          </button>
        </header>

        <p class="invite-dialog-intro">
          Zaznacz znajomych, których chcesz zaprosić do wycieczki <strong>"{{ formData.name || 'tej wycieczki' }}"</strong>.
        </p>

        <div v-if="inviteSuccess" class="alert-banner success-banner" role="alert">
          <span>{{ inviteSuccess }}</span>
        </div>

        <div v-if="isLoadingFriends" class="status-card loading-card" role="status" aria-live="polite">
          <div class="spinner" aria-hidden="true"></div>
          <p>Ładowanie znajomych...</p>
        </div>

        <div v-else-if="friends.length === 0" class="empty-state">
          <p>Nie masz jeszcze znajomych. Dodaj ich w zakładce "Znajomi" w swoim profilu.</p>
        </div>

        <div v-else class="friends-list">
          <label
            v-for="friend in friends"
            :key="friend.id"
            class="friend-checkbox-item"
            :class="{
              'friend-pending': isFriendInvited(friend.id),
              'friend-accepted': isFriendAccepted(friend.id),
            }"
          >
            <div class="friend-avatar-wrapper">
              <img
                v-if="friend.avatar_path"
                :src="`/storage/${friend.avatar_path}`"
                :alt="`Avatar ${friend.name}`"
                class="friend-avatar"
              />
              <div v-else class="friend-avatar-placeholder">
                {{ friend.name?.charAt(0)?.toUpperCase() || '?' }}
              </div>
            </div>
            <span class="friend-name">{{ friend.name || 'Użytkownik' }}</span>
            <span v-if="isFriendAccepted(friend.id)" class="friend-badge accepted">Uczestnik</span>
            <span v-else-if="isFriendInvited(friend.id)" class="friend-badge pending">Oczekujące</span>
            <input
              type="checkbox"
              :disabled="isFriendInvited(friend.id) || isFriendAccepted(friend.id)"
              :checked="isFriendSelected(friend.id)"
              @change="toggleFriendSelection(friend.id)"
            />
          </label>
        </div>

        <div class="invite-dialog-actions">
          <button type="button" class="btn-cancel" @click="closeInviteDialog">Anuluj</button>
          <button
            type="button"
            class="btn-confirm"
            :disabled="isInviting || selectedFriends.size === 0"
            @click="sendInvitations"
          >
            {{ isInviting ? 'Wysyłanie…' : `Zaproś (${selectedFriends.size})` }}
          </button>
        </div>
      </div>
    </dialog>
  </main>
</template>

<style scoped>
.trip-form-page {
  display: grid;
  justify-items: center;
  width: 100%;
  padding: 3rem 0;
}

.trip-form-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  width: 100%;
  max-width: 44rem;
}

.page-header {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  align-self: flex-start;
  padding: 0.35rem 0.65rem;
  margin-bottom: 0.5rem;
  border: 1px solid rgba(56, 106, 84, 0.3);
  border-radius: 0.5rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.back-button:hover {
  background: #386a54;
  color: #ffffff;
  border-color: #386a54;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #111827;
}

.page-intro {
  font-size: 1rem;
  color: #4b5563;
}

/* ── Status cards ── */
.status-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 3rem 1.5rem;
  border-radius: 1.5rem;
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

.error-card {
  border-color: #fecaca;
  background: #fef2f2;
}

.error-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #991b1b;
}

.error-text {
  color: #7f1d1d;
  font-size: 0.95rem;
}

.action-btn-secondary {
  padding: 0.5rem 1.25rem;
  border: 1px solid #991b1b;
  border-radius: 0.5rem;
  color: #991b1b;
  background: transparent;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.action-btn-secondary:hover {
  background: #991b1b;
  color: #ffffff;
}

.spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid #e5e7eb;
  border-top-color: #386a54;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* ── Formularz ── */
.form-card {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding: clamp(1.5rem, 5vw, 2.5rem);
  border-radius: 1.5rem;
  background: linear-gradient(135deg, #e9f5ef, #f5f2e9);
  color: #111827;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.alert-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  border-radius: 0.625rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 0.9rem;
}

.alert-banner svg {
  flex-shrink: 0;
  stroke: #dc2626;
}

.trip-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.dates-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

@media (max-width: 600px) {
  .dates-grid {
    grid-template-columns: 1fr;
  }
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

label {
  font-weight: 600;
  font-size: 0.925rem;
  color: #1f2937;
}

.required {
  color: #dc2626;
  font-weight: 700;
}

.optional {
  font-size: 0.8rem;
  font-weight: normal;
  color: #6b7280;
}

input,
textarea {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1px solid #9ca3af;
  border-radius: 0.5rem;
  font: inherit;
  font-size: 0.95rem;
  color: #111827;
  background: #ffffff;
  transition:
    border-color 150ms ease,
    box-shadow 150ms ease;
}

input:focus,
textarea:focus {
  outline: none;
  border-color: #386a54;
  box-shadow: 0 0 0 3px rgba(56, 106, 84, 0.2);
}

.field--invalid input,
.field--invalid textarea {
  border-color: #dc2626;
}

.field--invalid input:focus,
.field--invalid textarea:focus {
  border-color: #dc2626;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
}

.field-error {
  font-size: 0.85rem;
  color: #dc2626;
  margin-top: 0.15rem;
}

.invite-friends-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.invite-friends-hint {
  color: #6b7280;
  font-size: 0.875rem;
}

/* ── Form Actions ── */
.form-actions-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(56, 106, 84, 0.2);
}

.left-actions,
.right-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.btn-primary,
.btn-secondary,
.btn-danger-outline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0.65rem 1.25rem;
  border-radius: 0.5rem;
  font: inherit;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 150ms ease;
}

.btn-primary {
  border: 0;
  color: #ffffff;
  background: #386a54;
  box-shadow: 0 2px 6px rgba(56, 106, 84, 0.25);
}

.btn-primary:hover:not(:disabled) {
  background: #2b5241;
  transform: translateY(-1px);
}

.btn-secondary {
  border: 1px solid #9ca3af;
  color: #374151;
  background: #ffffff;
}

.btn-secondary:hover:not(:disabled) {
  background: #f3f4f6;
  border-color: #6b7280;
}

.btn-danger-outline {
  border: 1px solid #ef4444;
  color: #dc2626;
  background: transparent;
}

.btn-danger-outline:hover:not(:disabled) {
  background: #fee2e2;
}

.btn-primary:disabled,
.btn-secondary:disabled,
.btn-danger-outline:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.spinner-small {
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 540px) {
  .form-actions-row {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .left-actions,
  .right-actions {
    flex-direction: column;
    width: 100%;
  }

  .btn-primary,
  .btn-secondary,
  .btn-danger-outline {
    width: 100%;
  }
}

/* ── Dark mode ── */
@media (prefers-color-scheme: dark) {
  .page-title {
    color: #f3f4f6;
  }

  .page-intro {
    color: #9ca3af;
  }

  .back-button {
    border-color: #6ee7b7;
    color: #6ee7b7;
  }

  .back-button:hover {
    background: #6ee7b7;
    color: #111827;
  }

  .form-card {
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    color: #f3f4f6;
  }

  .alert-banner {
    background: #3f1a1a;
    border-color: #7f1d1d;
    color: #fca5a5;
  }

  label {
    color: #f3f4f6;
  }

  .optional {
    color: #9ca3af;
  }

  .invite-friends-hint {
    color: #9ca3af;
  }

  input,
  textarea {
    color: #f3f4f6;
    background: #1f2937;
    border-color: #4b5563;
  }

  input:focus,
  textarea:focus {
    border-color: #6ee7b7;
    box-shadow: 0 0 0 3px rgba(110, 231, 183, 0.2);
  }

  .btn-secondary {
    background: #374151;
    border-color: #4b5563;
    color: #f3f4f6;
  }

  .btn-secondary:hover:not(:disabled) {
    background: #4b5563;
  }

  .btn-danger-outline {
    border-color: #f87171;
    color: #f87171;
  }

  .btn-danger-outline:hover:not(:disabled) {
    background: rgba(220, 38, 38, 0.2);
  }

  .form-actions-row {
    border-top-color: rgba(110, 231, 183, 0.2);
  }
}

/* ── Invite friends dialog ── */

.invite-dialog {
  width: min(36rem, calc(100vw - 2rem));
  max-width: none;
  max-height: calc(100vh - 2rem);
  margin: auto;
  padding: 0;
  overflow: auto;
  border: 0;
  border-radius: 1rem;
  color: #111827;
  background: #fff;
  box-shadow: 0 1.5rem 4rem rgb(0 0 0 / 25%);
}

.invite-dialog::backdrop {
  background: rgb(17 24 39 / 65%);
}

.invite-dialog-content {
  display: grid;
  gap: 1.25rem;
  padding: 1.5rem;
}

.invite-dialog-intro {
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #374151;
}

.success-banner {
  background: #e9f5ef;
  border-color: #386a54;
  color: #294d3d;
}

.empty-state {
  display: flex;
  justify-content: center;
  padding: 2rem 1rem;
  text-align: center;
  color: #6b7280;
  font-size: 0.95rem;
}

.friends-list {
  display: grid;
  gap: 0.5rem;
  max-height: 20rem;
  overflow-y: auto;
  padding: 0.5rem;
}

.friend-checkbox-item {
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: background 150ms ease;
}

.friend-checkbox-item:hover {
  background: #f3f4f6;
}

.friend-checkbox-item.friend-pending {
  opacity: 0.6;
  cursor: not-allowed;
}

.friend-checkbox-item.friend-accepted {
  background: #e9f5ef;
}

.friend-avatar-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
}

.friend-avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  object-fit: cover;
}

.friend-avatar-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  background: #386a54;
  color: #fff;
  font-weight: 600;
  font-size: 1rem;
}

.friend-name {
  font-weight: 500;
  font-size: 0.95rem;
  color: #1f2937;
}

.friend-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
}

.friend-badge.accepted {
  background: #386a54;
  color: #fff;
}

.friend-badge.pending {
  background: #f59e0b;
  color: #fff;
}

.friend-checkbox-item input[type='checkbox'] {
  width: 1.25rem;
  height: 1.25rem;
  accent-color: #386a54;
}

.invite-dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-cancel {
  padding: 0.5rem 1rem;
  border: 1px solid #9ca3af;
  border-radius: 0.5rem;
  color: #374151;
  background: #fff;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-cancel:hover {
  background: #f3f4f6;
}

.btn-confirm {
  padding: 0.5rem 1rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: #fff;
  background: #386a54;
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-confirm:hover:not(:disabled) {
  background: #294d3d;
}

.btn-confirm:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Dark mode for invite dialog */
@media (prefers-color-scheme: dark) {
  .invite-dialog {
    color: #f3f4f6;
    background: #1f2937;
  }

  .invite-dialog-intro {
    color: #d1d5db;
  }

  .success-banner {
    background: rgb(110 231 183 / 12%);
    border-color: #6ee7b7;
    color: #6ee7b7;
  }

  .empty-state {
    color: #9ca3af;
  }

  .friend-checkbox-item:hover {
    background: #374151;
  }

  .friend-checkbox-item.friend-accepted {
    background: rgb(110 231 183 / 12%);
  }

  .friend-avatar-placeholder {
    background: #6ee7b7;
    color: #111827;
  }

  .friend-name {
    color: #f3f4f6;
  }

  .friend-badge.accepted {
    background: #6ee7b7;
    color: #111827;
  }

  .btn-cancel {
    border-color: #6b7280;
    color: #d1d5db;
    background: #1f2937;
  }

  .btn-cancel:hover {
    background: #374151;
  }

  .btn-confirm {
    border-color: #6ee7b7;
    background: #6ee7b7;
    color: #111827;
  }

  .btn-confirm:hover:not(:disabled) {
    background: #386a54;
    color: #fff;
  }
}
</style>
