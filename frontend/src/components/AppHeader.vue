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

      <template v-if="auth.isAuthenticated">
        <RouterLink to="/dashboard">Mój dziennik</RouterLink>
        <span>{{ auth.user?.name }}</span>
        <button type="button" @click="handleLogout">Wyloguj</button>
      </template>

      <template v-else>
        <RouterLink to="/login">Zaloguj</RouterLink>
        <RouterLink to="/register">Zarejestruj się</RouterLink>
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
</style>
