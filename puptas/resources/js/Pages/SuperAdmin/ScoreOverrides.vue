<script setup>
import { ref, computed, watch } from 'vue'
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
    faChevronDown,
    faInfoCircle
} from '@fortawesome/free-solid-svg-icons'
import axios from 'axios'

library.add(faSearch, faCheckCircle, faExclamationCircle, faExclamationTriangle, faTrash, faUserCheck, faChevronRight, faChevronDown, faInfoCircle)

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
const selectedEmails = ref(JSON.parse(sessionStorage.getItem('score_overrides_selected_emails') || '[]'))

const probationApplicants = ref([])
const isLoadingProbation = ref(false)
const probationError = ref(null)
const showProbationPanel = ref(false)

const loadProbationApplicants = async () => {
    if (showProbationPanel.value && probationApplicants.value.length > 0) {
        showProbationPanel.value = !showProbationPanel.value
        return
    }
    
    showProbationPanel.value = !showProbationPanel.value
    if (!showProbationPanel.value) return

    isLoadingProbation.value = true
    probationError.value = null

    try {
        const response = await axios.get(route('score-overrides.probation-applicants'))
        probationApplicants.value = response.data.applicants
        if (probationApplicants.value.length === 0) {
            probationError.value = "No applicants found on probation."
        }
    } catch (error) {
        probationError.value = error.response?.data?.message || "An error occurred while fetching."
    } finally {
        isLoadingProbation.value = false
    }
}

const addAllProbationApplicants = () => {
    const duplicates = []
    probationApplicants.value.forEach(applicant => {
        if (selectedEmails.value.find(e => e.email === applicant.email)) return
        const existing = isAlreadyAllowed(applicant.email)
        if (existing) {
            duplicates.push({ applicant, existingEntry: existing })
        } else {
            selectedEmails.value.push(applicant)
        }
    })
    if (duplicates.length > 0) {
        const first = duplicates.shift()
        duplicateWarning.value = { applicant: first.applicant, existingEntry: first.existingEntry }
        duplicateBulkQueue.value = duplicates
    }
}

watch(selectedEmails, (newVal) => {
    sessionStorage.setItem('score_overrides_selected_emails', JSON.stringify(newVal))
}, { deep: true })

const handleEmailSearch = async () => {
    if (!searchEmail.value) {
        searchEmailError.value = "Please enter a valid search term."
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
            searchEmailError.value = "No applicants found."
        }
    } catch (error) {
        searchEmailError.value = error.response?.data?.message || "An error occurred while searching."
    } finally {
        isSearchingEmail.value = false
    }
}

// Duplicate email warning state
const duplicateWarning = ref(null) // { applicant, existingEntry } | null
const duplicateBulkQueue = ref([])  // for bulk "add all" duplicates

const isAlreadyAllowed = (email) => {
    return props.allowed_emails.find(e => e.email.toLowerCase() === email.toLowerCase()) || null
}

const addToSelection = (applicant) => {
    // Already in staging — ignore
    if (selectedEmails.value.find(e => e.email === applicant.email)) return

    const existing = isAlreadyAllowed(applicant.email)
    if (existing) {
        // Show duplicate warning for this single applicant
        duplicateWarning.value = { applicant, existingEntry: existing }
        return
    }
    selectedEmails.value.push(applicant)
}

const proceedAddDespiteDuplicate = () => {
    if (duplicateWarning.value) {
        if (!selectedEmails.value.find(e => e.email === duplicateWarning.value.applicant.email)) {
            selectedEmails.value.push(duplicateWarning.value.applicant)
        }
        duplicateWarning.value = null
    }
    // Drain any remaining bulk-queue duplicates
    if (duplicateBulkQueue.value.length > 0) {
        const next = duplicateBulkQueue.value.shift()
        duplicateWarning.value = { applicant: next.applicant, existingEntry: next.existingEntry }
    }
}

const skipDuplicate = () => {
    duplicateWarning.value = null
    if (duplicateBulkQueue.value.length > 0) {
        const next = duplicateBulkQueue.value.shift()
        duplicateWarning.value = { applicant: next.applicant, existingEntry: next.existingEntry }
    }
}

const skipAllDuplicates = () => {
    duplicateWarning.value = null
    duplicateBulkQueue.value = []
}

const removeFromSelection = (email) => {
    selectedEmails.value = selectedEmails.value.filter(e => e.email !== email)
}

const allowedEmailsSearch = ref('')
const allowedEmailsCurrentPage = ref(1)
const allowedEmailsItemsPerPage = 5

