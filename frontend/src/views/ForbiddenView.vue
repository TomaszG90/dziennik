<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <section class="forbidden">
    <h1>Brak dostępu</h1>
    <p>Nie masz uprawnień, aby wyświetlić tę stronę.</p>
    <RouterLink to="/">Wróć na stronę główną</RouterLink>
  </section>
</template>

<style scoped>
.forbidden {
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
