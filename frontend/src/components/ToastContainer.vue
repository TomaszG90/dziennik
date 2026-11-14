<script setup lang="ts">
import { dismissToast, toasts } from '@/services/toast'
</script>

<template>
  <div class="toast-container" aria-label="Powiadomienia">
    <div v-for="toast in toasts" :key="toast.id" class="toast" role="alert">
      <p>{{ toast.message }}</p>
      <button type="button" aria-label="Zamknij komunikat" @click="dismissToast(toast.id)">
        ×
      </button>
    </div>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  z-index: 1000;
  top: 1rem;
  right: 1rem;
  display: grid;
  width: min(24rem, calc(100vw - 2rem));
  gap: 0.75rem;
}

.toast {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem;
  border-left: 4px solid #b42318;
  border-radius: 0.375rem;
  background: #fff;
  box-shadow: 0 4px 16px rgb(0 0 0 / 18%);
  color: #7a271a;
}

.toast p {
  margin: 0;
}

.toast button {
  flex: none;
  border: 0;
  padding: 0 0.25rem;
  background: transparent;
  color: inherit;
  font-size: 1.25rem;
  line-height: 1;
}
</style>
