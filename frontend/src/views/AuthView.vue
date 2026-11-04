<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, registerUser, requestPasswordReset, resetPassword } from '@/api'
import type { RegisterPayload } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'

type AuthMode = 'login' | 'register' | 'forgot' | 'reset'
type RegisterField = keyof RegisterPayload

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const mode = computed<AuthMode>(() => {
  if (typeof route.query.token === 'string') return 'reset'
  if (route.query.mode === 'register' || route.query.mode === 'forgot') {
    return route.query.mode
  }
  return 'login'
})

const loginEmail = ref('')
const loginPassword = ref('')
const registerForm = reactive<RegisterPayload>({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  profile_visibility: 'private',
})
const resetEmail = ref(typeof route.query.email === 'string' ? route.query.email : '')
const resetPasswordValue = ref('')
const resetPasswordConfirmation = ref('')
const emailInput = ref<HTMLInputElement | null>(null)
const formError = ref('')
const statusMessage = ref('')
const loginErrors = ref<{ email: string[]; password: string[] }>({
  email: [],
  password: [],
})
const registerErrors = ref<Partial<Record<RegisterField, string[]>>>({})
const resetErrors = ref<{ email: string[]; password: string[] }>({
  email: [],
  password: [],
})
const isSubmitting = ref(false)

watch(mode, () => {
  formError.value = ''
  statusMessage.value = ''
  loginErrors.value = { email: [], password: [] }
  registerErrors.value = {}
  resetErrors.value = { email: [], password: [] }
})

async function setMode(nextMode: 'login' | 'register' | 'forgot') {
  await router.replace({
    name: 'auth',
    query: {
      ...(typeof route.query.redirect === 'string'
        ? { redirect: route.query.redirect }
        : {}),
      ...(nextMode === 'login' ? {} : { mode: nextMode }),
    },
  })
}

async function submitLogin() {
  formError.value = ''
  statusMessage.value = ''
  loginErrors.value = { email: [], password: [] }

  if (!emailInput.value?.validity.valid) {
    loginErrors.value.email = [
      emailInput.value?.validity.valueMissing
        ? 'Podaj adres e-mail.'
        : 'Podaj prawidłowy adres e-mail.',
    ]
    return
  }

  if (!loginPassword.value) {
    loginErrors.value.password = ['Podaj hasło.']
    return
  }

  try {
    await auth.login({ email: loginEmail.value, password: loginPassword.value })
    const redirect = route.query.redirect
    await router.replace(typeof redirect === 'string' ? redirect : '/')
  } catch (error) {
    if (error instanceof ApiError) {
      loginErrors.value.email = error.validationErrors?.email ?? []
      loginErrors.value.password = error.validationErrors?.password ?? []

      if (!error.isValidationError) {
        formError.value = 'Nie udało się zalogować. Sprawdź e-mail i hasło.'
      } else if (!loginErrors.value.email.length && !loginErrors.value.password.length) {
        formError.value = error.message
      }
    } else {
      formError.value = 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie.'
    }
  }
}

async function submitRegister() {
  formError.value = ''
  statusMessage.value = ''
  registerErrors.value = {}

  if (!emailInput.value?.validity.valid) {
    registerErrors.value.email = [
      emailInput.value?.validity.valueMissing
        ? 'Podaj adres e-mail.'
        : 'Podaj prawidłowy adres e-mail.',
    ]
    return
  }

  if (registerForm.password.length < 8) {
    registerErrors.value.password = ['Hasło musi mieć co najmniej 8 znaków.']
    return
  }

  if (registerForm.password !== registerForm.password_confirmation) {
    registerErrors.value.password_confirmation = ['Hasła muszą być takie same.']
    return
  }

  isSubmitting.value = true
  try {
    await registerUser({ ...registerForm })
    await setMode('login')
    statusMessage.value = 'Konto zostało utworzone. Możesz się teraz zalogować.'
  } catch (error) {
    if (error instanceof ApiError) {
      registerErrors.value = error.validationErrors ?? {}
      if (!Object.keys(registerErrors.value).length) formError.value = error.message
    } else {
      formError.value = 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie.'
    }
  } finally {
    isSubmitting.value = false
  }
}

async function submitForgotPassword() {
  formError.value = ''
  statusMessage.value = ''
  resetErrors.value = { email: [], password: [] }

  if (!emailInput.value?.validity.valid) {
    resetErrors.value.email = [
      emailInput.value?.validity.valueMissing
        ? 'Podaj adres e-mail.'
        : 'Podaj prawidłowy adres e-mail.',
    ]
    return
  }

  isSubmitting.value = true
  try {
    await requestPasswordReset({ email: resetEmail.value })
    statusMessage.value =
      'Jeśli konto z tym adresem istnieje, wyślemy na niego link do zmiany hasła.'
  } catch (error) {
    if (error instanceof ApiError) {
      resetErrors.value.email = error.validationErrors?.email ?? []
      if (!resetErrors.value.email.length) formError.value = error.message
    } else {
      formError.value = 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie.'
    }
  } finally {
    isSubmitting.value = false
  }
}

