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
        <RouterLink :to="{ name: 'user-map' }">Mapa podróży</RouterLink>

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
  flex-wrap: wrap;
  margin: 0.75rem 0 0;
  padding: 1rem 1.25rem;
  border: 1px solid rgba(56, 106, 84, 0.16);
  border-radius: 1rem;
  background: linear-gradient(135deg, #e9f5ef, #f5f2e9);
  box-shadow: 0 3px 12px rgba(41, 77, 61, 0.08);
}

.brand {
  color: #294d3d;
  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

nav {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.4rem;
}

nav a,
nav span {
  padding: 0.5rem 0.75rem;
  border-radius: 0.6rem;
  color: #294d3d;
  font-size: 1.05rem;
  font-weight: 600;
}

nav a {
  transition:
    color 150ms ease,
    background-color 150ms ease;
}

nav a:hover,
nav a:focus-visible,
nav a.router-link-exact-active {
  color: #ffffff;
  background: #386a54;
}

nav a:focus-visible {
  outline: 2px solid #294d3d;
  outline-offset: 2px;
}

nav span {
  color: #386a54;
}

.logout-button {
  min-height: 2.5rem;
  padding: 0.45rem 0.85rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  color: #294d3d;
  background: transparent;
  font: inherit;
  font-size: 1rem;
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

@media (max-width: 640px) {
  .app-header {
    align-items: flex-start;
    flex-direction: column;
  }

  nav {
    width: 100%;
  }
}

@media (prefers-color-scheme: dark) {
  .app-header {
    border-color: rgba(110, 231, 183, 0.18);
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.22);
  }

  .brand,
  nav a,
  nav span {
    color: #6ee7b7;
  }

  nav a:hover,
  nav a:focus-visible,
  nav a.router-link-exact-active {
    color: #111827;
    background: #6ee7b7;
  }

  nav a:focus-visible {
    outline-color: #6ee7b7;
  }

  nav span {
    color: #a7f3d0;
  }

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
