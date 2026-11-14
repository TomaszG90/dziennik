import { ref } from 'vue'

export interface ToastMessage {
  id: number
  message: string
}

const toastDuration = 6000
const nextToastId = ref(0)
const timers = new Map<number, ReturnType<typeof setTimeout>>()

export const toasts = ref<ToastMessage[]>([])

export function showErrorToast(message: string): void {
  const id = ++nextToastId.value
  toasts.value.push({ id, message })
  timers.set(
    id,
    setTimeout(() => dismissToast(id), toastDuration),
  )
}

export function dismissToast(id: number): void {
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
  toasts.value = toasts.value.filter((toast) => toast.id !== id)
}
