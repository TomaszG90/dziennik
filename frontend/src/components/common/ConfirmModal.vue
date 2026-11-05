<script setup lang="ts">
withDefaults(defineProps<{
  isOpen: boolean
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  isLoading?: boolean
  variant?: 'default' | 'danger'
}>(), {
  confirmLabel: 'Potwierdź',
  cancelLabel: 'Anuluj',
  isLoading: false,
  variant: 'default',
})

const emit = defineEmits<{
  confirm: []
  cancel: []
  close: []
}>()
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal-backdrop" @click.self="emit('close')">
      <section
        class="modal"
        :class="{ 'modal--danger': variant === 'danger' }"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
      >
        <h2 class="modal-title">{{ title }}</h2>
        <p class="modal-message">{{ message }}</p>
        <div class="modal-actions">
          <button
            type="button"
            class="button button--secondary"
            :disabled="isLoading"
            data-testid="modal-cancel-button"
            @click="emit('cancel')"
          >
            {{ cancelLabel }}
          </button>
          <button
            type="button"
            class="button button--confirm"
            :disabled="isLoading"
            data-testid="modal-confirm-button"
            @click="emit('confirm')"
          >
            <span v-if="isLoading" class="spinner" aria-hidden="true"></span>
            {{ confirmLabel }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  z-index: 1000;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(15 23 42 / 60%);
}

.modal {
  width: min(100%, 28rem);
  padding: 1.5rem;
  border-radius: 1rem;
  background: #fff;
  color: #1f2937;
  box-shadow: 0 20px 50px rgb(0 0 0 / 25%);
}

.modal-title {
  margin: 0 0 0.75rem;
  font-size: 1.25rem;
}

.modal-message {
  margin: 0;
  line-height: 1.5;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

.button {
  min-height: 2.5rem;
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: 0.5rem;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.button:disabled {
  cursor: wait;
  opacity: 0.6;
}

.button--secondary {
  border-color: #d1d5db;
  background: #fff;
  color: #374151;
}

.button--confirm {
  background: #386a54;
  color: #fff;
}

.modal--danger .button--confirm {
  background: #b91c1c;
}

.spinner {
  display: inline-block;
  width: 0.85rem;
  height: 0.85rem;
  margin-right: 0.4rem;
  border: 2px solid rgb(255 255 255 / 50%);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
