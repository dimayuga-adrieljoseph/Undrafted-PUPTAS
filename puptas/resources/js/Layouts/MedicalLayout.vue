<!-- Medical Layout Redesigned -->
<script setup>
import Sidebar from '@/Components/Sidebar.vue'
import Footer from '@/Components/Footer.vue'
import { useGlobalLoading } from '@/Composables/useGlobalLoading'
import { usePage, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, watch } from 'vue'
import TermsandConditionsModal from '@/Pages/Modal/TermsandConditionsModal.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faMoon, faSun, faBell } from '@fortawesome/free-solid-svg-icons'

library.add(faMoon, faSun, faBell)

const { isLoading } = useGlobalLoading()

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)

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

// Privacy consent
const privacyConsent = computed(() => page.props.privacy_consent ?? { required: false })
const showPrivacyModal = ref(false)

// Check if privacy consent is required on mount and when user changes
watch(() => [page.props.auth?.user, privacyConsent.value], ([user, consent]) => {
    if (user && consent && consent.required) {
        showPrivacyModal.value = true
    } else if (!consent || !consent.required) {
        showPrivacyModal.value = false
    }
}, { immediate: true })

// Privacy consent handlers
const handlePrivacyAccept = () => {
    window.axios.post('/privacy-consent/accept')
        .then(() => {
            showPrivacyModal.value = false
            router.reload({ only: ['privacy_consent'] })
        })
        .catch((error) => {
            console.error('Failed to accept privacy consent:', error)
        })
}

const handlePrivacyCancel = () => {
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
        class="min-h-screen flex bg-gradient-to-br from-emerald-50 to-[#faf6f2] dark:from-gray-950 dark:to-gray-900"
    >
        <!-- Sidebar -->
        <Sidebar variant="medical" />

        <!-- Main Area -->
        <div
            class="flex-1 flex flex-col"
            style="margin-left: var(--sidebar-width, 5rem)"
        >
            <!-- Top Bar -->
            <header
                class="sticky top-0 z-40 h-16 px-6 flex items-center justify-between bg-white/80 backdrop-blur border-b border-gray-200 dark:bg-gray-900/80 dark:border-gray-800"
            >
                <!-- Title -->
                <slot name="title">
                    <h1
                        class="text-lg font-semibold text-gray-800 dark:text-gray-100"
                    >
                        Medical Office
                    </h1>
                </slot>

                <!-- Controls -->
                <div class="flex items-center gap-4">
                    <button
                        class="text-gray-500 hover:text-[#9E122C] dark:hover:text-white transition dark:text-gray-300"
                    >
                        <FontAwesomeIcon :icon="['fas', 'bell']" class="text-lg" />
                    </button>

                    <button
                        @click="toggleDarkMode"
                        class="w-9 h-9 rounded-lg flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition"
                    >
                        <FontAwesomeIcon
                            :icon="['fas', isDarkMode ? 'moon' : 'sun']"
                            class="text-gray-700 dark:text-gray-200"
                        />
                    </button>

                    <!-- User -->
                    <div
                        class="flex items-center gap-3 px-3 py-1.5 rounded-full bg-white border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-700"
                    >
                        <div
                            class="w-9 h-9 rounded-full flex items-center justify-center bg-[#9E122C]/10 text-[#9E122C] font-semibold dark:text-white"
                        >
                            {{ user?.firstname?.charAt(0)
                            }}{{ user?.lastname?.charAt(0) }}
                        </div>
                        <div class="hidden sm:block leading-tight">
                            <p
                                class="text-sm font-medium text-gray-800 dark:text-gray-100"
                            >
                                {{ user?.firstname }} {{ user?.lastname }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Medical
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <div
                    class="w-full rounded-2xl p-6 bg-white min-h-[calc(100vh-12rem)] shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800"
                >
                    <slot />
                </div>
            </main>

            <!-- Footer -->
            <Footer />
        </div>

        <!-- Loading Overlay -->
        <div
            v-if="isLoading"
            class="fixed inset-0 z-[999] bg-black/40 backdrop-blur-sm flex items-center justify-center"
        >
            <div
                class="px-6 py-4 rounded-xl bg-white shadow-lg dark:bg-gray-900 flex flex-col items-center gap-3"
            >
                <svg
                    class="animate-spin h-8 w-8 text-[#9E122C] dark:text-white"
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
                <span class="font-medium text-gray-800 dark:text-gray-100">
                    Loading, please wait...
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
