<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { apiClient, ApiError, updateMyProfile } from '@/api'
import type { UpdateProfilePayload } from '@/api/auth'
import type { ProfilePrivacySettings } from '@/api/types'
import { useAuthStore } from '@/stores/auth'
import UserTripsList from '@/components/trips/UserTripsList.vue'

type ProfileTab = 'trips' | 'friends' | 'stats'
type ProfileFieldKey = 'name' | 'email' | 'first_name' | 'last_name' | 'city' | 'birth_date'

interface ProfileForm {
  name: string
  email: string
  first_name: string
  last_name: string
  city: string
  birth_date: string
  avatar_path: string
}

interface ProfileField {
  key: ProfileFieldKey
  privacyKey: keyof ProfilePrivacySettings
  label: string
  inputType: 'text' | 'email' | 'date'
  autocomplete: string
}

const auth = useAuthStore()
const activeTab = ref<ProfileTab>('trips')
const profileVisibility = ref<'private' | 'public'>(
  auth.user?.profile_visibility ?? 'private',
)
const defaultPrivacySettings: ProfilePrivacySettings = {
  name: false,
  email: false,
  firstName: false,
  lastName: false,
  city: false,
  birthDate: false,
  avatar: false,
}
const fieldVisibility = reactive<ProfilePrivacySettings>({
  ...defaultPrivacySettings,
  ...auth.user?.privacy_settings,
})
const form = reactive<ProfileForm>({
  name: auth.user?.name ?? '',
  email: auth.user?.email ?? '',
  first_name: auth.user?.first_name ?? '',
  last_name: auth.user?.last_name ?? '',
  city: auth.user?.city ?? '',
  birth_date: auth.user?.birth_date ?? '',
  avatar_path: auth.user?.avatar_path ?? '1.png',
})
const profileFields: ProfileField[] = [
  {
    key: 'name',
    privacyKey: 'name',
    label: 'Nazwa profilu',
    inputType: 'text',
    autocomplete: 'nickname',
  },
  {
    key: 'email',
    privacyKey: 'email',
    label: 'E-mail',
    inputType: 'email',
    autocomplete: 'email',
  },
  {
    key: 'first_name',
    privacyKey: 'firstName',
    label: 'Imię',
    inputType: 'text',
    autocomplete: 'given-name',
  },
  {
    key: 'last_name',
    privacyKey: 'lastName',
    label: 'Nazwisko',
    inputType: 'text',
    autocomplete: 'family-name',
  },
  {
    key: 'city',
    privacyKey: 'city',
    label: 'Miejscowość',
    inputType: 'text',
    autocomplete: 'address-level2',
  },
  {
    key: 'birth_date',
    privacyKey: 'birthDate',
    label: 'Data urodzenia',
    inputType: 'date',
    autocomplete: 'bday',
  },
]
const avatarDialog = ref<HTMLDialogElement | null>(null)
const showVisibilityInfo = ref(false)
const avatarFiles = Array.from({ length: 10 }, (_, index) => `${index + 1}.png`)
const isSaving = ref(false)
const saveError = ref('')
const statusMessage = ref('')
const friendsCount: number = 0
const friendsCountLabel = computed(() => {
  if (friendsCount === 1) return '1 znajomy'
  return `${friendsCount} znajomych`
})

const avatarUrl = (filename: string) =>
  new URL(`/avatars/${filename}`, apiClient.defaults.baseURL).toString()

function toggleProfileVisibility() {
  profileVisibility.value = profileVisibility.value === 'public' ? 'private' : 'public'
}

function toggleFieldVisibility(field: keyof ProfilePrivacySettings) {
  if (profileVisibility.value === 'private') return
  fieldVisibility[field] = !fieldVisibility[field]
}

function openAvatarDialog() {
  avatarDialog.value?.showModal()
}

function closeAvatarDialog() {
  avatarDialog.value?.close()
}

function selectAvatar(filename: string) {
  form.avatar_path = filename
  closeAvatarDialog()
}