async function submitResetPassword() {
  formError.value = ''
  statusMessage.value = ''
  resetErrors.value = { email: [], password: [] }

  if (resetPasswordValue.value.length < 8) {
    resetErrors.value.password = ['Hasło musi mieć co najmniej 8 znaków.']
    return
  }
  if (resetPasswordValue.value !== resetPasswordConfirmation.value) {
    resetErrors.value.password = ['Hasła muszą być takie same.']
    return
  }

  isSubmitting.value = true
  try {
    await resetPassword({
      email: resetEmail.value,
      token: typeof route.query.token === 'string' ? route.query.token : '',
      password: resetPasswordValue.value,
      password_confirmation: resetPasswordConfirmation.value,
    })
    await setMode('login')
    statusMessage.value = 'Hasło zostało zmienione. Możesz się zalogować.'
  } catch (error) {
    if (error instanceof ApiError) {
      resetErrors.value.email = error.validationErrors?.email ?? []
      resetErrors.value.password = error.validationErrors?.password ?? []
      if (!resetErrors.value.email.length && !resetErrors.value.password.length) {
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
  <main class="auth-page">
    <section class="auth-card" aria-labelledby="auth-title">
      <h1 id="auth-title">
        {{ mode === 'register' ? 'Utwórz konto' : mode === 'reset' ? 'Ustaw nowe hasło' : mode === 'forgot' ? 'Odzyskaj hasło' : 'Zaloguj się' }}
      </h1>
      <p class="intro">
        {{
          mode === 'register'
            ? 'Załóż konto i zacznij zapisywać swoje podróże.'
            : mode === 'reset'
              ? 'Wybierz nowe hasło do swojego konta.'
              : mode === 'forgot'
                ? 'Podaj adres e-mail powiązany z kontem.'
                : 'Wróć do swojego dziennika podróży.'
        }}
      </p>

      <div v-if="mode !== 'reset'" class="mode-tabs" role="tablist" aria-label="Opcje konta">
        <button
          id="login-tab"
          type="button"
          role="tab"
          :aria-selected="mode === 'login'"
          aria-controls="auth-panel"
          @click="setMode('login')"
        >
          Logowanie
        </button>
        <button
          id="register-tab"
          type="button"
          role="tab"
          :aria-selected="mode === 'register'"
          aria-controls="auth-panel"
          @click="setMode('register')"
        >
          Rejestracja
        </button>
        <button
          id="forgot-tab"
          type="button"
          role="tab"
          :aria-selected="mode === 'forgot'"
          aria-controls="auth-panel"
          @click="setMode('forgot')"
        >
          Reset hasła
        </button>
      </div>

      <div
        id="auth-panel"
        role="tabpanel"
        :aria-labelledby="mode === 'reset' ? 'auth-title' : `${mode}-tab`"
      >
        <form v-if="mode === 'login'" @submit.prevent="submitLogin">
          <div class="field">
            <label for="email">E-mail</label>
            <input
              id="email"
              ref="emailInput"
              v-model.trim="loginEmail"
              type="email"
              autocomplete="email"
              required
              :aria-invalid="loginErrors.email.length > 0"
              aria-describedby="email-error"
            />
            <p v-if="loginErrors.email.length" id="email-error" class="field-error">
              {{ loginErrors.email[0] }}
            </p>
          </div>

          <div class="field">
            <label for="password">Hasło</label>
            <input
              id="password"
              v-model="loginPassword"
              type="password"
              autocomplete="current-password"
              required
              :aria-invalid="loginErrors.password.length > 0"
              aria-describedby="password-error"
            />
            <p v-if="loginErrors.password.length" id="password-error" class="field-error">
              {{ loginErrors.password[0] }}
            </p>
          </div>

          <button type="submit" :disabled="auth.isLoading">
            {{ auth.isLoading ? 'Logowanie...' : 'Zaloguj się' }}
          </button>
        </form>

        <form v-else-if="mode === 'register'" @submit.prevent="submitRegister">
          <div class="field">
            <label for="name">Imię i nazwisko</label>
            <input
              id="name"
              v-model.trim="registerForm.name"
              type="text"
              autocomplete="name"
              required
              maxlength="255"
              :aria-invalid="Boolean(registerErrors.name?.length)"
              :aria-describedby="registerErrors.name?.length ? 'name-error' : undefined"
            />
            <p v-if="registerErrors.name?.length" id="name-error" class="field-error">
              {{ registerErrors.name[0] }}
            </p>
          </div>

          <div class="field">
            <label for="email">E-mail</label>
            <input
              id="email"
              ref="emailInput"
              v-model.trim="registerForm.email"
              type="email"
              autocomplete="email"
              required
              maxlength="255"
              :aria-invalid="Boolean(registerErrors.email?.length)"
              :aria-describedby="registerErrors.email?.length ? 'email-error' : undefined"
            />
            <p v-if="registerErrors.email?.length" id="email-error" class="field-error">
              {{ registerErrors.email[0] }}
            </p>
          </div>

          <div class="field">
            <label for="password">Hasło</label>
            <input
              id="password"
              v-model="registerForm.password"
              type="password"
              autocomplete="new-password"
              required
              minlength="8"
              :aria-invalid="Boolean(registerErrors.password?.length)"
              :aria-describedby="registerErrors.password?.length ? 'password-error' : undefined"
            />
            <p v-if="registerErrors.password?.length" id="password-error" class="field-error">
              {{ registerErrors.password[0] }}
            </p>
          </div>

          <div class="field">
            <label for="password_confirmation">Powtórz hasło</label>
            <input
              id="password_confirmation"
              v-model="registerForm.password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              :aria-invalid="Boolean(registerErrors.password_confirmation?.length)"
              :aria-describedby="
                registerErrors.password_confirmation?.length
                  ? 'password-confirmation-error'
                  : undefined
              "
            />
            <p
              v-if="registerErrors.password_confirmation?.length"
              id="password-confirmation-error"
              class="field-error"
            >
              {{ registerErrors.password_confirmation[0] }}
            </p>
          </div>

          <button type="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Tworzenie konta...' : 'Utwórz konto' }}
          </button>
        </form>

        <form v-else-if="mode === 'forgot'" @submit.prevent="submitForgotPassword">
          <div class="field">
            <label for="email">E-mail</label>
            <input
              id="email"
              ref="emailInput"
              v-model.trim="resetEmail"
              type="email"
              autocomplete="email"
              required
              :aria-invalid="resetErrors.email.length > 0"
              aria-describedby="email-error"
            />
            <p v-if="resetErrors.email.length" id="email-error" class="field-error">
              {{ resetErrors.email[0] }}
            </p>
          </div>
          <button type="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Wysyłanie...' : 'Wyślij link do resetu' }}
          </button>
        </form>

        <form v-else @submit.prevent="submitResetPassword">
          <div class="field">
            <label for="email">E-mail</label>
            <input
              id="email"
              v-model.trim="resetEmail"
              type="email"
              autocomplete="email"
              required
              :aria-invalid="resetErrors.email.length > 0"
              aria-describedby="reset-email-error"
            />
            <p v-if="resetErrors.email.length" id="reset-email-error" class="field-error">
              {{ resetErrors.email[0] }}
            </p>
          </div>
          <div class="field">
            <label for="new-password">Nowe hasło</label>
            <input
              id="new-password"
              v-model="resetPasswordValue"
              type="password"
              autocomplete="new-password"
              required
              minlength="8"
              :aria-invalid="resetErrors.password.length > 0"
              aria-describedby="reset-password-error"
            />
          </div>
          <div class="field">
            <label for="new-password-confirmation">Powtórz nowe hasło</label>
            <input
              id="new-password-confirmation"
              v-model="resetPasswordConfirmation"
              type="password"
              autocomplete="new-password"
              required
              minlength="8"
              :aria-invalid="resetErrors.password.length > 0"
              aria-describedby="reset-password-error"
            />
            <p
              v-if="resetErrors.password.length"
              id="reset-password-error"
              class="field-error"
            >
              {{ resetErrors.password[0] }}
            </p>
          </div>
          <button type="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Zapisywanie...' : 'Zmień hasło' }}
          </button>
        </form>
      </div>

      <p v-if="formError" class="form-error" role="alert">{{ formError }}</p>
      <p v-if="statusMessage" class="status-message" role="status">{{ statusMessage }}</p>
    </section>
  </main>
</template>

<style scoped>
.auth-page {
  display: grid;
  place-items: center;
  padding: 3rem 1rem;
}

.auth-card {
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

.intro {
  margin-bottom: 1.5rem;
  color: #4b5563;
}

.mode-tabs {
  display: flex;
  width: 100%;
  margin-bottom: 1.5rem;
}

.mode-tabs button {
  flex: 1 1 0;
  min-width: 0;
  min-height: 2.5rem;
  padding: 0.5rem 0.25rem;
  border: 1px solid #386a54;
  color: #294d3d;
  background: transparent;
  font: inherit;
}

.mode-tabs button:first-child {
  border-radius: 0.5rem 0 0 0.5rem;
}

.mode-tabs button:last-child {
  border-radius: 0 0.5rem 0.5rem 0;
}

.mode-tabs button[aria-selected='true'] {
  color: white;
  background: #386a54;
}

.mode-tabs button + button {
  border-left: 0;
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

form > button {
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

.status-message {
  color: #294d3d;
}

@media (prefers-color-scheme: dark) {
  .auth-card {
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    color: #f3f4f6;
  }

  .intro {
    color: #9ca3af;
  }

  .mode-tabs button {
    color: #6ee7b7;
    border-color: #6ee7b7;
  }

  .mode-tabs button[aria-selected='true'] {
    color: #111827;
    background: #6ee7b7;
  }

  input {
    color: #f3f4f6;
    background: #1f2937;
    border-color: #4b5563;
  }

  .field-error,
  .form-error {
    color: #fca5a5;
  }

  .status-message {
    color: #6ee7b7;
  }
}
</style>
