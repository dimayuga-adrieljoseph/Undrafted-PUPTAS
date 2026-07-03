<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import ChangesConfirmationModal from '@/Components/ChangesConfirmationModal.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faSearch,
    faCheckCircle,
    faExclamationCircle,
    faExclamationTriangle,
    faTrash,
    faUserCheck,
    faChevronRight,
    faInfoCircle
} from '@fortawesome/free-solid-svg-icons'
import axios from 'axios'

library.add(faSearch, faCheckCircle, faExclamationCircle, faExclamationTriangle, faTrash, faUserCheck, faChevronRight, faInfoCircle)

const props = defineProps({
    allowed_scores: {
        type: Array,
        default: () => [],
    },
    allowed_emails: {
        type: Array,
        default: () => [],
    },
    cutoff_active: {
        type: Boolean,
        default: false,
    }
})

const activeTab = ref('score')

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

const searchScore = ref('')
const applicants = ref([])
const isSearching = ref(false)
const searchError = ref(null)

const handleSearch = async () => {
    if (!searchScore.value || isNaN(searchScore.value)) {
        searchError.value = "Please enter a valid numeric score."
        return
    }

    isSearching.value = true
    searchError.value = null
    applicants.value = []

    try {
        const response = await axios.post(route('score-overrides.search'), {
            score: searchScore.value
        })
        applicants.value = response.data.applicants
        if (applicants.value.length === 0) {
            searchError.value = "No applicants found with this score."
        }
    } catch (error) {
        searchError.value = error.response?.data?.message || "An error occurred while searching."
    } finally {
        isSearching.value = false
    }
}

const addForm = useForm({
    score: '',
    expires_at: '',
})

const deleteForm = useForm({
    score: '',
})

const confirmingAction = ref(null)
const confirmData = ref(null)

const confirmAddScore = () => {
    if (!searchScore.value || !addForm.expires_at) return;
    confirmingAction.value = 'add'
}

const confirmRemoveScore = (score) => {
    confirmingAction.value = 'remove'
    confirmData.value = score
}

const searchEmail = ref('')
const applicantsEmail = ref([])
const isSearchingEmail = ref(false)
const searchEmailError = ref(null)

const handleEmailSearch = async () => {
    if (!searchEmail.value) {
        searchEmailError.value = "Please enter a valid email."
        return
    }

    isSearchingEmail.value = true
    searchEmailError.value = null
    applicantsEmail.value = []

    try {
        const response = await axios.post(route('score-overrides.search-email'), {
            email: searchEmail.value
        })
        applicantsEmail.value = response.data.applicants
        if (applicantsEmail.value.length === 0) {
            searchEmailError.value = "No applicants found with this email."
        }
    } catch (error) {
        searchEmailError.value = error.response?.data?.message || "An error occurred while searching."
    } finally {
        isSearchingEmail.value = false
    }
}

const addEmailForm = useForm({
    email: '',
    expires_at: '',
})

const deleteEmailForm = useForm({
    email: '',
})

const confirmAddEmail = () => {
    if (!searchEmail.value || !addEmailForm.expires_at) return;
    confirmingAction.value = 'addEmail'
}

const confirmRemoveEmail = (email) => {
    confirmingAction.value = 'removeEmail'
    confirmData.value = email
}

const proceedAction = () => {
    if (confirmingAction.value === 'add') {
        addForm.score = searchScore.value
        addForm.post(route('score-overrides.store'), {
            preserveScroll: true,
            onSuccess: () => {
                searchScore.value = ''
                addForm.expires_at = ''
                applicants.value = []
                confirmingAction.value = null
            }
        })
    } else if (confirmingAction.value === 'remove') {
        deleteForm.score = confirmData.value
        deleteForm.delete(route('score-overrides.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                confirmingAction.value = null
                confirmData.value = null
            }
        })
    } else if (confirmingAction.value === 'addEmail') {
        addEmailForm.email = searchEmail.value
        addEmailForm.post(route('score-overrides.store-email'), {
            preserveScroll: true,
            onSuccess: () => {
                searchEmail.value = ''
                addEmailForm.expires_at = ''
                applicantsEmail.value = []
                confirmingAction.value = null
            }
        })
    } else if (confirmingAction.value === 'removeEmail') {
        deleteEmailForm.email = confirmData.value
        deleteEmailForm.delete(route('score-overrides.destroy-email'), {
            preserveScroll: true,
            onSuccess: () => {
                confirmingAction.value = null
                confirmData.value = null
            }
        })
    }
}

