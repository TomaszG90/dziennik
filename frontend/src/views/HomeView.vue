<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="home-page">
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
        <RouterLink class="button button-primary" to="/register">
          Załóż konto
        </RouterLink>
        <RouterLink class="button button-secondary" to="/login">
          Zaloguj się
        </RouterLink>
      </div>
    </section>

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

.hero {
  max-width: 720px;
  padding: clamp(2rem, 7vw, 5rem);
  border-radius: 1.5rem;
  background: linear-gradient(135deg, #e9f5ef, #f5f2e9);
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
}

.feature-card p {
  color: #4b5563;
}
</style>
