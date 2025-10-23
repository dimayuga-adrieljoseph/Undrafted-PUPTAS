import { ref } from 'vue'

const snackbar = ref({
  show: false,
  message: '',
  type: 'success',
})

let timeoutId = null

export function useSnackbar() {
  function show(message, type = 'success', duration = 3000) {
    snackbar.value.message = message
    snackbar.value.type = type
    snackbar.value.show = true

    if (timeoutId) clearTimeout(timeoutId)
    timeoutId = setTimeout(() => {
      snackbar.value.show = false
    }, duration)
  }

  function hide() {
    snackbar.value.show = false
    if (timeoutId) clearTimeout(timeoutId)
  }

  return {
    snackbar,
    show,
    hide,
  }
}
