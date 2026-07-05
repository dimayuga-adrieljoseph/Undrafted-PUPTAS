<!--
  AuthenticatedLayout.vue
  ────────────────────────
  Single authenticated shell for all roles. Pass `variant` to select the
  correct sidebar nav set and derive role-specific chrome (title, label, extras).

  Replaces: AppLayout, ApplicantLayout, EvaluatorLayout,
            InterviewerLayout, RecordStaffLayout, SuperAdminLayout

  Props
  ─────
  variant   – mirrors Sidebar's variant prop (same accepted values)
              'default' | 'superadmin' | 'record' | 'interviewer'
              'evaluator' | 'document_evaluator' | 'applicant'

  Slots
  ─────
  #title          – override the default page heading
  #header-actions – inject extra buttons between the built-ins and the user pill

  Usage
  ─────
  <AuthenticatedLayout variant="applicant">
    <template #title>My Page</template>
    <YourPageContent />
  </AuthenticatedLayout>
-->
<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faMoon, faSun } from '@fortawesome/free-solid-svg-icons'

import Sidebar from '@/Components/Sidebar.vue'
import Footer from '@/Components/Footer.vue'
import ApplicantHelpButtons from '@/Components/ApplicantHelpButtons.vue'
import TermsandConditionsModal from '@/Pages/Modal/TermsandConditionsModal.vue'
import { useLayout } from '@/Composables/useLayout'

library.add(faMoon, faSun)

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    /**
     * Controls Sidebar nav set + derives role label, default title, and
     * which header extras are shown.
     */
    variant: {
        type: String,
        default: 'default',
        validator: (v) =>
            ['default', 'superadmin', 'record', 'interviewer', 'evaluator', 'document_evaluator', 'applicant'].includes(v),
    },
})

// ─── Composable ───────────────────────────────────────────────────────────────
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

// ─── Variant config map ───────────────────────────────────────────────────────
/**
 * Each entry drives the variant-specific chrome without any conditional
 * branches in the template.
 *
 * backgroundClass  – Tailwind gradient applied to the root wrapper
 * defaultTitle     – h1 text when the #title slot is not used
 * roleLabel        – subtitle in the user pill (null = derive from user data)
 * showBackToAdmin  – show "Back to Admin" link when the user is admin/superadmin
 * showApplicantHelp– show FAQ + Application Process buttons (applicant only)
 */
const VARIANT_CONFIG = {
    default: {
        backgroundClass: 'bg-gradient-to-br from-[#faf6f2] to-[#f1ebe6]',
        defaultTitle: 'Admin Portal',
        roleLabel: 'Administrator',
        showBackToAdmin: false,
        showApplicantHelp: false,
    },
    superadmin: {
        backgroundClass: 'bg-gradient-to-br from-purple-50 to-[#faf6f2]',
        defaultTitle: 'Superadmin Dashboard',
        roleLabel: null, // computed below (Admin vs Superadmin)
        showBackToAdmin: false,
        showApplicantHelp: false,
    },
    record: {
        backgroundClass: 'bg-gradient-to-br from-[#faf6f2] to-[#f1ebe6]',
        defaultTitle: 'Record Management',
        roleLabel: 'Record Staff',
        showBackToAdmin: false,
        showApplicantHelp: false,
    },
    interviewer: {
        backgroundClass: 'bg-gradient-to-br from-orange-50 to-[#faf6f2]',
        defaultTitle: 'Interviewer Panel',
        roleLabel: 'Interviewer',
        showBackToAdmin: true,
        showApplicantHelp: false,
    },
    evaluator: {
        backgroundClass: 'bg-gradient-to-br from-orange-100 to-[#faf6f2]',
        defaultTitle: 'Grade Evaluator Workspace',
        roleLabel: 'Grade Evaluator',
        showBackToAdmin: true,
        showApplicantHelp: false,
    },
    document_evaluator: {
        backgroundClass: 'bg-gradient-to-br from-orange-100 to-[#faf6f2]',
        defaultTitle: 'Document Evaluator Workspace',
        roleLabel: 'Document Evaluator',
        showBackToAdmin: true,
        showApplicantHelp: false,
    },
    applicant: {
        backgroundClass: 'bg-gradient-to-br from-orange-50 to-[#faf6f2]',
        defaultTitle: 'Applicant Portal',
        roleLabel: 'Applicant',
        showBackToAdmin: false,
        showApplicantHelp: true,
    },
}

