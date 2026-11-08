<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = withDefaults(
  defineProps<{
    center?: [number, number]
    zoom?: number
    tileUrl?: string
    attribution?: string
  }>(),
  {
    center: () => [52.1, 19.4],
    zoom: 5,
    tileUrl: import.meta.env.VITE_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution:
      import.meta.env.VITE_MAP_ATTRIBUTION ||
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  },
)

const mapElement = ref<HTMLElement | null>(null)
let map: L.Map | undefined

onMounted(() => {
  if (!mapElement.value) {
    throw new Error('Nie można zainicjalizować mapy bez elementu kontenera.')
  }

  map = L.map(mapElement.value, {
    zoomControl: true,
    attributionControl: true,
    scrollWheelZoom: false,
  }).setView(props.center, props.zoom)

  L.tileLayer(props.tileUrl, { attribution: props.attribution, maxZoom: 19 }).addTo(map)
})

onBeforeUnmount(() => {
  map?.remove()
})
</script>

<template>
  <div
    ref="mapElement"
    class="map-view"
    role="application"
    aria-label="Interaktywna mapa"
    tabindex="0"
  />
</template>

<style>
.map-view {
  width: 100%;
  height: 100%;
  min-height: 20rem;
  border-radius: inherit;
}
</style>
