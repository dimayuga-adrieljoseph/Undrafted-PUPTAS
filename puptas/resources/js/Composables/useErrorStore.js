// Composables/useErrorStore.js
import { reactive } from 'vue'

const errorState = reactive({
  message: null,      // string | null
  retryCallback: null // function | null
})

export function useErrorStore() {
  const setError = (message, retryCallback = null) => {
    errorState.message = message
    errorState.retryCallback = retryCallback
  }

  const clearError = () => {
    errorState.message = null
    errorState.retryCallback = null
  }

  const retry = () => {
    if (errorState.retryCallback) {
      errorState.retryCallback()
    }
  }

  return { errorState, setError, clearError, retry }
}
