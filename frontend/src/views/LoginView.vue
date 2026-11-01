<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const formError = ref('')
const emailErrors = ref<string[]>([])
const passwordErrors = ref<string[]>([])

async function submitLogin() {
  formError.value = ''
  emailErrors.value = []
  passwordErrors.value = []

  try {
    await auth.login({ email: email.value, password: password.value })

    const redirect = route.query.redirect
    await router.replace(typeof redirect === 'string' ? redirect : '/dashboard')
  } catch (error) {
    if (error instanceof ApiError) {
      emailErrors.value = error.validationErrors?.email ?? []
      passwordErrors.value = error.validationErrors?.password ?? []

      if (!error.isValidationError) {
        formError.value = 'Nie udało się zalogować. Sprawdź e-mail i hasło.'
      } else if (!emailErrors.value.length && !passwordErrors.value.length) {
        formError.value = error.message
      }
    } else {
      formError.value = 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie.'
    }
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-card" aria-labelledby="login-title">
      <h1 id="login-title">Zaloguj się</h1>
      <p class="intro">Wróć do swojego dziennika podróży.</p>

      <form @submit.prevent="submitLogin">
        <div class="field">
          <label for="email">E-mail</label>
          <input
            id="email"
            v-model.trim="email"
            type="email"
            autocomplete="email"
            required
            :aria-invalid="emailErrors.length > 0"
            aria-describedby="email-error"
          />
          <p v-if="emailErrors.length" id="email-error" class="field-error">
            {{ emailErrors[0] }}
          </p>
        </div>

        <div class="field">
          <label for="password">Hasło</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            :aria-invalid="passwordErrors.length > 0"
            aria-describedby="password-error"
          />
          <p v-if="passwordErrors.length" id="password-error" class="field-error">
            {{ passwordErrors[0] }}
          </p>
        </div>

        <p v-if="formError" class="form-error" role="alert">
          {{ formError }}
        </p>

        <button type="submit" :disabled="auth.isLoading">
          {{ auth.isLoading ? 'Logowanie...' : 'Zaloguj się' }}
        </button>
      </form>

      <p class="register-link">
        Nie masz konta?
        <RouterLink to="/register">Zarejestruj się</RouterLink>
      </p>
    </section>
  </main>
</template>

<style scoped>
.login-page {
  display: grid;
  place-items: center;
  padding: 3rem 1rem;
}

.login-card {
  width: min(100%, 28rem);
  padding: clamp(2rem, 7vw, 5rem);
  border: 0;
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

.register-link {
  margin-top: 1.5rem;
  color: inherit;
}

/* ── Dark mode ── */
@media (prefers-color-scheme: dark) {
  .login-card {
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    color: #f3f4f6;
  }

  .intro {
    color: #9ca3af;
  }

  input {
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

  .register-link a {
    color: #6ee7b7;
  }
}
</style>
