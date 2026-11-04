<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    isOpen: boolean
    title: string
    message: string
    confirmLabel?: string
    cancelLabel?: string
    isLoading?: boolean
    variant?: 'danger' | 'primary'
    disableTeleport?: boolean
  }>(),
  {
    confirmLabel: 'Usuń',
    cancelLabel: 'Anuluj',
    isLoading: false,
    variant: 'danger',
    disableTeleport: false,
  },
)

const emit = defineEmits<{
  (e: 'confirm'): void
  (e: 'cancel'): void
  (e: 'close'): void
}>()

const shouldDisableTeleport = computed(() => {
  if (props.disableTeleport) return true
  if (typeof process !== 'undefined' && (process.env?.VITEST || process.env?.NODE_ENV === 'test')) {
    return true
  }
  return false
})

function handleKeyDown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.isOpen && !props.isLoading) {
    emit('cancel')
    emit('close')
  }
}

function handleBackdropClick() {
  if (!props.isLoading) {
    emit('cancel')
    emit('close')
  }
}

function handleConfirm() {
  if (!props.isLoading) {
    emit('confirm')
  }
}

function handleCancel() {
  if (!props.isLoading) {
    emit('cancel')
    emit('close')
  }
}

watch(
  () => props.isOpen,
  (open) => {
    if (typeof document !== 'undefined') {
      if (open) {
        document.body.style.overflow = 'hidden'
      } else {
        document.body.style.overflow = ''
      }
    }
  },
)

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeyDown)
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleKeyDown)
  }
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <Teleport to="body" :disabled="shouldDisableTeleport">
    <Transition name="modal-fade">
      <div
        v-if="isOpen"
        class="modal-backdrop"
        data-testid="modal-backdrop"
        @click.self="handleBackdropClick"
      >
        <div
          class="modal-dialog"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'modal-title'"
          :aria-describedby="'modal-desc'"
          data-testid="confirm-modal-dialog"
        >
          <div class="modal-icon-row">
            <div
              class="modal-icon"
              :class="{
                'icon-danger': variant === 'danger',
                'icon-primary': variant === 'primary',
              }"
              aria-hidden="true"
            >
              <svg
                v-if="variant === 'danger'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                <line x1="10" y1="11" x2="10" y2="17" />
                <line x1="14" y1="11" x2="14" y2="17" />
              </svg>
              <svg
                v-else
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
            </div>

            <h3 id="modal-title" class="modal-title">{{ title }}</h3>
          </div>

          <p id="modal-desc" class="modal-message">{{ message }}</p>

          <div class="modal-actions">
            <button
              type="button"
              class="btn-cancel"
              :disabled="isLoading"
              data-testid="modal-cancel-button"
              @click="handleCancel"
            >
              {{ cancelLabel }}
            </button>
            <button
              type="button"
              class="btn-confirm"
              :class="{
                'btn-danger': variant === 'danger',
                'btn-primary': variant === 'primary',
              }"
              :disabled="isLoading"
              data-testid="modal-confirm-button"
              @click="handleConfirm"
            >
              <span v-if="isLoading" class="spinner-small" aria-hidden="true"></span>
              <span>{{ confirmLabel }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 1rem;
  background-color: rgba(17, 24, 39, 0.6);
  backdrop-filter: blur(2px);
}

.modal-dialog {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  width: 100%;
  max-width: 28rem;
  padding: 1.75rem;
  border-radius: 1.25rem;
  background: #ffffff;
  color: #111827;
  box-shadow:
    0 20px 25px -5px rgba(0, 0, 0, 0.15),
    0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-icon-row {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.modal-icon {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 50%;
  flex-shrink: 0;
}

.modal-icon svg {
  width: 1.5rem;
  height: 1.5rem;
}

.icon-danger {
  background: #fee2e2;
  color: #dc2626;
}

.icon-primary {
  background: #e0f2fe;
  color: #0284c7;
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
  line-height: 1.3;
}

.modal-message {
  font-size: 0.95rem;
  color: #4b5563;
  line-height: 1.5;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.btn-cancel,
.btn-confirm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 2.5rem;
  padding: 0.5rem 1.125rem;
  border-radius: 0.5rem;
  font: inherit;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 150ms ease;
}

.btn-cancel {
  border: 1px solid #d1d5db;
  color: #374151;
  background: #ffffff;
}

.btn-cancel:hover:not(:disabled) {
  background: #f3f4f6;
  border-color: #9ca3af;
}

.btn-danger {
  border: 1px solid #dc2626;
  color: #ffffff;
  background: #dc2626;
}

.btn-danger:hover:not(:disabled) {
  background: #b91c1c;
  border-color: #b91c1c;
}

.btn-primary {
  border: 1px solid #386a54;
  color: #ffffff;
  background: #386a54;
}

.btn-primary:hover:not(:disabled) {
  background: #294d3d;
}

.btn-cancel:disabled,
.btn-confirm:disabled {
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

/* Animations */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 200ms ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-active .modal-dialog {
  transition: transform 200ms ease;
}

.modal-fade-enter-from .modal-dialog {
  transform: scale(0.95);
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
  .modal-dialog {
    background: #1f2937;
    color: #f3f4f6;
    border: 1px solid #374151;
  }

  .modal-title {
    color: #f3f4f6;
  }

  .modal-message {
    color: #9ca3af;
  }

  .btn-cancel {
    border-color: #4b5563;
    color: #d1d5db;
    background: #374151;
  }

  .btn-cancel:hover:not(:disabled) {
    background: #4b5563;
  }

  .icon-danger {
    background: rgba(220, 38, 38, 0.2);
    color: #f87171;
  }
}
</style>
