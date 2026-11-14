<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import AppHeader from './components/AppHeader.vue'
import ToastContainer from './components/ToastContainer.vue'

const router = useRouter()

function handleUnauthorized() {
  void router.push({ name: 'auth', query: { mode: 'login' } })
}

function handleForbidden() {
  void router.push({ name: 'forbidden' })
}

onMounted(() => {
  window.addEventListener('auth:unauthorized', handleUnauthorized)
  window.addEventListener('api:forbidden', handleForbidden)
})

onUnmounted(() => {
  window.removeEventListener('auth:unauthorized', handleUnauthorized)
  window.removeEventListener('api:forbidden', handleForbidden)
})
</script>

<template>
  <div class="app-shell">
    <AppHeader />
    <main class="app-content">
      <RouterView />
    </main>
  </div>
  <ToastContainer />
</template>