const getStatusBadgeClass = (statusId) => {
    // 1=Passed, 2=Waitlisted, 3=Unqualified, 4=Waitlisted Below Cutoff
    switch (statusId) {
        case 1: return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        case 2: return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
        case 3: return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
        case 4: return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
    }
}

</script>

<template>
    <Head title="Score Overrides" />
    <SuperAdminLayout>
        <div class="px-4 md:px-8 py-8 w-full max-w-7xl mx-auto">

            <!-- Header -->
            <div class="mb-8 flex items-center gap-4">
                <div class="p-3 bg-[#9E122C]/10 rounded-xl">
                    <FontAwesomeIcon icon="user-check" class="h-6 w-6 text-[#9E122C]" />
                </div>
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Registration Overrides</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1 text-sm">
                        Allow applicants with specific PUPCET scores or emails to bypass registration cutoff and status restrictions.
                    </p>
                </div>
            </div>

            <!-- Flash Messages -->
            <div class="mb-6">
                <div v-if="flash.success" class="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-300 mb-4">
                    <FontAwesomeIcon icon="check-circle" class="w-5 h-5 mt-0.5 flex-shrink-0" />
                    <p class="text-sm font-medium">{{ flash.success }}</p>
                </div>
                <div v-if="flash.error" class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-800 dark:text-red-300 mb-4">
                    <FontAwesomeIcon icon="exclamation-circle" class="w-5 h-5 mt-0.5 flex-shrink-0" />
                    <p class="text-sm font-medium">{{ flash.error }}</p>
                </div>
                
                <!-- Cutoff Warning -->
                <div v-if="cutoff_active" class="flex items-start gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-300">
                    <FontAwesomeIcon icon="exclamation-triangle" class="w-5 h-5 mt-0.5 flex-shrink-0" />
                    <div>
                        <p class="text-sm font-bold">Registration Cutoff is Active</p>
                        <p class="text-sm mt-1">
                            The global submission cutoff has passed. Adding a score override here will bypass this cutoff and allow applicants with that score to register and submit applications.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl mb-6 max-w-md">
                <button 
                    @click="activeTab = 'score'"
                    :class="activeTab === 'score' ? 'bg-white dark:bg-gray-700 shadow text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="flex-1 py-2 px-4 rounded-lg text-sm font-medium transition-all duration-200"
                >
                    Score Overrides
                </button>
                <button 
                    @click="activeTab = 'email'"
                    :class="activeTab === 'email' ? 'bg-white dark:bg-gray-700 shadow text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="flex-1 py-2 px-4 rounded-lg text-sm font-medium transition-all duration-200"
                >
                    Email Overrides
                </button>
            </div>

            <!-- SCORE TAB -->
            <div v-show="activeTab === 'score'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column: Search & Add -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Find Applicants by Score</h2>
                        
                        <div class="flex gap-3">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <FontAwesomeIcon icon="search" class="text-gray-400" />
                                </div>
                                <input 
                                    v-model="searchScore" 
                                    @keyup.enter="handleSearch"
                                    type="number" 
                                    step="0.01"
                                    placeholder="Enter PUPCET Score (e.g. 85.50)"
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition"
                                />
                            </div>
                            <button 
                                @click="handleSearch"
                                :disabled="isSearching || !searchScore"
                                class="px-5 py-2.5 bg-gray-900 dark:bg-gray-700 hover:bg-gray-800 dark:hover:bg-gray-600 disabled:opacity-50 text-white rounded-xl font-medium text-sm transition shadow-sm whitespace-nowrap"
                            >
                                {{ isSearching ? 'Searching...' : 'Search' }}
                            </button>
                        </div>
                        <p v-if="searchError" class="text-sm text-red-500 mt-2">{{ searchError }}</p>

                        <!-- Search Results -->
                        <div v-if="applicants.length > 0" class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200">
                                    Found {{ applicants.length }} applicant(s)
                                </h3>
                                <div class="flex items-start gap-3">
                                    <div class="flex flex-col">
                                        <label class="text-xs text-gray-500 mb-1 font-medium">Expiration Date (Required)</label>
                                        <input 
                                            v-model="addForm.expires_at"
                                            type="datetime-local" 
                                            class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white outline-none focus:ring-1 focus:ring-[#9E122C] focus:border-[#9E122C]"
                                        />
                                        <p v-if="addForm.errors.expires_at" class="text-xs text-red-500 mt-1 max-w-[200px]">{{ addForm.errors.expires_at }}</p>
                                    </div>
                                    <button 
                                        @click="confirmAddScore"
                                        :disabled="addForm.processing || !addForm.expires_at"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 text-white rounded-lg font-medium text-sm transition shadow-sm mt-[22px]"
                                    >
                                        <FontAwesomeIcon icon="check-circle" class="w-4 h-4" />
                                        {{ addForm.processing ? 'Allowing...' : 'Allow Registration for this Score' }}
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="w-full text-sm text-left">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Reference No.</th>
                                            <th scope="col" class="px-4 py-3">Name</th>
                                            <th scope="col" class="px-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="applicant in applicants" :key="applicant.test_passer_id" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                                {{ applicant.reference_number }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ applicant.surname }}, {{ applicant.first_name }} {{ applicant.middle_name }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="getStatusBadgeClass(applicant.passer_status_id)">
                                                    {{ applicant.passerStatus?.name || 'Unknown' }}
                                                </span>
                                                <span v-if="applicant.status === 'registered'" class="ml-2 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                                                    Registered
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Allowed Scores List -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Currently Allowed Scores</h2>
                        
                        <div v-if="allowed_scores.length === 0" class="text-center py-8">
                            <div class="text-gray-400 mb-2">
                                <FontAwesomeIcon icon="info-circle" class="w-8 h-8 opacity-50" />
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No score overrides are currently active.</p>
                        </div>
                        
                        <ul v-else class="space-y-3">
                            <li v-for="item in allowed_scores" :key="item.score" class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600">
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-[#9E122C]/10 text-[#9E122C] font-bold text-sm flex-shrink-0">
                                        {{ item.score }}
                                    </span>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Override Active
                                        </span>
                                        <span v-if="item.expires_at" class="text-xs text-gray-500 dark:text-gray-400">
                                            Expires: {{ new Date(item.expires_at).toLocaleString() }}
                                        </span>
                                        <span v-else class="text-xs text-gray-500 dark:text-gray-400">
                                            Never expires
                                        </span>
                                    </div>
                                </div>
                                <button 
                                    @click="confirmRemoveScore(item.score)"
                                    :disabled="deleteForm.processing"
                                    class="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 p-2 rounded-lg transition ml-2"
                                    title="Revoke Access"
                                >
                                    <FontAwesomeIcon icon="trash" class="w-4 h-4" />
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- EMAIL TAB -->
            <div v-show="activeTab === 'email'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column: Search & Add -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Find Applicants by Email</h2>
                        
                        <div class="flex gap-3">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <FontAwesomeIcon icon="search" class="text-gray-400" />
                                </div>
                                <input 
                                    v-model="searchEmail" 
                                    @keyup.enter="handleEmailSearch"
                                    type="email" 
                                    placeholder="Enter Applicant Email"
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition"
                                />
                            </div>
                            <button 
                                @click="handleEmailSearch"
                                :disabled="isSearchingEmail || !searchEmail"
                                class="px-5 py-2.5 bg-gray-900 dark:bg-gray-700 hover:bg-gray-800 dark:hover:bg-gray-600 disabled:opacity-50 text-white rounded-xl font-medium text-sm transition shadow-sm whitespace-nowrap"
                            >
                                {{ isSearchingEmail ? 'Searching...' : 'Search' }}
                            </button>
                        </div>
                        <p v-if="searchEmailError" class="text-sm text-red-500 mt-2">{{ searchEmailError }}</p>

                        <!-- Search Results -->
                        <div v-if="applicantsEmail.length > 0" class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200">
                                    Found {{ applicantsEmail.length }} applicant(s)
                                </h3>
                                <div class="flex items-start gap-3">
                                    <div class="flex flex-col">
                                        <label class="text-xs text-gray-500 mb-1 font-medium">Expiration Date (Required)</label>
                                        <input 
                                            v-model="addEmailForm.expires_at"
                                            type="datetime-local" 
                                            class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white outline-none focus:ring-1 focus:ring-[#9E122C] focus:border-[#9E122C]"
                                        />
                                        <p v-if="addEmailForm.errors.expires_at" class="text-xs text-red-500 mt-1 max-w-[200px]">{{ addEmailForm.errors.expires_at }}</p>
                                    </div>
                                    <button 
                                        @click="confirmAddEmail"
                                        :disabled="addEmailForm.processing || !addEmailForm.expires_at"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 text-white rounded-lg font-medium text-sm transition shadow-sm mt-[22px]"
                                    >
                                        <FontAwesomeIcon icon="check-circle" class="w-4 h-4" />
                                        {{ addEmailForm.processing ? 'Allowing...' : 'Allow Registration' }}
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="w-full text-sm text-left">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Reference No.</th>
                                            <th scope="col" class="px-4 py-3">Email</th>
                                            <th scope="col" class="px-4 py-3">Name</th>
                                            <th scope="col" class="px-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="applicant in applicantsEmail" :key="applicant.test_passer_id" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                                {{ applicant.reference_number }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ applicant.email }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ applicant.surname }}, {{ applicant.first_name }} {{ applicant.middle_name }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="getStatusBadgeClass(applicant.passer_status_id)">
                                                    {{ applicant.passerStatus?.name || 'Unknown' }}
                                                </span>
                                                <span v-if="applicant.status === 'registered'" class="ml-2 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                                                    Registered
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Allowed Emails List -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Allowed Emails</h2>
                        
                        <div v-if="allowed_emails.length === 0" class="text-center py-8">
                            <div class="text-gray-400 mb-2">
                                <FontAwesomeIcon icon="info-circle" class="w-8 h-8 opacity-50" />
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No email overrides active.</p>
                        </div>
                        
                        <ul v-else class="space-y-3">
                            <li v-for="item in allowed_emails" :key="item.email" class="flex flex-col p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex flex-col break-all">
                                        <span class="text-sm font-bold text-gray-900 dark:text-white">
                                            {{ item.email }}
                                        </span>
                                        <span v-if="item.expires_at" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Expires: {{ new Date(item.expires_at).toLocaleString() }}
                                        </span>
                                        <span v-else class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Never expires
                                        </span>
                                    </div>
                                    <button 
                                        @click="confirmRemoveEmail(item.email)"
                                        :disabled="deleteEmailForm.processing"
                                        class="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 p-2 rounded-lg transition ml-2 flex-shrink-0"
                                        title="Revoke Access"
                                    >
                                        <FontAwesomeIcon icon="trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <ChangesConfirmationModal
            :show="confirmingAction !== null"
            :title="confirmingAction === 'add' || confirmingAction === 'addEmail' ? 'Confirm Override' : 'Revoke Override'"
            :subtitle="confirmingAction === 'add' || confirmingAction === 'addEmail' ? 'Are you sure you want to allow this override?' : 'Are you sure you want to revoke this override?'"
            :loading="confirmingAction === 'add' ? addForm.processing : (confirmingAction === 'addEmail' ? addEmailForm.processing : (confirmingAction === 'removeEmail' ? deleteEmailForm.processing : deleteForm.processing))"
            :confirmText="confirmingAction === 'add' || confirmingAction === 'addEmail' ? 'Confirm Allow' : 'Confirm Revoke'"
            :confirmButtonClass="confirmingAction === 'add' || confirmingAction === 'addEmail' ? 'bg-[#9E122C] hover:bg-[#800918] text-white' : 'bg-red-600 hover:bg-red-700 text-white'"
            hideTable
            @confirm="proceedAction"
            @cancel="confirmingAction = null; confirmData = null"
        >
            <template #content>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <p v-if="confirmingAction === 'add'">
                        You are about to allow applicants with a score of <strong>{{ searchScore }}</strong> to bypass the registration cutoff until <strong>{{ new Date(addForm.expires_at).toLocaleString() }}</strong>.
                    </p>
                    <p v-if="confirmingAction === 'remove'">
                        You are about to revoke the registration bypass for applicants with a score of <strong>{{ confirmData }}</strong>. They will no longer be able to register if the cutoff is active.
                    </p>
                    <p v-if="confirmingAction === 'addEmail'">
                        You are about to allow the applicant with email <strong>{{ searchEmail }}</strong> to bypass the registration cutoff until <strong>{{ new Date(addEmailForm.expires_at).toLocaleString() }}</strong>.
                    </p>
                    <p v-if="confirmingAction === 'removeEmail'">
                        You are about to revoke the registration bypass for the email <strong>{{ confirmData }}</strong>. They will no longer be able to register if the cutoff is active.
                    </p>
                </div>
            </template>
        </ChangesConfirmationModal>
    </SuperAdminLayout>
</template>