const config = computed(() => VARIANT_CONFIG[props.variant] ?? VARIANT_CONFIG.default)

// Superadmin role label: "Superadmin" for role 7, "Admin" for role 2
const roleLabel = computed(() => {
    if (config.value.roleLabel !== null) return config.value.roleLabel
    return user.value?.role_id === 7 ? 'Superadmin' : 'Admin'
})

// "Back to Admin" is only visible when the logged-in user is an admin or superadmin
const showBackLink = computed(
    () => config.value.showBackToAdmin && (user.value?.role_id === 2 || user.value?.role_id === 7),
)
</script>

<template>
    <div
        class="min-h-screen flex dark:from-gray-950 dark:to-gray-900"
        :class="config.backgroundClass"
    >
        <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
        <Sidebar :variant="variant" v-model:open="sidebarOpen" />

        <!-- ── Main area ───────────────────────────────────────────────────── -->
        <div class="flex-1 flex flex-col ml-0 md:ml-[var(--sidebar-width,5rem)]">

            <!-- ── Header ─────────────────────────────────────────────────── -->
            <header class="sticky top-0 z-40 h-16 px-3 sm:px-6 flex items-center justify-between bg-white/80 backdrop-blur border-b border-gray-200 dark:bg-gray-900/80 dark:border-gray-800">

                <!-- Left: hamburger + title -->
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
                        <h1 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-100">
                            {{ config.defaultTitle }}
                        </h1>
                    </slot>
                </div>

                <!-- Right: contextual actions + dark mode + user pill -->
                <div class="flex items-center gap-4">

                    <!-- "Back to Admin" — interviewer / evaluator panels for admins -->
                    <Link
                        v-if="showBackLink"
                        href="/dashboard"
                        class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-[#9E122C] bg-[#9E122C]/10 hover:bg-[#9E122C]/20 transition dark:text-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Admin
                    </Link>

                    <!-- Applicant: FAQ + Application Process buttons -->
                    <ApplicantHelpButtons v-if="config.showApplicantHelp" />

                    <!-- Per-page injected actions -->
                    <slot name="header-actions" />

                    <!-- Dark mode toggle -->
                    <button
                        class="w-9 h-9 rounded-lg flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition min-h-[44px] min-w-[44px]"
                        aria-label="Toggle dark mode"
                        @click="toggleDarkMode"
                    >
                        <FontAwesomeIcon
                            :icon="['fas', isDarkMode ? 'moon' : 'sun']"
                            class="text-gray-700 dark:text-gray-200"
                            aria-hidden="true"
                        />
                    </button>

                    <!-- User pill -->
                    <div class="flex items-center gap-3 px-3 py-1.5 rounded-full bg-white border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-700">
                        <div
                            class="w-9 h-9 rounded-full flex items-center justify-center bg-[#9E122C]/10 text-[#9E122C] font-semibold dark:text-white"
                            aria-hidden="true"
                        >
                            {{ user?.firstname?.charAt(0) }}{{ user?.lastname?.charAt(0) }}
                        </div>
                        <div class="hidden sm:block leading-tight">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ user?.firstname }} {{ user?.lastname }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ roleLabel }}</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ── Page content ────────────────────────────────────────────── -->
            <main class="flex-1 p-3 sm:p-6 overflow-y-auto">
                <div class="w-full rounded-2xl p-4 sm:p-6 bg-white min-h-[calc(100vh-12rem)] shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800">
                    <slot />
                </div>
            </main>

            <Footer />
        </div>

        <!-- ── Global loading overlay ──────────────────────────────────────── -->
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

        <!-- ── Privacy consent modal ────────────────────────────────────────── -->
        <TermsandConditionsModal
            :show="showPrivacyModal"
            :can-close="false"
            @accept="handlePrivacyAccept"
            @cancel="handlePrivacyCancel"
        />
    </div>
</template>
