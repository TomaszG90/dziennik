import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MapView from '@/components/MapView.vue'

const leafletMocks = vi.hoisted(() => {
  const addTo = vi.fn<(...args: unknown[]) => unknown>()
  const setView = vi.fn<(...args: unknown[]) => unknown>()
  const fitBounds = vi.fn<(...args: unknown[]) => unknown>()
  const remove = vi.fn<(...args: unknown[]) => unknown>()
  const clearLayers = vi.fn<(...args: unknown[]) => unknown>()
  const addLayer = vi.fn<(...args: unknown[]) => unknown>()
  const bindPopup = vi.fn<(...args: unknown[]) => unknown>()

  const mapInstance = {
    setView,
    fitBounds,
    remove,
  }

  const groupInstance = {
    addTo,
    clearLayers,
    addLayer,
  }

  const markerInstance = {
    addTo,
    bindPopup,
  }

  const polylineInstance = {
    addTo,
    bindPopup,
  }

  return {
    mapInstance,
    groupInstance,
    markerInstance,
    polylineInstance,
    map: vi.fn<(...args: unknown[]) => unknown>(() => mapInstance),
    setView,
    fitBounds,
    remove,
    tileLayer: vi.fn<(...args: unknown[]) => unknown>(() => ({ addTo })),
    featureGroup: vi.fn<(...args: unknown[]) => unknown>(() => groupInstance),
    marker: vi.fn<(...args: unknown[]) => unknown>(() => markerInstance),
    polyline: vi.fn<(...args: unknown[]) => unknown>(() => polylineInstance),
    divIcon: vi.fn<(...args: unknown[]) => unknown>((opts) => opts),
    latLngBounds: vi.fn<(...args: unknown[]) => unknown>((coords) => ({
      isValid: () => true,
      coords,
    })),
    addTo,
    clearLayers,
    addLayer,
    bindPopup,
  }
})

vi.mock('leaflet', () => {
  leafletMocks.map.mockReturnValue(leafletMocks.mapInstance)
  leafletMocks.setView.mockReturnValue(leafletMocks.mapInstance)
  leafletMocks.fitBounds.mockReturnValue(leafletMocks.mapInstance)

  return {
    default: {
      map: leafletMocks.map,
      tileLayer: leafletMocks.tileLayer,
      featureGroup: leafletMocks.featureGroup,
      marker: leafletMocks.marker,
      polyline: leafletMocks.polyline,
      divIcon: leafletMocks.divIcon,
      latLngBounds: leafletMocks.latLngBounds,
    },
  }
})

describe('MapView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    leafletMocks.map.mockReturnValue(leafletMocks.mapInstance)
    leafletMocks.setView.mockReturnValue(leafletMocks.mapInstance)
    leafletMocks.fitBounds.mockReturnValue(leafletMocks.mapInstance)
    leafletMocks.featureGroup.mockReturnValue(leafletMocks.groupInstance)
    leafletMocks.marker.mockReturnValue(leafletMocks.markerInstance)
    leafletMocks.polyline.mockReturnValue(leafletMocks.polylineInstance)
  })

  it('renders an accessible Leaflet map with configured tiles and cleans it up', () => {
    const wrapper = mount(MapView, {
      props: {
        center: [50.06, 19.94],
        zoom: 8,
        tileUrl: 'https://tiles.example.test/{z}/{x}/{y}.png',
        attribution: 'Example map provider',
      },
    })

    expect(wrapper.get('[role="application"]').attributes('aria-label')).toBe('Interaktywna mapa')
    expect(leafletMocks.map).toHaveBeenCalledWith(
      wrapper.element,
      expect.objectContaining({
        zoomControl: true,
        attributionControl: true,
        scrollWheelZoom: false,
      }),
    )
    expect(leafletMocks.setView).toHaveBeenCalledWith([50.06, 19.94], 8)
    expect(leafletMocks.tileLayer).toHaveBeenCalledWith(
      'https://tiles.example.test/{z}/{x}/{y}.png',
      { attribution: 'Example map provider', maxZoom: 19 },
    )
    expect(leafletMocks.addTo).toHaveBeenCalled()

    wrapper.unmount()

    expect(leafletMocks.remove).toHaveBeenCalledOnce()
  })

  it('renders markers for places with popups and fits map bounds', () => {
    const places = [
      { id: 1, name: 'Warszawa', latitude: 52.23, longitude: 21.01, position: 1, country_code: 'PL' },
      { id: 2, name: 'Kraków', latitude: 50.06, longitude: 19.94, position: 2, country_code: 'PL' },
    ]

    mount(MapView, {
      props: { places },
    })

    expect(leafletMocks.marker).toHaveBeenCalledTimes(2)
    expect(leafletMocks.marker).toHaveBeenCalledWith([52.23, 21.01], expect.any(Object))
    expect(leafletMocks.marker).toHaveBeenCalledWith([50.06, 19.94], expect.any(Object))
    expect(leafletMocks.addLayer).toHaveBeenCalledTimes(3) // 2 markers + 1 connecting line
    expect(leafletMocks.bindPopup).toHaveBeenCalled()
    expect(leafletMocks.latLngBounds).toHaveBeenCalledWith([
      [52.23, 21.01],
      [50.06, 19.94],
    ])
    expect(leafletMocks.fitBounds).toHaveBeenCalled()
  })

  it('renders polylines for explicit legs connecting places', () => {
    const places = [
      { id: 1, name: 'Gdańsk', latitude: 54.35, longitude: 18.64, position: 1 },
      { id: 2, name: 'Wrocław', latitude: 51.1, longitude: 17.03, position: 2 },
    ]
    const legs = [
      { id: 10, from_place_id: 1, to_place_id: 2, transport: 'train', distance: 480 },
    ]

    mount(MapView, {
      props: { places, legs },
    })

    expect(leafletMocks.polyline).toHaveBeenCalledWith(
      [
        [54.35, 18.64],
        [51.1, 17.03],
      ],
      expect.objectContaining({ color: '#2563eb' }),
    )
  })

  it('skips places with invalid or empty coordinates', () => {
    const places = [
      { id: 1, name: 'Poznań', latitude: '52.40', longitude: '16.92', position: 1 },
      { id: 2, name: 'Brak koordynatów', latitude: null, longitude: null, position: 2 },
      { id: 3, name: 'Puste koordynaty', latitude: '', longitude: '10.0', position: 3 },
    ]

    mount(MapView, {
      props: { places },
    })

    expect(leafletMocks.marker).toHaveBeenCalledTimes(1)
    expect(leafletMocks.marker).toHaveBeenCalledWith([52.4, 16.92], expect.any(Object))
    expect(leafletMocks.setView).toHaveBeenCalledWith([52.4, 16.92], 12)
  })

  it('clears and re-renders layers when places prop changes', async () => {
    const wrapper = mount(MapView, {
      props: {
        places: [{ id: 1, name: 'Kraków', latitude: 50.06, longitude: 19.94, position: 1 }],
      },
    })

    expect(leafletMocks.marker).toHaveBeenCalledTimes(1)

    await wrapper.setProps({
      places: [
        { id: 1, name: 'Kraków', latitude: 50.06, longitude: 19.94, position: 1 },
        { id: 2, name: 'Zakopane', latitude: 49.29, longitude: 19.95, position: 2 },
      ],
    })

    expect(leafletMocks.clearLayers).toHaveBeenCalled()
    expect(leafletMocks.marker).toHaveBeenCalledTimes(3) // 1 initial + 2 after update
  })
})
