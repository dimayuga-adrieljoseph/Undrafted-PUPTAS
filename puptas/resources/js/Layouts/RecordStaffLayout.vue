<script setup>
import Sidebar from '@/Components/Sidebar.vue'
import { useGlobalLoading } from '@/Composables/useGlobalLoading'
import { usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted } from 'vue'

// FontAwesome
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faMoon, faSun } from '@fortawesome/free-solid-svg-icons'

library.add(faMoon, faSun)

// Global loading composable
const { isLoading } = useGlobalLoading()

// Current user
const page = usePage()
const user = computed(() => page.props.user)

// Dark mode (layout-level only)
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
</script>

<template>
  <div class="flex min-h-screen bg-orange-100 dark:bg-gray-900">
    <!-- ✅ CENTRALIZED SIDEBAR (Record Staff variant) -->
    <Sidebar variant="record" />

    <!-- Main Content -->
    <div
      class="flex-1 p-6 transition-all
             bg-gradient-to-br from-orange-200 to-red-400
             dark:from-gray-100 dark:to-gray-900"
    >
      <main>
        <!-- Header -->
        <div class="flex justify-end items-center gap-4 mb-4">
          <!-- Dark Mode Toggle -->
          <button
            @click="toggleDarkMode"
            class="text-white transition"
            title="Toggle Dark Mode"
          >
            <FontAwesomeIcon
              :icon="isDarkMode ? 'moon' : 'sun'"
              class="text-xl"
            />
          </button>

          <!-- Profile Pill -->
          <div
            class="flex items-center space-x-3 bg-white border-4 border-red-400
                   dark:bg-gray-900 px-2 py-2 rounded-full shadow"
          >
            <svg
              class="w-8 h-8 stroke-[#9E122C]"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 14c3.866 0 7 3.134 7 7H5c0-3.866 3.134-7 7-7z"
              />
              <circle cx="12" cy="7" r="4" />
            </svg>

            <div class="text-sm leading-tight">
              <p class="font-semibold text-[#9E122C]">
                {{ user?.lastname }}, {{ user?.firstname }}
              </p>
              <p class="text-xs text-gray-600 dark:text-gray-300">
                Record Staff
              </p>
            </div>
          </div>
        </div>

        <!-- Page Content -->
        <slot />

        <!-- Global Loading Overlay -->
        <div
          v-if="isLoading"
          class="fixed inset-0 z-50 bg-black/30 flex flex-col items-center justify-center"
        >
          <svg
            class="animate-spin h-16 w-16 text-[#9E122C] mb-4"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            />
            <path
              class="opacity-50"
              fill="currentColor"
              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
          </svg>

          <p class="text-lg font-semibold text-[#9E122C]">
            Loading, please wait...
          </p>
        </div>
      </main>
    </div>
  </div>
</template>
