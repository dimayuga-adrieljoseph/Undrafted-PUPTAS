<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faClock,
    faSave,
    faTrash,
    faCalendarAlt,
    faInfoCircle,
    faCheckCircle,
    faExclamationCircle,
} from '@fortawesome/free-solid-svg-icons'

library.add(faClock, faSave, faTrash, faCalendarAlt, faInfoCircle, faCheckCircle, faExclamationCircle)

const props = defineProps({
    cutoff_display: {
        type: String,
        default: null,
    },
    cutoff_raw: {
        type: String,
        default: null,
    },
    settings: {
        type: Object,
        default: () => ({ enable_qualified_programs_view: true })
    }
})

const page = usePage()

const flash = computed(() => page.props.flash ?? {})

// Form for saving a new cutoff
const form = useForm({
    cutoff_at: props.cutoff_raw ? props.cutoff_raw.slice(0, 16) : '',
})

const saveCutoff = () => {
    form.post(route('cutoff-settings.store'), {
        preserveScroll: true,
    })
}

// Separate form for clearing (DELETE)
const clearForm = useForm({})

const clearCutoff = () => {
    if (!confirm('Are you sure you want to clear the submission cutoff? Applicants will be able to submit without a deadline.')) return
    clearForm.delete(route('cutoff-settings.destroy'), {
        preserveScroll: true,
    })
}

// System Settings form
const systemForm = useForm({
    enable_qualified_programs_view: props.settings?.enable_qualified_programs_view ?? true,
})

const saveSystemSettings = () => {
    systemForm.post(route('cutoff-settings.system-update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Cutoff Settings" />
    <SuperAdminLayout>
        <div class="px-4 md:px-8 py-8 w-full">

            <!-- Header -->
            <div class="mb-8 flex items-center gap-4">
                <div class="p-3 bg-[#9E122C]/10 rounded-xl">
                    <FontAwesomeIcon icon="clock" class="h-6 w-6 text-[#9E122C]" />
                </div>
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Cutoff Settings</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1 text-sm">Configure the application submission deadline</p>
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

            <!-- Current Cutoff Status -->
            <div class="mb-8 bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 mb-3">
                    <FontAwesomeIcon icon="info-circle" class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Current Cutoff</h2>
                </div>

                <div v-if="cutoff_display" class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                        <FontAwesomeIcon icon="calendar-alt" class="w-5 h-5 text-amber-600 dark:text-amber-300" />
                    </div>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ cutoff_display }}</p>
                </div>
                <div v-else class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                        <FontAwesomeIcon icon="calendar-alt" class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 italic">No cutoff is currently set</p>
                </div>
            </div>

            <!-- Set Cutoff Form -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">
                    {{ cutoff_display ? 'Update Cutoff Datetime' : 'Set Cutoff Datetime' }}
                </h2>

                <form @submit.prevent="saveCutoff" class="flex flex-col sm:flex-row gap-4 items-start">
                    <div class="flex-1 w-full">
                        <label for="cutoff_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Deadline Date &amp; Time
                            <span class="text-gray-400 dark:text-gray-500 font-normal">(Asia/Manila timezone)</span>
                        </label>
                        <input
                            id="cutoff_at"
                            v-model="form.cutoff_at"
                            type="datetime-local"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition"
                            :class="{ 'border-red-400 focus:ring-red-400/40 focus:border-red-400': form.errors.cutoff_at }"
                        />
                        <p v-if="form.errors.cutoff_at" class="text-xs text-red-500 mt-1">
                            {{ form.errors.cutoff_at }}
                        </p>
                    </div>

                    <div class="flex items-end pb-0 sm:pb-0" :class="{ 'pt-6': !form.errors.cutoff_at, 'pt-0 sm:pt-6': form.errors.cutoff_at }">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-xl font-medium text-sm transition shadow-sm whitespace-nowrap"
                        >
                            <FontAwesomeIcon icon="save" class="w-4 h-4" />
                            {{ form.processing ? 'Saving...' : 'Save Cutoff' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Clear Cutoff -->
            <div
                v-if="cutoff_display"
                class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-red-200 dark:border-red-900/50"
            >
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-1">Clear Cutoff</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Removing the cutoff will re-open applications without a deadline.
                </p>
                <button
                    type="button"
                    :disabled="clearForm.processing"
                    @click="clearCutoff"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-xl font-medium text-sm transition shadow-sm"
                >
                    <FontAwesomeIcon icon="trash" class="w-4 h-4" />
                    {{ clearForm.processing ? 'Clearing...' : 'Clear Cutoff' }}
                </button>
                </button>
            </div>

            <!-- Other System Settings -->
            <div class="mt-8 bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Other Settings</h2>
                <form @submit.prevent="saveSystemSettings" class="space-y-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/30 rounded-xl border border-gray-100 dark:border-gray-700">
                        <div class="mr-4">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Enable Qualified Programs View for Applicants</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                When enabled, applicants can track their qualified programs and the available slots on their dashboard.
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input
                                type="checkbox"
                                v-model="systemForm.enable_qualified_programs_view"
                                class="sr-only peer"
                            >
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#9E122C]/20 dark:peer-focus:ring-[#9E122C]/30 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[#9E122C]"></div>
                        </label>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="systemForm.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-xl font-medium text-sm transition shadow-sm"
                        >
                            <FontAwesomeIcon icon="save" class="w-4 h-4" />
                            {{ systemForm.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </SuperAdminLayout>
</template>
