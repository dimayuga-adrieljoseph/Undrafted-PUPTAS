<script setup>
import Sidebar from '@/Components/Sidebar.vue'
import Footer from '@/Components/Footer.vue'
import TermsandConditionsModal from '@/Pages/Modal/TermsandConditionsModal.vue'
import { useLayout } from '@/Composables/useLayout'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faMoon, faSun } from '@fortawesome/free-solid-svg-icons'

library.add(faMoon, faSun)

const {
    user,
    isLoading,
    isDarkMode,
    toggleDarkMode,
    showPrivacyModal,
    handlePrivacyAccept,
    handlePrivacyCancel,
    sidebarOpen,
} = useLayout()
</script>

<template>
    <div class="min-h-screen flex bg-gradient-to-br from-orange-50 to-[#faf6f2] dark:from-gray-950 dark:to-gray-900">

        <Sidebar variant="applicant" v-model:open="sidebarOpen" />

        <div class="flex-1 flex flex-col ml-0 md:ml-[var(--sidebar-width,5rem)]">

            <header class="sticky top-0 z-40 h-16 px-3 sm:px-6 flex items-center justify-between bg-white/80 backdrop-blur border-b border-gray-200 dark:bg-gray-900/80 dark:border-gray-800">
                <div class="flex items-center gap-4">
                    <button
                        class="md:hidden min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition"
                        aria-label="Open navigation menu"
                        @click="sidebarOpen = true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <slot name="title">
                        <h1 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-100">Applicant Portal</h1>
                    </slot>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Per-page header actions (e.g. FAQ button) -->
                    <slot name="header-actions" />

                    <button
                        class="w-9 h-9 rounded-lg flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition min-h-[44px] min-w-[44px]"
                        aria-label="Toggle dark mode"
                        @click="toggleDarkMode"
                    >
                        <FontAwesomeIcon :icon="['fas', isDarkMode ? 'moon' : 'sun']" class="text-gray-700 dark:text-gray-200" aria-hidden="true" />
                    </button>

                    <div class="flex items-center gap-3 px-3 py-1.5 rounded-full bg-white border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-700">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center bg-[#9E122C]/10 text-[#9E122C] font-semibold dark:text-white" aria-hidden="true">
                            {{ user?.firstname?.charAt(0) }}{{ user?.lastname?.charAt(0) }}
                        </div>
                        <div class="hidden sm:block leading-tight">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ user?.firstname }} {{ user?.lastname }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Applicant</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-3 sm:p-6 overflow-y-auto">
                <div class="w-full rounded-2xl p-4 sm:p-6 bg-white min-h-[calc(100vh-12rem)] shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800">
                    <slot />
                </div>
            </main>

            <Footer />
        </div>

        <div
            v-if="isLoading"
            class="fixed inset-0 z-[999] bg-black/40 backdrop-blur-sm flex items-center justify-center"
            aria-live="polite"
            aria-label="Loading"
        >
            <div class="px-6 py-4 rounded-xl bg-white shadow-lg dark:bg-gray-900 flex flex-col items-center gap-3">
                <svg class="animate-spin h-8 w-8 text-[#9E122C] dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-50" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span class="font-medium text-gray-800 dark:text-gray-100">Loading, please wait...</span>
            </div>
        </div>

        <TermsandConditionsModal
            :show="showPrivacyModal"
            :can-close="false"
            @accept="handlePrivacyAccept"
            @cancel="handlePrivacyCancel"
        />
    </div>
</template>
