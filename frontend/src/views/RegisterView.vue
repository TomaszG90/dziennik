<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ApiError, registerUser } from '@/api'
import type { RegisterPayload } from '@/api/auth'

type RegisterField = keyof RegisterPayload

const router = useRouter()
const isSubmitting = ref(false)
const formError = ref('')
const fieldErrors = ref<Partial<Record<RegisterField, string[]>>>({})

const form = reactive<RegisterPayload>({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  profile_visibility: 'private',
})

async function submitRegister() {
  formError.value = ''
  fieldErrors.value = {}

  if (form.password !== form.password_confirmation) {
    fieldErrors.value = {
      password_confirmation: ['Hasła muszą być takie same.'],
    }
    return
  }

  isSubmitting.value = true

  try {
    await registerUser({ ...form })
    await router.replace({ name: 'login' })
  } catch (error) {
    if (error instanceof ApiError) {
      fieldErrors.value = error.validationErrors ?? {}
      if (!Object.keys(fieldErrors.value).length) {
        formError.value = error.message
      }
    } else {
      formError.value = 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie.'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="register-page">
    <section class="register-card" aria-labelledby="register-title">
      <h1 id="register-title">Utwórz konto</h1>
      <p class="intro">Załóż konto i zacznij zapisywać swoje podróże.</p>

      <form @submit.prevent="submitRegister">
        <div class="field">
          <label for="name">Imię i nazwisko</label>
          <input
            id="name"
            v-model.trim="form.name"
            type="text"
            autocomplete="name"
            required
            maxlength="255"
            :aria-invalid="Boolean(fieldErrors.name?.length)"
            :aria-describedby="fieldErrors.name?.length ? 'name-error' : undefined"
          />
          <p v-if="fieldErrors.name?.length" id="name-error" class="field-error">
            {{ fieldErrors.name[0] }}
          </p>
        </div>

        <div class="field">
          <label for="email">E-mail</label>
          <input
            id="email"
            v-model.trim="form.email"
            type="email"
            autocomplete="email"
            required
            maxlength="255"
            :aria-invalid="Boolean(fieldErrors.email?.length)"
            :aria-describedby="fieldErrors.email?.length ? 'email-error' : undefined"
          />
          <p v-if="fieldErrors.email?.length" id="email-error" class="field-error">
            {{ fieldErrors.email[0] }}
          </p>
        </div>

        <div class="field">
          <label for="password">Hasło</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required
            :aria-invalid="Boolean(fieldErrors.password?.length)"
            :aria-describedby="fieldErrors.password?.length ? 'password-error' : undefined"
          />
          <p
            v-if="fieldErrors.password?.length"
            id="password-error"
            class="field-error"
          >
            {{ fieldErrors.password[0] }}
          </p>
        </div>

        <div class="field">
          <label for="password_confirmation">Powtórz hasło</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required
            :aria-invalid="Boolean(fieldErrors.password_confirmation?.length)"
            :aria-describedby="
              fieldErrors.password_confirmation?.length
                ? 'password-confirmation-error'
                : undefined
            "
          />
          <p
            v-if="fieldErrors.password_confirmation?.length"
            id="password-confirmation-error"
            class="field-error"
          >
            {{ fieldErrors.password_confirmation[0] }}
          </p>
        </div>

        <p v-if="formError" class="form-error" role="alert">
          {{ formError }}
        </p>

        <button type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Tworzenie konta…' : 'Utwórz konto' }}
        </button>
      </form>

      <p class="login-link">
        Masz już konto?
        <RouterLink to="/login">Zaloguj się</RouterLink>
      </p>
    </section>
  </main>
</template>

<style scoped>
.register-page {
  display: grid;
  place-items: center;
  padding: 3rem 1rem;
}

.register-card {
  width: min(100%, 34rem);
  padding: clamp(2rem, 7vw, 5rem);
  border-radius: 1.5rem;
  background: linear-gradient(135deg, #e9f5ef, #f5f2e9);
  color: #111827;
}

h1 {
  margin-bottom: 0.5rem;
  font-size: 2rem;
  color: inherit;
}

label {
  color: inherit;
}

.intro {
  margin-bottom: 1.5rem;
  color: #4b5563;
}

form,
.field {
  display: grid;
  gap: 0.5rem;
}

form {
  gap: 1.25rem;
}

input:not([type='radio']) {
  width: 100%;
  min-height: 2.75rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid #9ca3af;
  border-radius: 0.5rem;
  font: inherit;
  color: #111827;
  background: #ffffff;
}

.visibility-field {
  display: grid;
  gap: 0.75rem;
  padding: 0;
  border: 0;
}

.visibility-field legend {
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.radio-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

button {
  min-height: 2.75rem;
  border: 0;
  border-radius: 0.5rem;
  color: white;
  background: #386a54;
  font: inherit;
  font-weight: 600;
}

button:disabled {
  cursor: wait;
  opacity: 0.7;
}

.field-error,
.form-error {
  color: #b42318;
}

.login-link {
  margin-top: 1.5rem;
  color: inherit;
}

/* ── Dark mode ── */
@media (prefers-color-scheme: dark) {
  .register-card {
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    color: #f3f4f6;
  }

  .intro {
    color: #9ca3af;
  }

  input:not([type='radio']) {
    color: #f3f4f6;
    background: #1f2937;
    border-color: #4b5563;
  }

  input::placeholder {
    color: #6b7280;
  }

  .field-error,
  .form-error {
    color: #fca5a5;
  }

  .login-link a {
    color: #6ee7b7;
  }
}
</style>
