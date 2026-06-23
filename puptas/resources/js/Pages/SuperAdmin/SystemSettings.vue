<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faUserShield,
    faSave,
    faInfoCircle,
    faCheckCircle,
    faExclamationCircle,
} from '@fortawesome/free-solid-svg-icons'

library.add(faUserShield, faSave, faInfoCircle, faCheckCircle, faExclamationCircle)

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
})

const page = usePage()

const flash = computed(() => page.props.flash ?? {})

// Form for saving settings
const form = useForm({
    idp_down_emergency_login_enabled: props.settings.idp_down_emergency_login_enabled === '1',
})

const saveSettings = () => {
    form.post(route('system-settings.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Emergency Access" />
    <SuperAdminLayout>
        <div class="px-4 md:px-8 py-8 w-full">

            <!-- Header -->
            <div class="mb-8 flex items-center gap-4">
                <div class="p-3 bg-[#9E122C]/10 rounded-xl">
                    <FontAwesomeIcon icon="user-shield" class="h-6 w-6 text-[#9E122C]" />
                </div>
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Emergency Access</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1 text-sm">Configure global application behaviors and emergency fallbacks</p>
                </div>
            </div>

            <!-- Flash Messages -->
            <div class="mb-6">
                <div
                    v-if="flash.success"
                    class="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-300"
                >
                    <FontAwesomeIcon icon="check-circle" class="w-5 h-5 mt-0.5 flex-shrink-0" />
                    <p class="text-sm font-medium">{{ flash.success }}</p>
                </div>
                <div
                    v-if="flash.error"
                    class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-800 dark:text-red-300"
                >
                    <FontAwesomeIcon icon="exclamation-circle" class="w-5 h-5 mt-0.5 flex-shrink-0" />
                    <p class="text-sm font-medium">{{ flash.error }}</p>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                <form @submit.prevent="saveSettings" class="space-y-6">
                    
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex-shrink-0">
                                <FontAwesomeIcon icon="info-circle" class="w-5 h-5 text-amber-600 dark:text-amber-300" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Emergency Email OTP Login</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    When enabled, the system will bypass the external Identity Provider (IDP) and allow users to login locally via an Email OTP. Turn this on ONLY when the IDP is down or experiencing severe outages.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.idp_down_emergency_login_enabled" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#9E122C]/30 dark:peer-focus:ring-[#9E122C]/80 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[#9E122C]"></div>
                                <span class="ml-3 text-sm font-medium text-gray-900 dark:text-gray-300">
                                    {{ form.idp_down_emergency_login_enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-xl font-medium text-sm transition shadow-sm"
                        >
                            <FontAwesomeIcon icon="save" class="w-4 h-4" />
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </SuperAdminLayout>
</template>
