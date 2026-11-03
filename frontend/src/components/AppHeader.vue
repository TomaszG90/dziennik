<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  await router.push('/')
}
</script>

<template>
  <header class="app-header">
    <RouterLink class="brand" to="/">Dziennik podróży</RouterLink>

    <nav aria-label="Nawigacja główna">
      <RouterLink to="/">Strona główna</RouterLink>
      <RouterLink to="/me">Profil</RouterLink>

      <template v-if="auth.isAuthenticated">
        <RouterLink to="/dashboard">Mój dziennik</RouterLink>

        <span>{{ auth.user?.name }}</span>
        <button class="logout-button" type="button" @click="handleLogout">Wyloguj</button>
      </template>

      <template v-else>
        <RouterLink :to="{ name: 'auth' }">Konto</RouterLink>
      </template>
    </nav>
  </header>
</template>

<style scoped>
.app-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 0;
  border-bottom: 1px solid var(--color-border);
}

.brand {
  font-weight: 700;
}

nav {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.logout-button {
  min-height: 2.5rem;
  padding: 0.5rem 0.9rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-weight: 600;
  transition:
    color 150ms ease,
    background-color 150ms ease;
}

.logout-button:hover,
.logout-button:focus-visible {
  color: #ffffff;
  background: #386a54;
}

.logout-button:focus-visible {
  outline: 2px solid #386a54;
  outline-offset: 2px;
}

@media (prefers-color-scheme: dark) {
  .logout-button {
    color: #6ee7b7;
    border-color: #6ee7b7;
  }

  .logout-button:hover,
  .logout-button:focus-visible {
    color: #111827;
    background: #6ee7b7;
  }

  .logout-button:focus-visible {
    outline-color: #6ee7b7;
  }
}
</style>
