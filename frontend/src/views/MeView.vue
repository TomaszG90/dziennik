<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

type VisibilityKey = FieldKey | 'avatar'
type FieldKey = 'name' | 'email' | 'firstName' | 'lastName' | 'city' | 'birthDate'

type ProfileTab = 'trips' | 'friends' | 'stats'
const activeTab = ref<ProfileTab>('trips')

const fieldVisibility = reactive<Record<VisibilityKey, boolean>>({
  name: false,
  email: false,
  firstName: false,
  lastName: false,
  city: false,
  birthDate: false,
  avatar: false,
})

interface ProfileField {
  key: FieldKey
  label: string
  value: string
  editable: boolean
  inputType: 'text' | 'email' | 'date'
  autocomplete: string
}

const auth = useAuthStore()

const profileVisibility = ref<'private' | 'public'>('private')

const profileFields = reactive<ProfileField[]>([
  {
    key: 'name',
    label: 'Nazwa profilu',
    value: auth.user?.name ?? '',
    editable: true,
    inputType: 'text',
    autocomplete: 'nickname',
  },
  {
    key: 'email',
    label: 'E-mail',
    value: auth.user?.email ?? '',
    editable: false,
    inputType: 'email',
    autocomplete: 'email',
  },
  {
    key: 'firstName',
    label: 'Imię',
    value: '',
    editable: true,
    inputType: 'text',
    autocomplete: 'given-name',
  },
  {
    key: 'lastName',
    label: 'Nazwisko',
    value: '',
    editable: true,
    inputType: 'text',
    autocomplete: 'family-name',
  },
  {
    key: 'city',
    label: 'Miejscowość',
    value: '',
    editable: true,
    inputType: 'text',
    autocomplete: 'address-level2',
  },
  {
    key: 'birthDate',
    label: 'Data urodzenia',
    value: '',
    editable: true,
    inputType: 'date',
    autocomplete: 'bday',
  },
])

function toggleProfileVisibility() {
  if (profileVisibility.value === 'public') {
    profileVisibility.value = 'private'
    fieldVisibility.name = false
    fieldVisibility.email = false
    fieldVisibility.avatar = false
    fieldVisibility.firstName = false
    fieldVisibility.lastName = false
    fieldVisibility.city = false
    fieldVisibility.birthDate = false
    return
  }

  profileVisibility.value = 'public'
}

function toggleFieldVisibility(field: VisibilityKey) {
  if (profileVisibility.value === 'private') return

  fieldVisibility[field] = !fieldVisibility[field]
}
</script>

<template>
  <main class="profile-page">
    <div class="profile-layout">
      <section class="profile-card" aria-labelledby="profile-title">
        <header class="profile-header">
          <div>
            <h1 id="profile-title">Edytuj profil</h1>
          </div>

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
              <path
                v-if="profileVisibility === 'private'"
                d="M3 3l18 18"
              />
            </svg>

            {{ profileVisibility === 'public' ? 'Profil publiczny' : 'Profil prywatny' }}
          </button>
        </header>

        <div class="avatar-row">
          <div class="avatar-placeholder" role="img" aria-label="Zdjęcie profilowe">
            <span>Zdjęcie<br />profilowe</span>
          </div>

          <button
            class="visibility-button"
            type="button"
            :disabled="profileVisibility === 'private'"
            :aria-pressed="fieldVisibility.avatar"
            :aria-label="
              fieldVisibility.avatar
                ? 'Ukryj zdjęcie profilowe'
                : 'Pokaż zdjęcie profilowe'
            "
            @click="toggleFieldVisibility('avatar')"
          >
            <svg aria-hidden="true" viewBox="0 0 24 24">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
              <circle cx="12" cy="12" r="3" />
              <path v-if="!fieldVisibility.avatar" d="M3 3l18 18" />
            </svg>
            <span v-if="fieldVisibility.avatar">Publiczne</span>
          </button>
        </div>

        <form class="profile-form" @submit.prevent>
          <div
            v-for="field in profileFields"
            :key="field.key"
            class="info-row"
          >
            <div class="field">
              <label :for="field.key">{{ field.label }}</label>
              <input
                :id="field.key"
                v-model="field.value"
                :type="field.inputType"
                :autocomplete="field.autocomplete"
                :readonly="!field.editable"
              />
            </div>

            <button
              class="visibility-button"
              type="button"
              :disabled="profileVisibility === 'private'"
              :aria-pressed="fieldVisibility[field.key]"
              :aria-label="
                fieldVisibility[field.key]
                  ? `Ukryj: ${field.label}`
                  : `Pokaż: ${field.label}`
              "
              @click="toggleFieldVisibility(field.key)"
            >
              <svg aria-hidden="true" viewBox="0 0 24 24">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
                <path v-if="!fieldVisibility[field.key]" d="M3 3l18 18" />
              </svg>
              <span v-if="fieldVisibility[field.key]">Publiczne</span>
            </button>
          </div>

          <button class="save-button" type="button" disabled>
            Zapisz zmiany
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
          <p v-if="activeTab === 'trips'">Lista wycieczek pojawi się tutaj.</p>
          <p v-else-if="activeTab === 'friends'">Lista znajomych pojawi się tutaj.</p>
          <p v-else>Statystyki pojawią się tutaj.</p>
        </div>
      </section>
    </div>
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
  grid-template-columns: minmax(17rem, 0.8fr) minmax(0, 1.4fr);
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

.profile-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

h1 {
  margin-bottom: 0.5rem;
  font-size: 2rem;
  color: inherit;
}

h2 {
  font-size: 1.5rem;
  color: inherit;
}

label {
  color: inherit;
}

.intro {
  color: #4b5563;
}

.avatar-row,
.info-row {
  display: flex;
  align-items: end;
  gap: 1rem;
}

.avatar-placeholder {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 8rem;
  aspect-ratio: 1;
  border: 2px dashed #386a54;
  border-radius: 50%;
  color: #294d3d;
  background: rgb(255 255 255 / 45%);
  font-weight: 600;
  text-align: center;
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

input[readonly] {
  color: #4b5563;
  background: #f3f4f6;
  border-color: #d1d5db;
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

.master-visibility {
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

@media (max-width: 600px) {
  .profile-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .info-row {
    align-items: stretch;
  }
}

@media (max-width: 420px) {
  .info-row {
    flex-direction: column;
  }

  .info-row .visibility-button {
    align-self: flex-start;
  }
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

@media (max-width: 850px) {
  .profile-layout {
    grid-template-columns: 1fr;
  }
}

/* ── Dark mode ── */
@media (prefers-color-scheme: dark) {
  .profile-card {
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    color: #f3f4f6;
  }

  .intro {
    color: #9ca3af;
  }

  .avatar-placeholder {
    border-color: #6ee7b7;
    color: #6ee7b7;
    background: rgb(0 0 0 / 25%);
  }

  input {
    color: #f3f4f6;
    background: #1f2937;
    border-color: #4b5563;
  }

  input[readonly] {
    color: #9ca3af;
    background: #111827;
    border-color: #374151;
  }

  input::placeholder {
    color: #6b7280;
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
}
</style>
