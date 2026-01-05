<script setup>
import { ref, computed, onMounted, watchEffect } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

// Import the reusable sidebar
import Sidebar from '@/Components/Sidebar.vue'

const page = usePage()
const user = computed(() => page.props.user)

const isDarkMode = ref(false)

onMounted(() => {
  const saved = localStorage.getItem('darkMode') === 'true'
  isDarkMode.value = saved
  document.documentElement.classList.toggle('dark', saved)
})

function toggleDarkMode() {
  isDarkMode.value = !isDarkMode.value
  document.documentElement.classList.toggle('dark', isDarkMode.value)
  localStorage.setItem('darkMode', String(isDarkMode.value))
}

const isLoading = ref(false)

onMounted(() => {
  router.on('start', () => (isLoading.value = true))
  router.on('finish', () => (isLoading.value = false))
  router.on('error', () => (isLoading.value = false))
})

const logout = () => {
  router.post(route('logout'))
}
</script>

<template>
  <div class="flex min-h-screen bg-orange-100 text-black dark:bg-gray-900 dark:text-white">
    <!-- Reusable Sidebar -->
    <Sidebar :variant="'interviewer'" />

    <!-- Main Content -->
    <div class="flex-1 bg-[#faf6f2] dark:bg-gray-900 p-6 relative" style="margin-left: var(--sidebar-width, 5rem)">
      <main>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-2 mb-1">
          <h2 class="font-semibold text-xl text-[#9E122C] dark:text-gray-200 leading-tight"></h2>

          <!-- Dark Mode Toggle -->
          <button @click="toggleDarkMode" class="text-[#9E122C] dark:text-white transition" title="Toggle Dark Mode">
            <FontAwesomeIcon :icon="isDarkMode ? 'moon' : 'sun'" class="text-xl" />
          </button>

          <!-- Profile Avatar with Name -->
          <div class="flex items-center space-x-3 bg-white border-4 border-red-400 dark:bg-gray-900 px-2 py-2 rounded-full shadow">
            <svg class="w-8 h-8 stroke-[#9E122C]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14c3.866 0 7 3.134 7 7H5c0-3.866 3.134-7 7-7z"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            <div class="text-sm">
              <p class="font-sm text-[#9E122C]">{{ user?.lastname }}, {{ user?.firstname }}</p>
              <p class="text-gray-600 dark:text-gray-300">Interviewer</p>
            </div>
          </div>
        </div>

        <!-- Loading Overlay -->
        <div v-if="isLoading" class="fixed inset-0 z-50 bg-white bg-opacity-25 flex flex-col items-center justify-center">
          <svg class="animate-spin h-16 w-16 text-[#9E122C] mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-50" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
          <p class="text-lg font-semibold text-[#9E122C]">Loading, please wait...</p>
        </div>

        <slot />
      </main>
    </div>
  </div>
</template>