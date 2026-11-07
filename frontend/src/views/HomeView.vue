<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

defineProps<{
  qrCodeUrl?: string | null
}>()

const auth = useAuthStore()
</script>

<template>
  <div class="home-page">
    <div class="hero-layout">
      <section class="hero">
        <p class="eyebrow">Twój dziennik podróży</p>
        <h1>Wspomnienia z drogi zasługują na swoje miejsce.</h1>

        <p v-if="auth.isAuthenticated" class="intro">
          Witaj ponownie, {{ auth.user?.name }}. Zacznij planować kolejną przygodę.
        </p>
        <p v-else class="intro">
          Zapisuj odwiedzone miejsca, układaj trasy i wracaj do swoich podróżniczych
          wspomnień.
        </p>

        <div v-if="!auth.isAuthenticated" class="actions">
          <RouterLink class="button button-primary" :to="{ name: 'auth', query: { mode: 'register' } }">
            Załóż konto
          </RouterLink>
          <RouterLink class="button button-secondary" :to="{ name: 'auth' }">
            Zaloguj się
          </RouterLink>
        </div>
      </section>

      <aside class="mobile-app" aria-labelledby="mobile-app-title">
        <p class="eyebrow">Wkrótce</p>
        <h2 id="mobile-app-title">Aplikacja mobilna w budowie</h2>
        <p>Pracujemy nad tym, aby Twój dziennik podróży był zawsze pod ręką.</p>
        <img
          v-if="qrCodeUrl"
          class="qr-code"
          :src="qrCodeUrl"
          alt="Kod QR do pobrania aplikacji mobilnej"
        />
        <div v-else class="qr-placeholder" aria-label="Miejsce na kod QR">
          <span>Miejsce na kod QR</span>
        </div>
        <p v-if="qrCodeUrl" class="qr-caption">Zeskanuj kod, aby pobrać aplikację.</p>
        <p v-else class="qr-caption">Kod QR do pobrania aplikacji pojawi się tutaj.</p>
      </aside>
    </div>

    <section class="features" aria-label="Możliwości dziennika">
      <article class="feature-card">
        <h2>Zapisuj miejsca</h2>
        <p>Zbieraj punkty swojej podróży w jednym dzienniku.</p>
      </article>

      <article class="feature-card">
        <h2>Układaj trasy</h2>
        <p>Porządkuj kolejne etapy i środki transportu.</p>
      </article>

      <article class="feature-card">
        <h2>Wracaj do wspomnień</h2>
        <p>Przeglądaj swoje podróże i odkrywaj je na nowo.</p>
      </article>
    </section>
  </div>
</template>

<style scoped>
.home-page {
  display: grid;
  gap: 3rem;
  padding: 2rem 0 4rem;
}

.hero-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(16rem, 0.38fr);
  gap: 1.5rem;
}

.hero {
  max-width: none;
  padding: clamp(2rem, 7vw, 5rem);
  border-radius: 1.5rem;
  background: linear-gradient(135deg, #e9f5ef, #f5f2e9);
  color: #111827;
}

.mobile-app {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  padding: clamp(1.5rem, 4vw, 2.5rem);
  border: 1px solid var(--color-border);
  border-radius: 1.5rem;
  background: var(--color-background-soft);
}

.mobile-app h2 {
  font-size: 1.5rem;
  line-height: 1.2;
  font-weight: 700;
}

.mobile-app > p:not(.eyebrow, .qr-caption) {
  margin-top: 0.75rem;
  color: #4b5563;
}

.qr-placeholder {
  display: grid;
  place-items: center;
  width: min(100%, 10rem);
  aspect-ratio: 1;
  margin-top: 1.5rem;
  border: 2px dashed var(--color-border-hover);
  border-radius: 0.75rem;
  color: var(--color-text);
  text-align: center;
  font-size: 0.875rem;
}

.qr-code {
  display: block;
  width: min(100%, 10rem);
  aspect-ratio: 1;
  margin-top: 1.5rem;
  border-radius: 0.75rem;
  object-fit: contain;
  background: white;
}

.qr-caption {
  margin-top: 0.75rem;
  color: #6b7280;
  font-size: 0.875rem;
}

.eyebrow {
  margin-bottom: 0.75rem;
  color: #386a54;
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

h1 {
  max-width: 15ch;
  font-size: clamp(2.25rem, 6vw, 4rem);
  line-height: 1.1;
  color: inherit;
}

.intro {
  max-width: 55ch;
  margin-top: 1.25rem;
  color: #4b5563;
  font-size: 1.125rem;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 2rem;
}

.button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  padding: 0.65rem 1.1rem;
  border: 1px solid #386a54;
  border-radius: 0.5rem;
  font-weight: 600;
}

.button-primary {
  color: white;
  background: #386a54;
}

.button-secondary {
  color: #294d3d;
}

.features {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
  gap: 1rem;
}

.feature-card {
  padding: 1.5rem;
  border: 1px solid var(--color-border);
  border-radius: 1rem;
}

.feature-card h2 {
  margin-bottom: 0.5rem;
  font-size: 1.125rem;
  font-weight: 700;
  color: inherit;
}

.feature-card p {
  color: #4b5563;
}

@media (max-width: 760px) {
  .hero-layout {
    grid-template-columns: 1fr;
  }

  .mobile-app {
    align-items: center;
    text-align: center;
  }
}

/* ── Dark mode ── */
@media (prefers-color-scheme: dark) {
  .hero {
    background: linear-gradient(135deg, #1a2e24, #2a2618);
    color: #f3f4f6;
  }

  .eyebrow {
    color: #6ee7b7;
  }

  .intro {
    color: #9ca3af;
  }

  .mobile-app {
    color: #f3f4f6;
  }

  .mobile-app > p:not(.eyebrow, .qr-caption),
  .qr-caption {
    color: #9ca3af;
  }

  .button-secondary {
    color: #6ee7b7;
    border-color: #6ee7b7;
  }

  .feature-card {
    color: #f3f4f6;
  }

  .feature-card p {
    color: #9ca3af;
  }
}
</style>