async function saveProfile() {
  isSaving.value = true
  saveError.value = ''
  statusMessage.value = ''

  const payload: UpdateProfilePayload = {
    name: form.name,
    first_name: form.first_name,
    last_name: form.last_name,
    city: form.city,
    birth_date: form.birth_date || null,
    avatar_path: form.avatar_path,
    profile_visibility: profileVisibility.value,
    privacy_settings: { ...fieldVisibility },
  }

  try {
    const updatedProfile = await updateMyProfile(payload)
    auth.user = updatedProfile
    statusMessage.value = 'Zmiany profilu zostały zapisane.'
  } catch (error) {
    saveError.value =
      error instanceof ApiError
        ? Object.values(error.validationErrors ?? {})[0]?.[0] ?? error.message
        : 'Nie udało się zapisać profilu. Spróbuj ponownie.'
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <main class="profile-page">
    <div class="profile-layout">
      <section class="profile-card profile-editor-card" aria-labelledby="profile-title">
        <header class="profile-header">
          <h1 id="profile-title">Edytuj profil</h1>

          <div class="visibility-control">
            <button
              class="visibility-button master-visibility"
              type="button"
              :aria-pressed="profileVisibility === 'public'"
              @click="toggleProfileVisibility"
            >
              <svg
                :class="{ 'eye-closed': profileVisibility === 'private' }"
                aria-hidden="true"
                viewBox="0 0 24 24"
              >
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
                <path v-if="profileVisibility === 'private'" d="M3 3l18 18" />
              </svg>
              {{ profileVisibility === 'public' ? 'Profil publiczny' : 'Profil prywatny' }}
            </button>
            <button
              class="visibility-info-button"
              type="button"
              aria-label="Informacje o widoczności profilu"
              aria-controls="visibility-info"
              :aria-expanded="showVisibilityInfo"
              @click="showVisibilityInfo = !showVisibilityInfo"
            >
              <svg aria-hidden="true" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 11v5m0-8h.01" />
              </svg>
            </button>
            <p v-if="showVisibilityInfo" id="visibility-info" class="visibility-info" role="tooltip">
              Profil prywatny jest ukryty dla innych. Profil publiczny udostępnia tylko pola
              oznaczone jako publiczne. Ustawienia poszczególnych pól pozostają zapisane po
              przełączeniu profilu na prywatny.
            </p>
          </div>
        </header>

        <div class="avatar-row">
          <button
            class="avatar-preview"
            type="button"
            aria-label="Zmień awatar"
            @click="openAvatarDialog"
          >
            <img :src="avatarUrl(form.avatar_path)" alt="" />
          </button>
          <div class="avatar-details">
            <strong>{{ friendsCountLabel }}</strong>
          </div>
          <button
            class="visibility-button avatar-visibility"
            type="button"
            :disabled="profileVisibility === 'private'"
            :aria-pressed="fieldVisibility.avatar"
            :aria-label="
              fieldVisibility.avatar
                ? 'Ukryj zdjęcie profilowe i liczbę znajomych'
                : 'Pokaż zdjęcie profilowe i liczbę znajomych'
            "
            @click="toggleFieldVisibility('avatar')"
          >
            <svg aria-hidden="true" viewBox="0 0 24 24">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
              <circle cx="12" cy="12" r="3" />
              <path v-if="!fieldVisibility.avatar" d="M3 3l18 18" />
            </svg>
            <span>{{ fieldVisibility.avatar ? 'Publiczne' : 'Prywatne' }}</span>
          </button>
        </div>

        <form class="profile-form" @submit.prevent="saveProfile">
          <div v-for="field in profileFields" :key="field.key" class="info-row">
            <div class="field">
              <label :for="field.key">{{ field.label }}</label>
              <div v-if="field.key === 'email'" class="static-field-value">
                <strong>{{ form.email }}</strong>
              </div>
              <input
                v-else
                :id="field.key"
                v-model="form[field.key]"
                :type="field.inputType"
                :autocomplete="field.autocomplete"
                :required="field.key === 'name'"
                maxlength="255"
              />
            </div>

            <button
              class="visibility-button field-visibility"
              type="button"
              :disabled="profileVisibility === 'private'"
              :aria-pressed="fieldVisibility[field.privacyKey]"
              :aria-label="
                fieldVisibility[field.privacyKey]
                  ? `Ukryj: ${field.label}`
                  : `Pokaż: ${field.label}`
              "
              @click="toggleFieldVisibility(field.privacyKey)"
            >
              <svg aria-hidden="true" viewBox="0 0 24 24">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
                <path v-if="!fieldVisibility[field.privacyKey]" d="M3 3l18 18" />
              </svg>
              <span>{{ fieldVisibility[field.privacyKey] ? 'Publiczne' : 'Prywatne' }}</span>
            </button>
          </div>

          <p v-if="saveError" class="form-message form-error" role="alert">{{ saveError }}</p>
          <p v-if="statusMessage" class="form-message form-success" role="status">
            {{ statusMessage }}
          </p>
          <button class="save-button" type="submit" :disabled="isSaving">
            {{ isSaving ? 'Zapisywanie...' : 'Zapisz zmiany' }}
          </button>
        </form>
      </section>

      <section class="profile-card activity-card" aria-labelledby="activity-title">
        <h2 id="activity-title">Aktywność</h2>

        <div class="tabs" role="tablist" aria-label="Sekcje profilu">
          <button
            id="tab-trips"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'trips'"
            @click="activeTab = 'trips'"
          >
            Wycieczki
          </button>
          <button
            id="tab-friends"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'friends'"
            @click="activeTab = 'friends'"
          >
            Znajomi
          </button>
          <button
            id="tab-stats"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'stats'"
            @click="activeTab = 'stats'"
          >
            Statystyki
          </button>
        </div>

        <div class="tab-panel" role="tabpanel" :aria-labelledby="`tab-${activeTab}`">
          <UserTripsList v-if="activeTab === 'trips'" />
          <p v-else-if="activeTab === 'friends'">Lista znajomych pojawi się tutaj.</p>
          <p v-else>Statystyki pojawią się tutaj.</p>
        </div>
      </section>
    </div>

    <dialog
      ref="avatarDialog"
      class="avatar-dialog"
      aria-labelledby="avatar-dialog-title"
      @click.self="closeAvatarDialog"
    >
      <div class="avatar-dialog-content">
        <header class="dialog-header">
          <h2 id="avatar-dialog-title">Wybierz awatar</h2>
          <button
            class="dialog-close"
            type="button"
            aria-label="Zamknij wybór awatara"
            @click="closeAvatarDialog"
          >
            ×
          </button>
        </header>
        <div class="avatar-gallery">
          <button
            v-for="avatar in avatarFiles"
            :key="avatar"
            class="avatar-option"
            :class="{ selected: form.avatar_path === avatar }"
            type="button"
            :aria-label="`Wybierz awatar ${avatarFiles.indexOf(avatar) + 1}`"
            :aria-pressed="form.avatar_path === avatar"
            @click="selectAvatar(avatar)"
          >
            <img :src="avatarUrl(avatar)" :alt="`Awatar ${avatarFiles.indexOf(avatar) + 1}`" />
          </button>
        </div>
      </div>
    </dialog>
  </main>
</template>

<style scoped>
.profile-page {
  display: grid;
  justify-items: center;
  width: 100%;
  padding: 3rem 0;
}

.profile-layout {
  display: grid;
  grid-template-columns: minmax(30rem, 0.8fr) minmax(0, 1.4fr);
  align-items: start;
  gap: 1.5rem;
  width: 100%;
}

.profile-card {
  display: grid;
  gap: 1.5rem;
  width: 100%;
  min-width: 0;
  padding: clamp(1.5rem, 6vw, 3rem);
  border-radius: 1.5rem;
  background: linear-gradient(135deg, #e9f5ef, #f5f2e9);
  color: #111827;
}

.profile-editor-card {
  min-width: 30rem;
}

.profile-header,
.avatar-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.visibility-control {
  position: relative;
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.5rem;
}

.visibility-info-button {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: 1px solid #386a54;
  border-radius: 50%;
  color: #386a54;
  background: transparent;
}

.visibility-info-button svg {
  width: 1.2rem;
  height: 1.2rem;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.8;
}

.visibility-info-button:focus-visible {
  outline: 3px solid #294d3d;
  outline-offset: 2px;
}

.visibility-info {
  position: absolute;
  z-index: 1;
  top: calc(100% + 0.5rem);
  right: 0;
  width: min(20rem, calc(100vw - 3rem));
  margin: 0;
  padding: 0.75rem 1rem;
  border: 1px solid #386a54;
  border-radius: 0.75rem;
  color: #111827;
  background: #fff;
  box-shadow: 0 0.5rem 1.5rem rgb(0 0 0 / 15%);
  font-size: 0.875rem;
  line-height: 1.5;
}

h1 {
  font-size: 2rem;
  color: inherit;
}

h2 {
  font-size: 1.5rem;
  color: inherit;
}

.avatar-preview {
  flex: 0 0 auto;
  width: 7rem;
  aspect-ratio: 1;
  overflow: hidden;
  border: 3px solid #386a54;
  border-radius: 50%;
  background: rgb(255 255 255 / 45%);
  cursor: pointer;
  padding: 0;
}

.avatar-preview:focus-visible {
  outline: 3px solid #294d3d;
  outline-offset: 3px;
}

.avatar-preview img,
.avatar-option img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-details {
  display: grid;
  flex: 1;
  gap: 0.25rem;
}

.avatar-details p {
  color: #4b5563;
  font-size: 0.875rem;
}

.choose-avatar-button {
  justify-self: start;
  padding: 0;
  border: 0;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-weight: 700;
  text-decoration: underline;
}

.profile-form,
.field {
  display: grid;
  gap: 0.5rem;
}

.profile-form {
  gap: 1.25rem;
}

.field {
  flex: 1;
  min-width: 0;
}

.field label {
  color: inherit;
}

input {
  width: 100%;
  min-height: 2.75rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid #9ca3af;
  border-radius: 0.5rem;
  font: inherit;
  color: #111827;
  background: #ffffff;
}

.static-field-value {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  overflow-wrap: anywhere;
  color: #386a54;
  text-align: center;
}

.static-field-value strong {
  font-size: 20px;
  font-weight: 800;
}

.visibility-button,
.save-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0.6rem 0.85rem;
  border: 0;
  border-radius: 0.5rem;
  color: white;
  background: #386a54;
  font: inherit;
  font-weight: 600;
  white-space: nowrap;
}

.visibility-button svg {
  width: 1.25rem;
  height: 1.25rem;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.avatar-visibility {
  align-self: center;
  flex: 0 0 auto;
}

.save-button {
  width: 100%;
}

.visibility-button:disabled,
.save-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.info-row {
  display: flex;
  align-items: end;
  gap: 1rem;
}

.form-message {
  margin-bottom: -0.75rem;
}

.form-error {
  color: #b42318;
}

.form-success {
  color: #294d3d;
}

.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  border-bottom: 1px solid var(--color-border);
}

.tabs button {
  padding: 0.65rem 0.8rem;
  border: 0;
  border-bottom: 3px solid transparent;
  color: #294d3d;
  background: transparent;
  font: inherit;
  cursor: pointer;
}

.tabs button[aria-selected='true'] {
  border-bottom-color: #386a54;
  font-weight: 700;
}

.tab-panel {
  min-height: 12rem;
  padding-top: 1rem;
}

.avatar-dialog {
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

.avatar-dialog::backdrop {
  background: rgb(17 24 39 / 65%);
}

.avatar-dialog-content {
  display: grid;
  gap: 1.5rem;
  padding: 1.5rem;
}

.dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.dialog-close {
  width: 2.5rem;
  height: 2.5rem;
  border: 0;
  border-radius: 50%;
  color: inherit;
  background: #f3f4f6;
  font-size: 1.75rem;
  line-height: 1;
}

.avatar-gallery {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.75rem;
}

.avatar-option {
  aspect-ratio: 1;
  padding: 0.2rem;
  overflow: hidden;
  border: 3px solid transparent;
  border-radius: 50%;
  background: transparent;
}

.avatar-option.selected {
  border-color: #386a54;
}

@media (max-width: 900px) {
  .profile-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 600px) {
  .profile-page {
    padding: 1rem 0;
  }

  .profile-editor-card {
    min-width: 0;
  }

  .profile-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .visibility-control {
    align-self: stretch;
  }

  .info-row {
    align-items: stretch;
  }

  .avatar-row {
    flex-wrap: wrap;
  }
}

@media (max-width: 420px) {
  .info-row {
    flex-direction: column;
  }

  .field-visibility {
    align-self: flex-start;
  }
}

@media (prefers-color-scheme: dark) {
  .profile-card {
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    color: #f3f4f6;
  }

  .avatar-details p {
    color: #9ca3af;
  }

  .static-field-value {
    color: #6ee7b7;
  }

  .choose-avatar-button {
    color: #6ee7b7;
  }

  input {
    color: #f3f4f6;
    background: #1f2937;
    border-color: #4b5563;
  }

  .tabs {
    border-bottom-color: #374151;
  }

  .tabs button {
    color: #9ca3af;
  }

  .tabs button:hover {
    color: #f3f4f6;
  }

  .tabs button[aria-selected='true'] {
    color: #6ee7b7;
    border-bottom-color: #6ee7b7;
  }

  .tab-panel p {
    color: #9ca3af;
  }

  .form-error {
    color: #fca5a5;
  }

  .form-success {
    color: #6ee7b7;
  }

  .avatar-dialog {
    color: #f3f4f6;
    background: #1f2937;
  }

  .visibility-info-button {
    border-color: #6ee7b7;
    color: #6ee7b7;
  }

  .visibility-info-button:focus-visible {
    outline-color: #6ee7b7;
  }

  .visibility-info {
    border-color: #6ee7b7;
    color: #f3f4f6;
    background: #1f2937;
  }

  .dialog-close {
    background: #374151;
  }
}
</style>
