<script setup>
import Sidebar from '@/Components/Sidebar.vue'
import Footer from '@/Components/Footer.vue'
import { useGlobalLoading } from '@/Composables/useGlobalLoading'
import { usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, watch } from 'vue'
import TermsandConditionsModal from '@/Pages/Modal/TermsandConditionsModal.vue'

// FontAwesome
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faMoon, faSun, faBell } from '@fortawesome/free-solid-svg-icons'

library.add(faMoon, faSun, faBell)

const { isLoading } = useGlobalLoading()

// User
const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)

// Privacy consent
const privacyConsent = computed(() => page.props.privacy_consent ?? { required: false })
const showPrivacyModal = ref(false)

// Check if privacy consent is required on mount and when user changes
watch(() => [page.props.auth?.user, privacyConsent.value], ([user, consent]) => {
    if (user && consent.required) {
        showPrivacyModal.value = true
    }
}, { immediate: true })

// Dark mode
const isDarkMode = ref(false)

onMounted(() => {
    const saved = localStorage.getItem('darkMode') === 'true'
    isDarkMode.value = saved
    document.documentElement.classList.toggle('dark', saved)
})

const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value
    document.documentElement.classList.toggle('dark', isDarkMode.value)
    localStorage.setItem('darkMode', String(isDarkMode.value))
}

// Privacy consent handlers
const handlePrivacyAccept = () => {
    // Send consent to server
    window.axios.post('/privacy-consent/accept')
        .then(() => {
            showPrivacyModal.value = false
            // Refresh the page to update the consent state
            router.reload({ only: ['privacy_consent'] })
        })
        .catch((error) => {
            console.error('Failed to accept privacy consent:', error)
        })
}

const handlePrivacyCancel = () => {
    // Log out the user using Inertia router (POST method)
    router.post(route('logout'), {}, {
        onSuccess: () => {
            showPrivacyModal.value = false
        },
        onError: (error) => {
            console.error('Failed to log out:', error)
        }
    })
}
</script>

<template>
    <div
        class="min-h-screen bg-gradient-to-br from-[#faf6f2] to-[#f1ebe6]
            dark:from-gray-950 dark:to-gray-900 flex"
    >
        <!-- Sidebar -->
        <Sidebar />

        <!-- Main Area -->
        <div
            class="flex-1 flex flex-col"
            style="margin-left: var(--sidebar-width, 5rem)"
        >
            <!-- Top Navigation Bar -->
            <header
                class="sticky top-0 z-40 h-16 px-6 flex items-center
                    justify-between bg-white/80 backdrop-blur border-b
                    border-gray-200 dark:bg-gray-900/80 dark:border-gray-800"
            >
                <!-- Page Title Slot -->
                <div class="flex items-center gap-4">
                    <slot name="title">
                        <h1
                            class="text-lg font-semibold text-gray-800
                                dark:text-gray-100"
                        >
                            Admin Portal
                        </h1>
                    </slot>
                </div>

                <!-- Right Controls -->
                <div class="flex items-center gap-5">
                    <!-- Dark Mode Toggle -->
                    <button
                        @click="toggleDarkMode"
                        class="w-9 h-9 rounded-lg flex items-center
                            justify-center bg-gray-100 hover:bg-gray-200
                            dark:bg-gray-800 dark:hover:bg-gray-700 transition"
                    >
                        <FontAwesomeIcon
                            :icon="['fas', isDarkMode ? 'moon' : 'sun']"
                            class="text-gray-700 dark:text-gray-200"
                        />
                    </button>

                    <!-- User Menu -->
                    <div
                        class="flex items-center gap-3 px-3 py-1.5 rounded-full
                            bg-white border border-gray-200 shadow-sm
                            dark:bg-gray-900 dark:border-gray-700"
                    >
                        <div
                            class="w-9 h-9 rounded-full flex items-center
                                justify-center bg-[#9E122C]/10 text-[#9E122C]
                                font-semibold"
                        >
                            {{ user?.firstname?.charAt(0)
                            }}{{ user?.lastname?.charAt(0) }}
                        </div>
                        <div class="hidden sm:block leading-tight">
                            <p
                                class="text-sm font-medium text-gray-800
                                    dark:text-gray-100"
                            >
                                {{ user?.firstname }} {{ user?.lastname }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Administrator
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <div
                    class="max-w-[1400px] mx-auto rounded-2xl p-6 bg-white
                        shadow-sm border border-gray-200 dark:bg-gray-900
                        dark:border-gray-800"
                >
                    <slot />
                </div>
            </main>

            <!-- Footer -->
            <Footer />
        </div>

        <!-- Global Loading Overlay -->
        <div
            v-if="isLoading"
            class="fixed inset-0 z-[999] bg-black/40 backdrop-blur-sm flex
                items-center justify-center"
        >
            <div
                class="px-6 py-3 rounded-xl bg-white shadow-lg dark:bg-gray-900"
            >
                <span class="text-gray-800 dark:text-gray-100 font-semibold">
                    Loading...
                </span>
            </div>
        </div>

        <!-- Terms and Conditions Modal -->
        <TermsandConditionsModal 
            :show="showPrivacyModal" 
            :can-close="false"
            @accept="handlePrivacyAccept"
            @cancel="handlePrivacyCancel"
        />
    </div>
</template>
