<script lang="ts">
export interface MapPlace {
  id: number
  name: string
  latitude: string | number | null
  longitude: string | number | null
  position?: number
  country_code?: string | null
  trip_name?: string
}

export interface MapLeg {
  id?: number
  from_place_id: number
  to_place_id: number
  transport?: string
  distance?: string | number | null
  position?: number
  trip_name?: string
  geometry?: {
    type: 'LineString'
    coordinates: Array<[number, number]>
  } | null
}

export interface TransportStyle {
  label: string
  color: string
  dashArray?: string
  weight: number
  opacity: number
  icon: string
}

export const TRANSPORT_CONFIG: Record<string, TransportStyle> = {
  car: {
    label: 'Samochód',
    color: '#2563eb',
    weight: 4,
    opacity: 0.85,
    icon: '🚗',
  },
  plane: {
    label: 'Samolot',
    color: '#7c3aed',
    dashArray: '8, 8',
    weight: 3,
    opacity: 0.85,
    icon: '✈️',
  },
  train: {
    label: 'Pociąg',
    color: '#059669',
    dashArray: '12, 6',
    weight: 4,
    opacity: 0.85,
    icon: '🚆',
  },
  walking: {
    label: 'Pieszo',
    color: '#d97706',
    dashArray: '4, 6',
    weight: 3,
    opacity: 0.85,
    icon: '🚶',
  },
  other: {
    label: 'Inny',
    color: '#64748b',
    dashArray: '6, 6',
    weight: 3,
    opacity: 0.85,
    icon: '🧭',
  },
}

export function getTransportStyle(transport?: string | null): TransportStyle {
  if (transport && transport in TRANSPORT_CONFIG) {
    return TRANSPORT_CONFIG[transport]!
  }
  return TRANSPORT_CONFIG.other!
}
</script>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = withDefaults(
  defineProps<{
    center?: [number, number]
    zoom?: number
    tileUrl?: string
    attribution?: string
    places?: MapPlace[]
    legs?: MapLeg[]
    fitBoundsOnUpdate?: boolean
    showLegend?: boolean
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
    showLegend: true,
  },
)

const mapElement = ref<HTMLElement | null>(null)
const isLegendExpanded = ref(true)
let map: L.Map | undefined
let markersLayer: L.FeatureGroup | undefined
let linesLayer: L.FeatureGroup | undefined

const usedTransports = computed(() => {
  const set = new Set<string>()
  if (props.legs) {
    props.legs.forEach((leg) => {
      if (leg.transport) {
        set.add(leg.transport)
      }
    })
  }
  return set
})

function getLegendLineStyle(style: TransportStyle): {
  borderColor: string
  borderTopStyle: 'solid' | 'dashed' | 'dotted'
} {
  let borderStyle: 'solid' | 'dashed' | 'dotted' = 'solid'
  if (style.dashArray) {
    borderStyle = style.dashArray === '4, 6' ? 'dotted' : 'dashed'
  }
  return {
    borderColor: style.color,
    borderTopStyle: borderStyle,
  }
}

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

function createPlaneArc(from: [number, number], to: [number, number]): [number, number][] {
  const longitudeDelta = ((to[1] - from[1] + 540) % 360) - 180
  const distance = Math.hypot(to[0] - from[0], longitudeDelta)
  const arcHeight = Math.min(24, distance * 0.18)
  const points: [number, number][] = []

  for (let step = 0; step <= 32; step += 1) {
    const progress = step / 32
    const latitude = from[0] + (to[0] - from[0]) * progress
    const longitude = from[1] + longitudeDelta * progress
    const arc = Math.sin(Math.PI * progress) * arcHeight
    points.push([Math.max(-85, Math.min(85, latitude + arc)), longitude])
  }

  return points
}

