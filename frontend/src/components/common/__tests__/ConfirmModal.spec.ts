import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConfirmModal from '../ConfirmModal.vue'

describe('ConfirmModal', () => {
  it('renders title and message when isOpen is true', () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: true,
        title: 'Usuń podróż',
        message: 'Czy na pewno chcesz usunąć?',
      },
    })

    expect(wrapper.text()).toContain('Usuń podróż')
    expect(wrapper.text()).toContain('Czy na pewno chcesz usunąć?')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
  })

  it('does not render dialog content when isOpen is false', () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: false,
        title: 'Usuń podróż',
        message: 'Czy na pewno chcesz usunąć?',
      },
    })

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('emits confirm when confirm button is clicked', async () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: true,
        title: 'Potwierdzenie',
        message: 'Potwierdź akcję',
      },
    })

    const confirmBtn = wrapper.get('[data-testid="modal-confirm-button"]')
    await confirmBtn.trigger('click')

    expect(wrapper.emitted('confirm')).toBeTruthy()
  })

  it('emits cancel and close when cancel button is clicked', async () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: true,
        title: 'Potwierdzenie',
        message: 'Potwierdź akcję',
      },
    })

    const cancelBtn = wrapper.get('[data-testid="modal-cancel-button"]')
    await cancelBtn.trigger('click')

    expect(wrapper.emitted('cancel')).toBeTruthy()
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('disables buttons when isLoading is true', () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: true,
        title: 'Usuwanie',
        message: 'Proszę czekać',
        isLoading: true,
      },
    })

    expect(wrapper.get('[data-testid="modal-confirm-button"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="modal-cancel-button"]').attributes('disabled')).toBeDefined()
  })
})
