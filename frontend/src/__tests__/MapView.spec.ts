import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MapView from '@/components/MapView.vue'

const leafletMocks = vi.hoisted(() => ({
  map: vi.fn<(...args: unknown[]) => unknown>(),
  setView: vi.fn<(...args: unknown[]) => unknown>(),
  remove: vi.fn<(...args: unknown[]) => unknown>(),
  tileLayer: vi.fn<(...args: unknown[]) => unknown>(),
  addTo: vi.fn<(...args: unknown[]) => unknown>(),
}))

vi.mock('leaflet', () => {
  const mapInstance = {
    setView: leafletMocks.setView,
    remove: leafletMocks.remove,
  }
  leafletMocks.map.mockReturnValue(mapInstance)
  leafletMocks.setView.mockReturnValue(mapInstance)
  leafletMocks.tileLayer.mockReturnValue({ addTo: leafletMocks.addTo })

  return {
    default: {
      map: leafletMocks.map,
      tileLayer: leafletMocks.tileLayer,
    },
  }
})

describe('MapView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
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
})
