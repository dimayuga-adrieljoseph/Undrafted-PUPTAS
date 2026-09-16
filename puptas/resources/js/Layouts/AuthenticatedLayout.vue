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
import { faMoon, faSun, faEye, faEyeSlash } from '@fortawesome/free-solid-svg-icons'

import Sidebar from '@/Components/Sidebar.vue'
import Footer from '@/Components/Footer.vue'
import ApplicantHelpButtons from '@/Components/ApplicantHelpButtons.vue'
import TermsandConditionsModal from '@/Pages/Modal/TermsandConditionsModal.vue'
import { useLayout } from '@/Composables/useLayout'
import { useMaskingState } from '@/Composables/useMaskingState'

library.add(faMoon, faSun, faEye, faEyeSlash)

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

const {
    isUnmasked,
    showComplianceModal,
    closeComplianceModal,
    confirmUnmask,
    toggleMasking,
} = useMaskingState()

// Roles that may unmask PII: admins + all operational staff who look up applicants by name
const UNMASK_ALLOWED_ROLES = [2, 3, 4, 5, 6, 7, 8] // Admin, DocEval, Interviewer, Medical, Registrar, SuperAdmin, GradeEval
const canToggleMasking = computed(
    () => UNMASK_ALLOWED_ROLES.includes(user.value?.role_id),
)

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
        showBackToAdmin: true,
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
        class="min-h-screen flex overflow-x-hidden dark:from-gray-950 dark:to-gray-900"
        :class="config.backgroundClass"
    >
        <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
        <Sidebar :variant="variant" v-model:open="sidebarOpen" />

        <!-- ── Main area ───────────────────────────────────────────────────── -->
        <div class="w-full min-w-0 flex flex-col md:ml-[var(--sidebar-width,5rem)]">

            <!-- ── Header ─────────────────────────────────────────────────── -->
            <header class="sticky top-0 z-40 h-16 px-3 sm:px-6 flex items-center justify-between bg-white/80 backdrop-blur border-b border-gray-200 dark:bg-gray-900/80 dark:border-gray-800">

                <!-- Left: hamburger + title -->
                <div class="flex items-center gap-3 flex-1 min-w-0">
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
                <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">

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

                    <!-- Universal PII Masking Toggle (Admin/Superadmin only) -->
                    <button
                        v-if="canToggleMasking"
                        type="button"
                        @click="toggleMasking"
                        class="w-9 h-9 rounded-lg flex items-center justify-center transition min-h-[44px] min-w-[44px] cursor-pointer"
                        :class="isUnmasked
                            ? 'bg-[#9E122C]/10 hover:bg-[#9E122C]/20 text-[#9E122C] dark:bg-[#9E122C]/20 dark:hover:bg-[#9E122C]/30 dark:text-[#ff6b81]'
                            : 'bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-200'"
                        :title="isUnmasked ? 'PII Unmasked (Click to mask)' : 'PII Masked (Click to reveal)'"
                        :aria-label="isUnmasked ? 'PII Unmasked (Click to mask)' : 'PII Masked (Click to reveal)'"
                    >
                        <FontAwesomeIcon
                            :icon="['fas', isUnmasked ? 'eye' : 'eye-slash']"
                            class="text-sm"
                            aria-hidden="true"
                        />
                    </button>

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
                    <component
                        :is="variant === 'applicant' ? Link : 'div'"
                        v-bind="variant === 'applicant' ? { href: route('applicant.profile') } : {}"
                        class="flex items-center gap-3 px-3 py-1.5 rounded-full bg-white border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-700 transition-colors"
                        :class="variant === 'applicant' ? 'hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer' : ''"
                        :aria-label="variant === 'applicant' ? 'View your profile' : undefined"
                    >
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
                    </component>
                </div>
            </header>

            <!-- ── Page content ────────────────────────────────────────────── -->
            <main class="flex-1 p-3 sm:p-6 overflow-y-auto overflow-x-hidden">
                <div class="w-full rounded-2xl p-4 sm:p-6 bg-white min-h-[calc(100vh-12rem)] shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800 overflow-x-hidden">
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

        <!-- ── Universal PII Unmasking Compliance Modal (RA 10173) ──────────── -->
        <Teleport to="body">
            <div
                v-if="showComplianceModal"
                class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50"
                role="dialog"
                aria-modal="true"
                aria-labelledby="pii-unmask-title"
                @click.self="closeComplianceModal"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-[#9E122C]/10 text-[#9E122C] flex items-center justify-center flex-shrink-0">
                                <FontAwesomeIcon :icon="['fas', 'eye']" class="text-sm" />
                            </div>
                            <div>
                                <h2 id="pii-unmask-title" class="text-base font-bold text-gray-900 dark:text-gray-100">
                                    Reveal Personal Information
                                </h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Compliance Authorization (RA 10173)
                                </p>
                            </div>
                        </div>
                        <button
                            @click="closeComplianceModal"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition text-gray-500 dark:text-gray-400 cursor-pointer"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-5 space-y-4 text-sm text-gray-600 dark:text-gray-300">
                        <p class="leading-relaxed">
                            You are about to unmask applicant and user personal information across administrative pages for this active session.
                        </p>
                        <div class="bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 rounded-xl p-3.5 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            <strong class="text-gray-900 dark:text-gray-200">Compliance Notice:</strong> This action will be permanently logged in the Security Audit Trail with your account and timestamp. This toggle will automatically reset to masked when you refresh or close your session.
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 p-5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                        <button
                            type="button"
                            @click="closeComplianceModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl border border-gray-300 dark:border-gray-600 transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="confirmUnmask"
                            class="px-4 py-2 text-sm font-medium text-white bg-[#9E122C] hover:bg-[#800918] rounded-xl shadow-xs transition cursor-pointer"
                        >
                            Confirm &amp; Reveal
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
