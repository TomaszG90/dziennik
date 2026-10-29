<script setup lang="ts">
defineProps<{
  title: string
  message: string
}>()
</script>

<template>
  <section class="placeholder">
    <p class="eyebrow">W przygotowaniu</p>
    <h1>{{ title }}</h1>
    <p>{{ message }}</p>
  </section>
</template>

<style scoped>
.placeholder {
  max-width: 640px;
  padding: 2rem 0;
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
  margin-bottom: 0.75rem;
  font-size: clamp(2rem, 5vw, 3rem);
}

.placeholder > p:last-child {
  color: #4b5563;
}
</style>
