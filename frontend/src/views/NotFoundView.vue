<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <section class="not-found">
    <h1>Nie znaleziono strony</h1>
    <p>Podany adres nie prowadzi do żadnej strony.</p>
    <RouterLink to="/">Wróć na stronę główną</RouterLink>
  </section>
</template>

<style scoped>
.not-found {
  max-width: 640px;
  padding: 2rem 0;
}

h1 {
  margin-bottom: 0.75rem;
}

p {
  margin-bottom: 1rem;
}

a {
  color: #386a54;
  text-decoration: underline;
}
</style>
