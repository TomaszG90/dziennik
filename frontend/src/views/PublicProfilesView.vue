<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { getPublicProfiles, type PublicProfile } from '@/api/publicProfiles'

const profiles = ref<PublicProfile[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const isLoading = ref(true)
const error = ref('')

function profileName(profile: PublicProfile): string {
  const fullName = [profile.first_name, profile.last_name].filter(Boolean).join(' ')
  return profile.name || fullName || `Podróżnik #${profile.id}`
}

async function loadProfiles(page = 1) {
  isLoading.value = true
  error.value = ''

  try {
    const result = await getPublicProfiles(page)
    profiles.value = result.data
    currentPage.value = result.meta.current_page
    lastPage.value = result.meta.last_page
    total.value = result.meta.total
  } catch (cause: unknown) {
    error.value =
      cause instanceof Error ? cause.message : 'Nie udało się załadować publicznych profili.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => void loadProfiles())
</script>

<template>
  <section class="profiles-page">
    <header class="profiles-heading">
      <p class="eyebrow">Odkrywaj podróże</p>
      <h1>Publiczne profile</h1>
      <p>Przeglądaj profile i podróże udostępnione przez innych użytkowników.</p>
    </header>

    <div v-if="isLoading" class="profiles-message" role="status">Ładowanie profili…</div>

    <section v-else-if="error" class="profiles-message profiles-error" role="alert">
      <p>{{ error }}</p>
      <button type="button" @click="loadProfiles(currentPage)">Spróbuj ponownie</button>
    </section>

    <section v-else-if="profiles.length === 0" class="profiles-message">
      <h2>Nie znaleziono publicznych profili</h2>
      <p>Gdy użytkownicy udostępnią swoje profile, pojawią się tutaj.</p>
    </section>

    <template v-else>
      <p class="profiles-count">Publiczne profile: {{ total }}</p>
      <ul class="profiles-grid">
        <li v-for="profile in profiles" :key="profile.id" class="profile-card">
          <div class="profile-avatar" aria-hidden="true">
            {{ profileName(profile).slice(0, 1).toLocaleUpperCase() }}
          </div>
          <div class="profile-card-content">
            <h2>{{ profileName(profile) }}</h2>
            <p v-if="profile.city">{{ profile.city }}</p>
            <RouterLink :to="{ name: 'public-profile', params: { id: profile.id } }">
              Zobacz profil
            </RouterLink>
          </div>
        </li>
      </ul>

      <nav v-if="lastPage > 1" class="profiles-pagination" aria-label="Stronicowanie profili">
        <button
          type="button"
          :disabled="currentPage <= 1 || isLoading"
          @click="loadProfiles(currentPage - 1)"
        >
          Poprzednia
        </button>
        <span>Strona {{ currentPage }} z {{ lastPage }}</span>
        <button
          type="button"
          :disabled="currentPage >= lastPage || isLoading"
          @click="loadProfiles(currentPage + 1)"
        >
          Następna
        </button>
      </nav>
    </template>
  </section>
</template>

<style scoped>
.profiles-page {
  width: min(100% - 2rem, 1100px);
  margin: 2rem auto 4rem;
}

.profiles-heading {
  margin-bottom: 1.5rem;
}

.profiles-heading h1 {
  margin: 0.25rem 0 0.5rem;
  color: #173d30;
  font-size: clamp(1.8rem, 4vw, 2.5rem);
}

.profiles-heading > p:last-child,
.profiles-count {
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

.profiles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
  gap: 1rem;
  margin: 1rem 0;
  padding: 0;
  list-style: none;
}

.profile-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: 1rem;
  background: var(--color-background-soft);
}

.profile-avatar {
  display: grid;
  flex: 0 0 3rem;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  color: white;
  background: #386a54;
  font-size: 1.25rem;
  font-weight: 700;
}

.profile-card-content h2 {
  margin: 0;
  color: #173d30;
  font-size: 1.1rem;
}

.profile-card-content p {
  margin: 0.25rem 0;
  color: #64746c;
}

.profile-card-content a {
  display: inline-block;
  margin-top: 0.5rem;
  color: #386a54;
  font-weight: 700;
}

.profiles-message {
  padding: 1.5rem;
  border: 1px solid var(--color-border);
  border-radius: 1rem;
}

.profiles-error {
  color: #9f2424;
}

.profiles-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  margin-top: 1.5rem;
}

.profiles-pagination button,
.profiles-message button {
  padding: 0.55rem 0.9rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.profiles-pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
