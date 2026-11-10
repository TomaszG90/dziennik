<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

export interface MapPlace {
  id: number
  name: string
  latitude: string | number | null
  longitude: string | number | null
  position?: number
  country_code?: string | null
}

export interface MapLeg {
  id?: number
  from_place_id: number
  to_place_id: number
  transport?: string
  distance?: string | number | null
  position?: number
}

const props = withDefaults(
  defineProps<{
    center?: [number, number]
    zoom?: number
    tileUrl?: string
    attribution?: string
    places?: MapPlace[]
    legs?: MapLeg[]
    fitBoundsOnUpdate?: boolean
  }>(),
  {
    center: () => [52.1, 19.4],
    zoom: 5,
    tileUrl: import.meta.env.VITE_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution:
      import.meta.env.VITE_MAP_ATTRIBUTION ||
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    places: () => [],
    legs: () => [],
    fitBoundsOnUpdate: true,
  },
)

const mapElement = ref<HTMLElement | null>(null)
let map: L.Map | undefined
let markersLayer: L.FeatureGroup | undefined
let linesLayer: L.FeatureGroup | undefined

function parseCoordinate(value: string | number | null | undefined): number | null {
  if (value === null || value === undefined) return null
  if (typeof value === 'string' && value.trim() === '') return null
  const num = Number(value)
  return Number.isFinite(num) ? num : null
}

function escapeHtml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

function renderPlacesAndLegs() {
  const currentMap = map
  const currentMarkersLayer = markersLayer
  const currentLinesLayer = linesLayer
  if (!currentMap || !currentMarkersLayer || !currentLinesLayer) return

  currentMarkersLayer.clearLayers()
  currentLinesLayer.clearLayers()

  const validPlaces: Array<{
    place: MapPlace
    lat: number
    lng: number
    position: number
  }> = []

  const placeCoordsMap = new Map<number, [number, number]>()

  props.places.forEach((place, index) => {
    const lat = parseCoordinate(place.latitude)
    const lng = parseCoordinate(place.longitude)
    if (lat === null || lng === null || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
      return
    }

    const pos = place.position ?? index + 1
    validPlaces.push({ place, lat, lng, position: pos })
    placeCoordsMap.set(place.id, [lat, lng])

    const icon = L.divIcon({
      className: 'trip-map-marker-container',
      html: `<div class="trip-map-marker-pin" aria-label="Punkt ${pos}: ${escapeHtml(place.name)}"><span class="trip-map-marker-num">${pos}</span></div>`,
      iconSize: [30, 42],
      iconAnchor: [15, 42],
      popupAnchor: [0, -38],
    })

    const marker = L.marker([lat, lng], { icon })
    const countryInfo = place.country_code
      ? `<span class="trip-marker-popup-country">Kraj: ${escapeHtml(place.country_code)}</span>`
      : ''
    marker.bindPopup(
      `<div class="trip-marker-popup">` +
        `<div class="trip-marker-popup-num">Punkt ${pos}</div>` +
        `<h3 class="trip-marker-popup-title">${escapeHtml(place.name)}</h3>` +
        countryInfo +
        `</div>`,
    )
    currentMarkersLayer.addLayer(marker)
  })

  // Render legs (odcinki)
  if (props.legs && props.legs.length > 0) {
    props.legs.forEach((leg) => {
      const fromCoord = placeCoordsMap.get(leg.from_place_id)
      const toCoord = placeCoordsMap.get(leg.to_place_id)
      if (!fromCoord || !toCoord) return

      const polyline = L.polyline([fromCoord, toCoord], {
        color: '#2563eb',
        weight: 4,
        opacity: 0.85,
        lineJoin: 'round',
      })

      const fromPlace = props.places.find((p) => p.id === leg.from_place_id)
      const toPlace = props.places.find((p) => p.id === leg.to_place_id)
      if (fromPlace && toPlace) {
        const dist =
          leg.distance !== null && leg.distance !== undefined ? `<br>Dystans: ${leg.distance} km` : ''
        polyline.bindPopup(
          `<div class="trip-leg-popup"><strong>Odcinek:</strong> ${escapeHtml(fromPlace.name)} &rarr; ${escapeHtml(toPlace.name)}${dist}</div>`,
        )
      }
      currentLinesLayer.addLayer(polyline)
    })
  } else if (validPlaces.length >= 2) {
    for (let i = 0; i < validPlaces.length - 1; i++) {
      const from = validPlaces[i]
      const to = validPlaces[i + 1]
      if (!from || !to) continue

      const polyline = L.polyline(
        [
          [from.lat, from.lng],
          [to.lat, to.lng],
        ],
        {
          color: '#2563eb',
          weight: 4,
          opacity: 0.85,
          lineJoin: 'round',
        },
      )
      polyline.bindPopup(
        `<div class="trip-leg-popup"><strong>Odcinek:</strong> ${escapeHtml(from.place.name)} &rarr; ${escapeHtml(to.place.name)}</div>`,
      )
      currentLinesLayer.addLayer(polyline)
    }
  }

  // Bounds fitting
  if (props.fitBoundsOnUpdate) {
    if (validPlaces.length >= 2) {
      const bounds = L.latLngBounds(validPlaces.map((p) => [p.lat, p.lng]))
      if (typeof bounds.isValid === 'function' ? bounds.isValid() : true) {
        currentMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 })
      }
    } else if (validPlaces.length === 1) {
      const singlePlace = validPlaces[0]
      if (singlePlace) {
        currentMap.setView([singlePlace.lat, singlePlace.lng], 12)
      }
    } else {
      currentMap.setView(props.center, props.zoom)
    }
  }
}

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

  linesLayer = L.featureGroup().addTo(map)
  markersLayer = L.featureGroup().addTo(map)

  renderPlacesAndLegs()
})

watch(
  () => [props.places, props.legs],
  () => {
    renderPlacesAndLegs()
  },
  { deep: true },
)

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
  position: relative;
}

.trip-map-marker-container {
  background: transparent !important;
  border: none !important;
}

.trip-map-marker-pin {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  background: #2563eb;
  color: #ffffff;
  border: 2px solid #ffffff;
  border-radius: 50% 50% 50% 0;
  transform: rotate(-45deg);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
  cursor: pointer;
  transition:
    transform 0.15s ease,
    background-color 0.15s ease;
}

.trip-map-marker-pin:hover {
  transform: rotate(-45deg) scale(1.15);
  background: #1d4ed8;
}

.trip-map-marker-num {
  transform: rotate(45deg);
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
}

.trip-marker-popup,
.trip-leg-popup {
  font-family: inherit;
  font-size: 0.875rem;
  color: #1e293b;
  line-height: 1.4;
}

.trip-marker-popup-num {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #2563eb;
  margin-bottom: 2px;
}

.trip-marker-popup-title {
  margin: 0 0 4px 0;
  font-size: 0.95rem;
  font-weight: 600;
  color: #0f172a;
}

.trip-marker-popup-country {
  display: inline-block;
  padding: 2px 6px;
  background: #f1f5f9;
  border-radius: 4px;
  font-size: 0.75rem;
  color: #475569;
}
</style>
