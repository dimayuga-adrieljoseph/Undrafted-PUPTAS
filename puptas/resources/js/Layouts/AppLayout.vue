<script setup>
import Sidebar from "@/Components/Sidebar.vue"
import { useGlobalLoading } from "@/Composables/useGlobalLoading"
import { usePage } from "@inertiajs/vue3"
import { computed, ref, onMounted } from "vue"

// FontAwesome
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMoon, faSun } from "@fortawesome/free-solid-svg-icons"

library.add(faMoon, faSun)

const { isLoading } = useGlobalLoading()

// User
const page = usePage()
const user = computed(() => page.props.auth.user)

// Dark mode
const isDarkMode = ref(false)

onMounted(() => {
    const saved = localStorage.getItem("darkMode") === "true"
    isDarkMode.value = saved
    document.documentElement.classList.toggle("dark", saved)
})

const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value
    document.documentElement.classList.toggle("dark", isDarkMode.value)
    localStorage.setItem("darkMode", String(isDarkMode.value))
}

// `darkModeIcon` removed; template will use array icon syntax for reactivity
</script>


<template>
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <Sidebar />

        <!-- Main Content -->
        <div class="flex-1 bg-[#faf6f2] dark:bg-gray-900 p-6 relative">
            <!-- Header -->
            <div class="flex justify-end items-center gap-4 mb-6">
                <!-- Dark Mode Toggle -->
                    <button
                        @click="toggleDarkMode"
                        class="text-[#9E122C] dark:text-white transition"
                        title="Toggle Dark Mode"
                    >
                        <FontAwesomeIcon
                            :icon="['fas', isDarkMode ? 'moon' : 'sun']"
                            class="text-xl"
                        />
                    </button>

                <!-- Profile Pill -->
                <div
                    class="flex items-center space-x-3 bg-white border-4 border-red-400
                           dark:bg-gray-900 px-2 py-2 rounded-full shadow"
                >
                    <!-- Avatar -->
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

                    <!-- User Info -->
                    <div class="text-sm leading-tight">
                        <p class="font-semibold text-[#9E122C]">
                            {{ user?.lastname }}, {{ user?.firstname }}
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            Administrator
                        </p>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <slot />

            <!-- Loading Overlay -->
            <div
                v-if="isLoading"
                class="fixed inset-0 z-[999] bg-black/30 flex items-center justify-center"
            >
                <span class="text-white text-lg font-semibold">
                    Loading...
                </span>
            </div>
        </div>
    </div>
</template>