function getLegGeometry(leg: MapLeg): [number, number][] | null {
  if (leg.geometry?.type !== 'LineString' || leg.geometry.coordinates.length < 2) return null

  const points: [number, number][] = []
  for (const coordinate of leg.geometry.coordinates) {
    const longitude = parseCoordinate(coordinate[0])
    const latitude = parseCoordinate(coordinate[1])
    if (
      longitude === null ||
      latitude === null ||
      Math.abs(longitude) > 180 ||
      Math.abs(latitude) > 90
    ) {
      return null
    }
    points.push([latitude, longitude])
  }

  return points
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
    const tripInfo = place.trip_name
      ? `<span class="trip-marker-popup-country">Podróż: ${escapeHtml(place.trip_name)}</span>`
      : ''
    marker.bindPopup(
      `<div class="trip-marker-popup">` +
        `<div class="trip-marker-popup-num">Punkt ${pos}</div>` +
        `<h3 class="trip-marker-popup-title">${escapeHtml(place.name)}</h3>` +
        tripInfo +
        countryInfo +
        `</div>`,
    )
    currentMarkersLayer.addLayer(marker)
  })

  // Render legs (odcinki) with transport differentiation
  if (props.legs && props.legs.length > 0) {
    props.legs.forEach((leg) => {
      const fromCoord = placeCoordsMap.get(leg.from_place_id)
      const toCoord = placeCoordsMap.get(leg.to_place_id)
      if (!fromCoord || !toCoord) return

      const style = getTransportStyle(leg.transport)
      const polylineOptions: L.PolylineOptions = {
        color: style.color,
        weight: style.weight,
        opacity: style.opacity,
        lineJoin: 'round',
      }
      if (style.dashArray) {
        polylineOptions.dashArray = style.dashArray
      }

      const routeCoordinates =
        leg.transport === 'plane'
          ? (getLegGeometry(leg) ?? createPlaneArc(fromCoord, toCoord))
          : leg.transport === 'car'
            ? (getLegGeometry(leg) ?? [fromCoord, toCoord])
            : [fromCoord, toCoord]
      const polyline = L.polyline(routeCoordinates, polylineOptions)

      const fromPlace = props.places.find((p) => p.id === leg.from_place_id)
      const toPlace = props.places.find((p) => p.id === leg.to_place_id)
      if (fromPlace && toPlace) {
        const dist =
          leg.distance !== null && leg.distance !== undefined
            ? `<div class="trip-leg-popup-distance">Dystans: ${leg.distance} km</div>`
            : ''
        polyline.bindPopup(
          `<div class="trip-leg-popup">` +
            `<div class="trip-leg-popup-header">` +
              `<span class="trip-leg-popup-icon" aria-hidden="true">${style.icon}</span>` +
              `<strong>${escapeHtml(style.label)}</strong>` +
            `</div>` +
            `<div class="trip-leg-popup-route">${escapeHtml(fromPlace.name)} &rarr; ${escapeHtml(toPlace.name)}</div>` +
            (leg.trip_name ? `<div class="trip-leg-popup-distance">Podróż: ${escapeHtml(leg.trip_name)}</div>` : '') +
            dist +
          `</div>`,
        )
      }
      currentLinesLayer.addLayer(polyline)
    })
  } else if (validPlaces.length >= 2) {
    const style = TRANSPORT_CONFIG.other!
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
          color: style.color,
          weight: style.weight,
          opacity: style.opacity,
          lineJoin: 'round',
          dashArray: style.dashArray,
        },
      )
      polyline.bindPopup(
        `<div class="trip-leg-popup">` +
          `<div class="trip-leg-popup-header">` +
            `<span class="trip-leg-popup-icon" aria-hidden="true">${style.icon}</span>` +
            `<strong>${escapeHtml(style.label)}</strong>` +
          `</div>` +
          `<div class="trip-leg-popup-route">${escapeHtml(from.place.name)} &rarr; ${escapeHtml(to.place.name)}</div>` +
        `</div>`,
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

  linesLayer = L.featureGroup()
  markersLayer = L.featureGroup()
  linesLayer.addTo(map)
  markersLayer.addTo(map)

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
  >
    <aside
      v-if="showLegend"
      class="map-legend"
      role="region"
      aria-label="Legenda transportu"
    >
      <button
        type="button"
        class="legend-header"
        :aria-expanded="isLegendExpanded"
        @click.stop="isLegendExpanded = !isLegendExpanded"
      >
        <span class="legend-header-title">Legenda transportu</span>
        <span class="legend-header-icon" aria-hidden="true">{{ isLegendExpanded ? '−' : '+' }}</span>
      </button>
      <ul v-show="isLegendExpanded" class="legend-list">
        <li
          v-for="(style, key) in TRANSPORT_CONFIG"
          :key="key"
          class="legend-item"
          :class="{ 'is-used': usedTransports.has(key) }"
          :data-testid="`legend-item-${key}`"
        >
          <span class="legend-item-icon" aria-hidden="true">{{ style.icon }}</span>
          <span class="legend-item-line" :style="getLegendLineStyle(style)" aria-hidden="true" />
          <span class="legend-item-label">{{ style.label }}</span>
          <span v-if="usedTransports.has(key)" class="legend-item-badge">w trasie</span>
        </li>
      </ul>
    </aside>
  </div>
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

.trip-leg-popup-header {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 0.25rem;
  font-size: 0.875rem;
}

.trip-leg-popup-route {
  font-size: 0.8125rem;
  color: #334155;
  margin-bottom: 0.25rem;
}

.trip-leg-popup-distance {
  font-size: 0.75rem;
  color: #64748b;
}

/* Map legend styles */
.map-legend {
  position: absolute;
  bottom: 0.75rem;
  right: 0.75rem;
  z-index: 1000;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(4px);
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  font-size: 0.8125rem;
  color: #1e293b;
  min-width: 11rem;
  max-width: 14rem;
  overflow: hidden;
  pointer-events: auto;
}

@media (prefers-color-scheme: dark) {
  .map-legend {
    background: rgba(30, 41, 59, 0.95);
    border-color: #334155;
    color: #f1f5f9;
  }
}

.legend-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 0.45rem 0.65rem;
  background: transparent;
  border: none;
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  color: inherit;
  cursor: pointer;
  text-align: left;
}

.legend-header:hover {
  background: rgba(0, 0, 0, 0.05);
}

.legend-header-icon {
  font-size: 0.875rem;
  font-weight: bold;
  margin-left: 0.5rem;
  line-height: 1;
}

.legend-list {
  list-style: none;
  margin: 0;
  padding: 0 0.65rem 0.45rem 0.65rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  opacity: 0.65;
  transition: opacity 0.15s ease;
}

.legend-item.is-used {
  opacity: 1;
  font-weight: 600;
}

.legend-item-icon {
  font-size: 0.875rem;
  line-height: 1;
  width: 1.1rem;
  text-align: center;
}

.legend-item-line {
  display: inline-block;
  width: 20px;
  height: 0;
  border-top-width: 3px;
  border-top-style: solid;
  border-radius: 2px;
}

.legend-item-label {
  flex: 1;
  font-size: 0.75rem;
}

.legend-item-badge {
  font-size: 0.625rem;
  padding: 1px 4px;
  background: #eff6ff;
  color: #2563eb;
  border-radius: 4px;
  font-weight: 500;
}

@media (prefers-color-scheme: dark) {
  .legend-item-badge {
    background: #1e3a8a;
    color: #93c5fd;
  }
}
</style>