const filteredAllowedEmails = computed(() => {
    if (!allowedEmailsSearch.value) return props.allowed_emails;
    const query = allowedEmailsSearch.value.toLowerCase();
    return props.allowed_emails.filter(item => item.email.toLowerCase().includes(query));
});

const paginatedAllowedEmails = computed(() => {
    const start = (allowedEmailsCurrentPage.value - 1) * allowedEmailsItemsPerPage;
    return filteredAllowedEmails.value.slice(start, start + allowedEmailsItemsPerPage);
});

const allowedEmailsTotalPages = computed(() => {
    return Math.ceil(filteredAllowedEmails.value.length / allowedEmailsItemsPerPage);
});

watch(allowedEmailsSearch, () => {
    allowedEmailsCurrentPage.value = 1;
});

const addEmailForm = useForm({
    emails: [],
    expires_at: '',
})

const deleteEmailForm = useForm({
    email: '',
})

const confirmAddEmail = () => {
    if (selectedEmails.value.length === 0 || !addEmailForm.expires_at) return;
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
        addEmailForm.emails = selectedEmails.value.map(a => a.email)
        addEmailForm.post(route('score-overrides.store-email'), {
            preserveScroll: true,
            onSuccess: () => {
                searchEmail.value = ''
                addEmailForm.expires_at = ''
                applicantsEmail.value = []
                selectedEmails.value = []
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
    <Head title="Registration Overrides" />
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
                    Name & Email Overrides
                </button>
            </div>

            <!-- SCORE TAB -->
            <div v-if="activeTab === 'score'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column: Search & Add -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Find Applicants by Score</h2>
                        
                        <div class="flex gap-3">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none z-10">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input 
                                    v-model="searchScore" 
                                    @keyup.enter="handleSearch"
                                    type="text"
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
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium capitalize" :class="getStatusBadgeClass(applicant.passer_status_id)">
                                                    {{ applicant.passer_status?.status || 'Unknown' }}
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
                        
                        <ul v-else class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
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
            <div v-if="activeTab === 'email'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column: Search & Add -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Probation Bulk Add Panel -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-[#9E122C]/20 dark:border-[#9E122C]/40 overflow-hidden">
                        <div 
                            @click="loadProbationApplicants"
                            class="flex items-center justify-between p-4 bg-red-50/50 dark:bg-[#9E122C]/10 cursor-pointer hover:bg-red-50 dark:hover:bg-[#9E122C]/20 transition-colors"
                        >
                            <div class="flex items-center gap-3">
                                <div class="bg-white dark:bg-gray-800 p-2 rounded-lg shadow-sm border border-red-100 dark:border-red-900/30">
                                    <FontAwesomeIcon icon="user-check" class="w-5 h-5 text-[#9E122C]" />
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-gray-900 dark:text-white">On Probation Applicants</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Bulk load and select all applicants with "On Probation" status</p>
                                </div>
                            </div>
                            <FontAwesomeIcon 
                                :icon="showProbationPanel ? 'chevron-down' : 'chevron-right'" 
                                class="w-4 h-4 text-gray-400 transition-transform duration-200"
                            />
                        </div>

                        <div v-show="showProbationPanel" class="p-5 border-t border-[#9E122C]/10 dark:border-[#9E122C]/20 bg-white dark:bg-gray-800">
                            <div v-if="isLoadingProbation" class="flex justify-center py-8">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#9E122C]"></div>
                            </div>
                            
                            <div v-else-if="probationError" class="text-center py-6 text-sm text-gray-500">
                                {{ probationError }}
                            </div>
                            
                            <div v-else-if="probationApplicants.length > 0">
                                <div class="flex items-center justify-between mb-4">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Found <strong class="text-[#9E122C]">{{ probationApplicants.length }}</strong> applicants
                                    </span>
                                    <button 
                                        @click="addAllProbationApplicants"
                                        class="px-4 py-2 bg-[#9E122C]/10 text-[#9E122C] hover:bg-[#9E122C]/20 rounded-lg text-sm font-semibold transition flex items-center gap-2"
                                    >
                                        <FontAwesomeIcon icon="check-circle" class="w-4 h-4" />
                                        Select All {{ probationApplicants.length }} Applicants
                                    </button>
                                </div>
                                
                                <div class="max-h-60 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="w-full text-sm text-left">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700/50 dark:text-gray-300 sticky top-0 shadow-sm z-10">
                                            <tr>
                                                <th scope="col" class="px-4 py-2.5">Email / Name</th>
                                                <th scope="col" class="px-4 py-2.5 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="applicant in probationApplicants" :key="applicant.test_passer_id" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-4 py-2">
                                                    <div class="font-medium text-gray-900 dark:text-white">{{ applicant.email }}</div>
                                                    <div class="text-xs text-gray-500">{{ applicant.surname }}, {{ applicant.first_name }}</div>
                                                </td>
                                                <td class="px-4 py-2 text-right">
                                                    <button 
                                                        v-if="!selectedEmails.find(e => e.email === applicant.email)"
                                                        @click="addToSelection(applicant)"
                                                        class="px-3 py-1 bg-[#9E122C]/10 text-[#9E122C] hover:bg-[#9E122C]/20 rounded-md text-xs font-semibold transition"
                                                    >
                                                        Add
                                                    </button>
                                                    <span v-else class="text-xs text-green-600 dark:text-green-400 font-medium bg-green-50 dark:bg-green-900/20 px-2 py-1 rounded-md border border-green-200 dark:border-green-800">Selected</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Find Applicants by Name or Email</h2>
                        
                        <div class="flex gap-3">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none z-10">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input 
                                    v-model="searchEmail" 
                                    @keyup.enter="handleEmailSearch"
                                    type="text" 
                                    placeholder="Enter partial Email or Name (e.g. John, john@gmail.com)"
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
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="w-full text-sm text-left">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Email</th>
                                            <th scope="col" class="px-4 py-3">Name</th>
                                            <th scope="col" class="px-4 py-3">Status</th>
                                            <th scope="col" class="px-4 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="applicant in applicantsEmail" :key="applicant.test_passer_id" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                                {{ applicant.email }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ applicant.surname }}, {{ applicant.first_name }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-1 rounded-full text-xs font-medium capitalize" :class="getStatusBadgeClass(applicant.passer_status_id)">
                                                    {{ applicant.passer_status?.status || 'Unknown' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <button 
                                                    v-if="!selectedEmails.find(e => e.email === applicant.email)"
                                                    @click="addToSelection(applicant)"
                                                    class="px-3 py-1.5 bg-[#9E122C]/10 text-[#9E122C] hover:bg-[#9E122C]/20 rounded-md text-xs font-semibold transition"
                                                >
                                                    Add
                                                </button>
                                                <span v-else class="text-xs text-gray-500 font-medium">Selected</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Selected Applicants Staging Area -->
                        <div v-if="selectedEmails.length > 0" class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4">
                                Selected Applicants ({{ selectedEmails.length }})
                            </h3>
                            
                            <ul class="space-y-2 mb-6">
                                <li v-for="item in selectedEmails" :key="item.email" class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.email }}</span>
                                        <span class="text-xs text-gray-500">{{ item.surname }}, {{ item.first_name }}</span>
                                    </div>
                                    <button 
                                        @click="removeFromSelection(item.email)"
                                        class="text-red-500 hover:text-red-700 p-2"
                                    >
                                        <FontAwesomeIcon icon="trash" class="w-4 h-4" />
                                    </button>
                                </li>
                            </ul>

                            <div class="flex flex-col md:flex-row items-end justify-between gap-4 p-4 bg-gray-50 dark:bg-gray-700/30 rounded-xl border border-gray-200 dark:border-gray-700">
                                <div class="flex flex-col w-full md:w-auto">
                                    <label class="text-xs text-gray-500 mb-1 font-medium">Expiration Date (Required)</label>
                                    <input 
                                        v-model="addEmailForm.expires_at"
                                        type="datetime-local" 
                                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white outline-none focus:ring-1 focus:ring-[#9E122C] focus:border-[#9E122C]"
                                    />
                                    <p v-if="addEmailForm.errors.expires_at" class="text-xs text-red-500 mt-1">{{ addEmailForm.errors.expires_at }}</p>
                                </div>
                                <button 
                                    @click="confirmAddEmail"
                                    :disabled="addEmailForm.processing || !addEmailForm.expires_at"
                                    class="w-full md:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 text-white rounded-lg font-semibold text-sm transition shadow-sm"
                                >
                                    <FontAwesomeIcon icon="check-circle" class="w-4 h-4" />
                                    {{ addEmailForm.processing ? 'Allowing...' : 'Allow Registration for Selected' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Allowed Emails List -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col min-h-[500px]">
                        <div class="flex flex-col mb-4 gap-3">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Allowed Emails</h2>
                            
                            <!-- Search Input -->
                            <div class="relative" v-if="allowed_emails.length > 0">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none z-10">
                                    <FontAwesomeIcon icon="search" class="w-3.5 h-3.5 text-gray-400" />
                                </div>
                                <input 
                                    v-model="allowedEmailsSearch"
                                    type="text" 
                                    placeholder="Search emails..."
                                    class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-1 focus:ring-[#9E122C] focus:border-[#9E122C] outline-none transition"
                                />
                            </div>
                        </div>
                        
                        <div v-if="allowed_emails.length === 0" class="text-center py-8">
                            <div class="text-gray-400 mb-2">
                                <FontAwesomeIcon icon="info-circle" class="w-8 h-8 opacity-50" />
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No email overrides active.</p>
                        </div>
                        
                        <div v-else-if="filteredAllowedEmails.length === 0" class="text-center py-6 text-sm text-gray-500">
                            No emails match your search.
                        </div>
                        
                        <div v-else class="flex flex-col flex-1">
                            <ul class="space-y-3 flex-1 overflow-y-auto pr-2">
                                <li v-for="item in paginatedAllowedEmails" :key="item.email" class="flex flex-col p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600">
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

                            <!-- Pagination Controls -->
                            <div v-if="allowedEmailsTotalPages > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <button 
                                    @click="allowedEmailsCurrentPage--"
                                    :disabled="allowedEmailsCurrentPage === 1"
                                    class="px-3 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Prev
                                </button>
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                    Page {{ allowedEmailsCurrentPage }} of {{ allowedEmailsTotalPages }}
                                </span>
                                <button 
                                    @click="allowedEmailsCurrentPage++"
                                    :disabled="allowedEmailsCurrentPage === allowedEmailsTotalPages"
                                    class="px-3 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
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
                        You are about to allow <strong>{{ selectedEmails.length }}</strong> selected applicant(s) to bypass the registration cutoff until <strong>{{ new Date(addEmailForm.expires_at).toLocaleString() }}</strong>.
                    </p>
                    <p v-if="confirmingAction === 'removeEmail'">
                        You are about to revoke the registration bypass for the email <strong>{{ confirmData }}</strong>. They will no longer be able to register if the cutoff is active.
                    </p>
                </div>
            </template>
        </ChangesConfirmationModal>

        <!-- Duplicate Email Warning Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="duplicateWarning" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <!-- Backdrop -->
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="skipDuplicate"></div>

                    <!-- Modal -->
                    <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-amber-200 dark:border-amber-700 overflow-hidden">
                        <!-- Top accent bar -->
                        <div class="h-1.5 w-full bg-gradient-to-r from-amber-400 to-orange-400"></div>

                        <div class="p-6">
                            <!-- Icon + Title -->
                            <div class="flex items-start gap-4 mb-5">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                                    <FontAwesomeIcon icon="exclamation-triangle" class="w-5 h-5 text-amber-500" />
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Email Already Allowed</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        This email already has an active override entry.
                                    </p>
                                </div>
                            </div>

                            <!-- Details -->
                            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-5 space-y-2">
                                <div>
                                    <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">Email</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5 break-all">{{ duplicateWarning.applicant.email }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">Applicant</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-0.5">{{ duplicateWarning.applicant.surname }}, {{ duplicateWarning.applicant.first_name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">Current Expiry</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-0.5">
                                        {{ duplicateWarning.existingEntry.expires_at ? new Date(duplicateWarning.existingEntry.expires_at).toLocaleString() : 'Never expires' }}
                                    </p>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-5">
                                Proceeding will add this applicant to the selection. When you submit, their expiry date will be <strong class="text-gray-900 dark:text-white">updated</strong> to the new date you set.
                            </p>

                            <!-- Bulk queue info -->
                            <p v-if="duplicateBulkQueue.length > 0" class="text-xs text-gray-500 dark:text-gray-400 mb-4 bg-gray-100 dark:bg-gray-700 rounded-lg px-3 py-2">
                                <strong>{{ duplicateBulkQueue.length }}</strong> more duplicate(s) in queue.
                            </p>

                            <!-- Actions -->
                            <div class="flex flex-col sm:flex-row gap-2 justify-end">
                                <!-- Skip all (only when bulk) -->
                                <button
                                    v-if="duplicateBulkQueue.length > 0"
                                    @click="skipAllDuplicates"
                                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium"
                                >
                                    Skip All Duplicates
                                </button>
                                <button
                                    @click="skipDuplicate"
                                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium"
                                >
                                    Skip
                                </button>
                                <button
                                    @click="proceedAddDespiteDuplicate"
                                    class="px-4 py-2 text-sm rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold transition shadow-sm"
                                >
                                    Proceed &amp; Update Expiry
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </SuperAdminLayout>
</template>
