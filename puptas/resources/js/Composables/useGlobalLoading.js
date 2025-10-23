// /composables/useGlobalLoading.js
import { ref } from 'vue'

const isLoading = ref(false)

export function useGlobalLoading() {
  return {
    isLoading,
    start: () => { isLoading.value = true },
    finish: () => { isLoading.value = false },
  }
}
