import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import ToastContainer from '@/components/ToastContainer.vue'
import { dismissToast, showErrorToast, toasts } from '@/services/toast'

function clearToasts() {
  for (const toast of toasts.value) {
    dismissToast(toast.id)
  }
}

describe('ToastContainer', () => {
  afterEach(() => {
    clearToasts()
    vi.useRealTimers()
  })

  it('shows an accessible error toast and lets the user dismiss it', async () => {
    const wrapper = mount(ToastContainer)
    showErrorToast('Coś poszło nie tak.')
    await wrapper.vm.$nextTick()

    expect(wrapper.get('[role="alert"]').text()).toContain('Coś poszło nie tak.')
    await wrapper.get('button[aria-label="Zamknij komunikat"]').trigger('click')

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('automatically dismisses a toast after six seconds', async () => {
    vi.useFakeTimers()
    const wrapper = mount(ToastContainer)
    showErrorToast('Błąd serwera.')
    await wrapper.vm.$nextTick()

    vi.advanceTimersByTime(6000)
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })
})
