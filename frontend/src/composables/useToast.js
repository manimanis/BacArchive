/**
 * BacArchive — Toast composable (Vue.js 3)
 */

import { ref } from 'vue'

const toasts = ref([])
let counter = 0

export function showToast(text, type = 'ok', duration = 3500) {
  const id = ++counter
  toasts.value.push({ id, text, type })
  setTimeout(() => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }, duration)
}

export function useToasts() {
  const removeToast = (id) => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }
  return { toasts, removeToast }
}