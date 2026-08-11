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
    faInfoCircle,
    faPencil,
    faXmark,
    faPlus,
} from '@fortawesome/free-solid-svg-icons'
import axios from 'axios'

library.add(faSearch, faCheckCircle, faExclamationCircle, faExclamationTriangle, faTrash, faUserCheck, faChevronRight, faChevronDown, faInfoCircle, faPencil, faXmark, faPlus)

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

// ── Score Range Override ──────────────────────────────────────────────────────

// Add form
const addRangeForm = useForm({
    score_from: '',
    score_to: '',
    expires_at: '',
})

// Edit state: track which entry is being edited
const editingId = ref(null)
const editForm = useForm({
    id: '',
    score_from: '',
    score_to: '',
    expires_at: '',
})

const startEdit = (item) => {
    editingId.value = item.id
    editForm.id         = item.id
    editForm.score_from = item.score_from
    editForm.score_to   = item.score_to
    editForm.expires_at = item.expires_at
        ? new Date(item.expires_at).toISOString().slice(0, 16)
        : ''
}

const cancelEdit = () => {
    editingId.value = null
    editForm.reset()
}

const submitEdit = () => {
    confirmingAction.value = 'editRange'
}

const deleteRangeForm = useForm({
    id: '',
})

// Preview applicants for the current add-form range
const rangeApplicants = ref([])
const isSearchingRange = ref(false)
const searchRangeError = ref(null)

const handleRangeSearch = async () => {
    const from = parseFloat(addRangeForm.score_from)
    const to   = parseFloat(addRangeForm.score_to)
    if (isNaN(from) || isNaN(to) || from < 1 || to > 150 || from > to) {
        searchRangeError.value = "Enter a valid range (1–150, from ≤ to)."
        return
    }

    isSearchingRange.value = true
    searchRangeError.value = null
    rangeApplicants.value  = []

    try {
        const response = await axios.post(route('score-overrides.search'), {
            score_from: from,
            score_to:   to,
        })
        rangeApplicants.value = response.data.applicants
        if (rangeApplicants.value.length === 0) {
            searchRangeError.value = "No applicants found in this range."
        }
    } catch (error) {
        searchRangeError.value = error.response?.data?.message || "An error occurred while searching."
    } finally {
        isSearchingRange.value = false
    }
}

const confirmingAction = ref(null)
const confirmData = ref(null)

const confirmAddRange = () => {
    const from = parseFloat(addRangeForm.score_from)
    const to   = parseFloat(addRangeForm.score_to)
    if (!addRangeForm.expires_at || isNaN(from) || isNaN(to)) return
    confirmingAction.value = 'addRange'
}

const confirmRemoveRange = (id) => {
    confirmingAction.value = 'removeRange'
    confirmData.value = id
}

const searchEmail = ref('')
const applicantsEmail = ref([])
const isSearchingEmail = ref(false)
const searchEmailError = ref(null)
const selectedEmails = ref(JSON.parse(sessionStorage.getItem('score_overrides_selected_emails') || '[]'))

// Single shared expiry for all selected emails (set once in the staging bar)
const emailExpiresAt = ref('')

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
const duplicateWarning = ref(null)
const duplicateBulkQueue = ref([])

const isAlreadyAllowed = (email) => {
    return props.allowed_emails.find(e => e.email.toLowerCase() === email.toLowerCase()) || null
}

const addToSelection = (applicant) => {
    if (selectedEmails.value.find(e => e.email === applicant.email)) return
    const existing = isAlreadyAllowed(applicant.email)
    if (existing) {
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

// ── Active email overrides table (inline edit) ────────────────────────────────
const allowedEmailsSearch = ref('')
const allowedEmailsCurrentPage = ref(1)
const allowedEmailsItemsPerPage = 10

const filteredAllowedEmails = computed(() => {
    if (!allowedEmailsSearch.value) return props.allowed_emails
    const q = allowedEmailsSearch.value.toLowerCase()
    return props.allowed_emails.filter(item =>
        item.email.toLowerCase().includes(q) ||
        (item.name && item.name.toLowerCase().includes(q))
    )
})

const paginatedAllowedEmails = computed(() => {
    const start = (allowedEmailsCurrentPage.value - 1) * allowedEmailsItemsPerPage
    return filteredAllowedEmails.value.slice(start, start + allowedEmailsItemsPerPage)
})

const allowedEmailsTotalPages = computed(() =>
    Math.ceil(filteredAllowedEmails.value.length / allowedEmailsItemsPerPage)
)

watch(allowedEmailsSearch, () => { allowedEmailsCurrentPage.value = 1 })

// Edit email override inline
const editingEmail = ref(null)
const editEmailForm = useForm({
    email: '',
    expires_at: '',
})

const startEditEmail = (item) => {
    editingEmail.value = item.email
    editEmailForm.email      = item.email
    editEmailForm.expires_at = item.expires_at
        ? new Date(item.expires_at).toISOString().slice(0, 16)
        : ''
}

const cancelEditEmail = () => {
    editingEmail.value = null
    editEmailForm.reset()
}

const submitEditEmail = () => {
    confirmingAction.value = 'editEmail'
}

const addEmailForm = useForm({
    emails: [],
    expires_at: '',
})

const deleteEmailForm = useForm({
    email: '',
})

const confirmAddEmail = () => {
    if (selectedEmails.value.length === 0 || !emailExpiresAt.value) return
    confirmingAction.value = 'addEmail'
}

const confirmRemoveEmail = (email) => {
    confirmingAction.value = 'removeEmail'
    confirmData.value = email
}

const proceedAction = () => {
    if (confirmingAction.value === 'addRange') {
        addRangeForm.post(route('score-overrides.store'), {
            preserveScroll: true,
            onSuccess: () => {
                addRangeForm.reset()
                rangeApplicants.value = []
                confirmingAction.value = null
            }
        })
    } else if (confirmingAction.value === 'editRange') {
        editForm.put(route('score-overrides.update'), {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null
                editForm.reset()
                confirmingAction.value = null
            }
        })
    } else if (confirmingAction.value === 'removeRange') {
        deleteRangeForm.id = confirmData.value
        deleteRangeForm.delete(route('score-overrides.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                confirmingAction.value = null
                confirmData.value = null
            }
        })
    } else if (confirmingAction.value === 'addEmail') {
        addEmailForm.emails = selectedEmails.value.map(a => ({
            email: a.email,
            name: a.surname ? `${a.surname}, ${a.first_name}` : null,
        }))
        addEmailForm.expires_at = emailExpiresAt.value
        addEmailForm.post(route('score-overrides.store-email'), {
            preserveScroll: true,
            onSuccess: () => {
                searchEmail.value = ''
                emailExpiresAt.value = ''
                applicantsEmail.value = []
                selectedEmails.value = []
                confirmingAction.value = null
            }
        })
    } else if (confirmingAction.value === 'editEmail') {
        editEmailForm.put(route('score-overrides.update-email'), {
            preserveScroll: true,
            onSuccess: () => {
                editingEmail.value = null
                editEmailForm.reset()
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
            <div v-if="activeTab === 'score'" class="space-y-6">

                <!-- Add New Range Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 mb-5">
                        <FontAwesomeIcon icon="plus" class="w-4 h-4 text-[#9E122C]" />
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Score Range Override</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <!-- Score From -->
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 font-medium">Score From <span class="text-red-500">*</span></label>
                            <input
                                v-model="addRangeForm.score_from"
                                type="number"
                                min="1"
                                max="150"
                                placeholder="e.g. 75"
                                class="px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition"
                            />
                            <p v-if="addRangeForm.errors.score_from" class="text-xs text-red-500 mt-1">{{ addRangeForm.errors.score_from }}</p>
                        </div>

                        <!-- Score To -->
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 font-medium">Score To <span class="text-red-500">*</span></label>
                            <input
                                v-model="addRangeForm.score_to"
                                type="number"
                                min="1"
                                max="150"
                                placeholder="e.g. 100"
                                class="px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition"
                            />
                            <p v-if="addRangeForm.errors.score_to" class="text-xs text-red-500 mt-1">{{ addRangeForm.errors.score_to }}</p>
                        </div>

                        <!-- Expiration Date -->
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 font-medium">Expiration Date <span class="text-red-500">*</span></label>
                            <input
                                v-model="addRangeForm.expires_at"
                                type="datetime-local"
                                class="px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] transition"
                            />
                            <p v-if="addRangeForm.errors.expires_at" class="text-xs text-red-500 mt-1">{{ addRangeForm.errors.expires_at }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <button
                                @click="handleRangeSearch"
                                :disabled="isSearchingRange || !addRangeForm.score_from || !addRangeForm.score_to"
                                class="flex-1 px-4 py-2.5 bg-gray-800 dark:bg-gray-700 hover:bg-gray-700 dark:hover:bg-gray-600 disabled:opacity-50 text-white rounded-xl font-medium text-sm transition shadow-sm"
                            >
                                {{ isSearchingRange ? 'Loading...' : 'Preview' }}
                            </button>
                            <button
                                @click="confirmAddRange"
                                :disabled="addRangeForm.processing || !addRangeForm.score_from || !addRangeForm.score_to || !addRangeForm.expires_at"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 text-white rounded-xl font-medium text-sm transition shadow-sm"
                            >
                                <FontAwesomeIcon icon="check-circle" class="w-3.5 h-3.5" />
                                {{ addRangeForm.processing ? 'Saving...' : 'Add Range' }}
                            </button>
                        </div>
                    </div>

                    <!-- Range info hint -->
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">
                        Scores are inclusive — a range of 75–100 allows any score from 75 to 100.
                    </p>

                    <!-- Preview Results -->
                    <div v-if="searchRangeError" class="mt-3">
                        <p class="text-sm text-red-500">{{ searchRangeError }}</p>
                    </div>

                    <div v-if="rangeApplicants.length > 0" class="mt-5 border-t border-gray-200 dark:border-gray-700 pt-5">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            Preview — {{ rangeApplicants.length }} applicant(s) in range {{ addRangeForm.score_from }}–{{ addRangeForm.score_to }}
                        </h3>
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-64 overflow-y-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-2.5">Reference No.</th>
                                        <th class="px-4 py-2.5">Name</th>
                                        <th class="px-4 py-2.5">Score</th>
                                        <th class="px-4 py-2.5">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="applicant in rangeApplicants" :key="applicant.test_passer_id" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ applicant.reference_number }}</td>
                                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ applicant.surname }}, {{ applicant.first_name }} {{ applicant.middle_name }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-[#9E122C]">{{ applicant.pupcet_total_score }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium capitalize" :class="getStatusBadgeClass(applicant.passer_status_id)">
                                                {{ applicant.passer_status?.status || 'Unknown' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Active Score Ranges Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Active Score Range Overrides</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Applicants whose PUPCET score falls within any active range can bypass the registration cutoff.
                        </p>
                    </div>

                    <div v-if="allowed_scores.length === 0" class="flex flex-col items-center justify-center py-14 text-gray-400">
                        <FontAwesomeIcon icon="info-circle" class="w-10 h-10 opacity-40 mb-3" />
                        <p class="text-sm">No score range overrides are currently active.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-3">Score Range</th>
                                    <th class="px-6 py-3">Expiration Date</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in allowed_scores" :key="item.id" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0">

                                    <!-- EDIT MODE -->
                                    <template v-if="editingId === item.id">
                                        <td class="px-6 py-3" colspan="2">
                                            <div class="flex flex-wrap items-end gap-3">
                                                <div class="flex flex-col">
                                                    <label class="text-xs text-gray-500 mb-1">From</label>
                                                    <input
                                                        v-model="editForm.score_from"
                                                        type="number"
                                                        min="1"
                                                        max="150"
                                                        class="w-24 px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-1 focus:ring-[#9E122C] outline-none"
                                                    />
                                                    <p v-if="editForm.errors.score_from" class="text-xs text-red-500 mt-0.5">{{ editForm.errors.score_from }}</p>
                                                </div>
                                                <span class="text-gray-400 pb-2">–</span>
                                                <div class="flex flex-col">
                                                    <label class="text-xs text-gray-500 mb-1">To</label>
                                                    <input
                                                        v-model="editForm.score_to"
                                                        type="number"
                                                        min="1"
                                                        max="150"
                                                        class="w-24 px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-1 focus:ring-[#9E122C] outline-none"
                                                    />
                                                    <p v-if="editForm.errors.score_to" class="text-xs text-red-500 mt-0.5">{{ editForm.errors.score_to }}</p>
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="text-xs text-gray-500 mb-1">Expiration Date</label>
                                                    <input
                                                        v-model="editForm.expires_at"
                                                        type="datetime-local"
                                                        class="px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-1 focus:ring-[#9E122C] outline-none"
                                                    />
                                                    <p v-if="editForm.errors.expires_at" class="text-xs text-red-500 mt-0.5">{{ editForm.errors.expires_at }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    @click="submitEdit"
                                                    :disabled="editForm.processing"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 text-white rounded-lg text-xs font-medium transition"
                                                >
                                                    <FontAwesomeIcon icon="check-circle" class="w-3 h-3" />
                                                    Save
                                                </button>
                                                <button
                                                    @click="cancelEdit"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg text-xs font-medium transition"
                                                >
                                                    <FontAwesomeIcon icon="xmark" class="w-3 h-3" />
                                                    Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </template>

                                    <!-- VIEW MODE -->
                                    <template v-else>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-[#9E122C]/10 text-[#9E122C] font-bold text-sm rounded-lg tabular-nums">
                                                    {{ item.score_from }} – {{ item.score_to }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span v-if="item.expires_at" class="text-sm text-gray-700 dark:text-gray-300">
                                                {{ new Date(item.expires_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                            </span>
                                            <span v-else class="text-sm text-gray-400 italic">Never expires</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <button
                                                    @click="startEdit(item)"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white rounded-lg text-xs font-medium transition"
                                                    title="Edit range"
                                                >
                                                    <FontAwesomeIcon icon="pencil" class="w-3 h-3" />
                                                    Edit
                                                </button>
                                                <button
                                                    @click="confirmRemoveRange(item.id)"
                                                    :disabled="deleteRangeForm.processing"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 dark:border-red-800 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-xs font-medium transition disabled:opacity-50"
                                                    title="Remove range"
                                                >
                                                    <FontAwesomeIcon icon="trash" class="w-3 h-3" />
                                                    Remove
                                                </button>
                                            </div>
                                        </td>
                                    </template>

                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- EMAIL TAB -->
            <div v-if="activeTab === 'email'" class="space-y-6">

                <!-- Search & Staging Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 mb-5">
                        <FontAwesomeIcon icon="plus" class="w-4 h-4 text-[#9E122C]" />
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Name & Email Overrides</h2>
                    </div>

                    <!-- Probation Bulk Add Panel -->
                    <div class="rounded-xl border border-[#9E122C]/20 dark:border-[#9E122C]/40 overflow-hidden mb-5">
                        <div
                            @click="loadProbationApplicants"
                            class="flex items-center justify-between px-4 py-3 bg-red-50/60 dark:bg-[#9E122C]/10 cursor-pointer hover:bg-red-50 dark:hover:bg-[#9E122C]/20 transition-colors"
                        >
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon icon="user-check" class="w-4 h-4 text-[#9E122C]" />
                                <div>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">On Probation Applicants</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">Bulk-load all applicants with "On Probation" status</span>
                                </div>
                            </div>
                            <FontAwesomeIcon :icon="showProbationPanel ? 'chevron-down' : 'chevron-right'" class="w-4 h-4 text-gray-400" />
                        </div>
                        <div v-show="showProbationPanel" class="border-t border-[#9E122C]/10 dark:border-[#9E122C]/20 bg-white dark:bg-gray-800">
                            <div v-if="isLoadingProbation" class="flex justify-center py-8">
                                <div class="animate-spin rounded-full h-7 w-7 border-b-2 border-[#9E122C]"></div>
                            </div>
                            <div v-else-if="probationError" class="text-center py-6 text-sm text-gray-500">{{ probationError }}</div>
                            <div v-else-if="probationApplicants.length > 0" class="p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        Found <strong class="text-[#9E122C]">{{ probationApplicants.length }}</strong> applicants
                                    </span>
                                    <button @click="addAllProbationApplicants" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#9E122C]/10 text-[#9E122C] hover:bg-[#9E122C]/20 rounded-lg text-xs font-semibold transition">
                                        <FontAwesomeIcon icon="check-circle" class="w-3.5 h-3.5" />
                                        Select All {{ probationApplicants.length }}
                                    </button>
                                </div>
                                <div class="max-h-52 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="w-full text-sm text-left">
                                        <thead class="text-xs text-gray-600 uppercase bg-gray-50 dark:bg-gray-700/50 dark:text-gray-300 sticky top-0 z-10">
                                            <tr>
                                                <th class="px-4 py-2">Email / Name</th>
                                                <th class="px-4 py-2 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="applicant in probationApplicants" :key="applicant.test_passer_id" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-4 py-2">
                                                    <div class="font-medium text-gray-900 dark:text-white text-xs">{{ applicant.email }}</div>
                                                    <div class="text-xs text-gray-500">{{ applicant.surname }}, {{ applicant.first_name }}</div>
                                                </td>
                                                <td class="px-4 py-2 text-right">
                                                    <button v-if="!selectedEmails.find(e => e.email === applicant.email)" @click="addToSelection(applicant)" class="px-2.5 py-1 bg-[#9E122C]/10 text-[#9E122C] hover:bg-[#9E122C]/20 rounded text-xs font-semibold transition">Add</button>
                                                    <span v-else class="text-xs text-green-600 dark:text-green-400 font-medium">✓ Added</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Search Input -->
                    <div class="flex gap-3 mb-4">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none z-10">
                                <FontAwesomeIcon icon="search" class="w-4 h-4 text-gray-400" />
                            </div>
                            <input
                                v-model="searchEmail"
                                @keyup.enter="handleEmailSearch"
                                type="text"
                                placeholder="Search by partial email or name (e.g. Juan, juan@gmail.com)"
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition"
                            />
                        </div>
                        <button
                            @click="handleEmailSearch"
                            :disabled="isSearchingEmail || !searchEmail"
                            class="px-5 py-2.5 bg-gray-800 dark:bg-gray-700 hover:bg-gray-700 dark:hover:bg-gray-600 disabled:opacity-50 text-white rounded-xl font-medium text-sm transition shadow-sm whitespace-nowrap"
                        >
                            {{ isSearchingEmail ? 'Searching...' : 'Search' }}
                        </button>
                    </div>
                    <p v-if="searchEmailError" class="text-sm text-red-500 mb-3">{{ searchEmailError }}</p>

                    <!-- Search Results -->
                    <div v-if="applicantsEmail.length > 0" class="mb-5 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-4 py-2.5 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                            {{ applicantsEmail.length }} result(s) found
                        </div>
                        <div class="overflow-x-auto max-h-56 overflow-y-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-600 uppercase bg-gray-50 dark:bg-gray-700/50 dark:text-gray-300 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-2.5">Email</th>
                                        <th class="px-4 py-2.5">Name</th>
                                        <th class="px-4 py-2.5">Status</th>
                                        <th class="px-4 py-2.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="applicant in applicantsEmail" :key="applicant.test_passer_id" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white text-xs">{{ applicant.email }}</td>
                                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ applicant.surname }}, {{ applicant.first_name }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="getStatusBadgeClass(applicant.passer_status_id)">
                                                {{ applicant.passer_status?.status || 'Unknown' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <button
                                                v-if="!selectedEmails.find(e => e.email === applicant.email)"
                                                @click="addToSelection(applicant)"
                                                class="px-2.5 py-1 bg-[#9E122C]/10 text-[#9E122C] hover:bg-[#9E122C]/20 rounded text-xs font-semibold transition"
                                            >Add</button>
                                            <span v-else class="text-xs text-green-600 dark:text-green-400 font-medium">✓ Added</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Staged Selection + Expiry -->
                    <div v-if="selectedEmails.length > 0" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                Selected applicants
                                <span class="ml-1.5 px-2 py-0.5 bg-[#9E122C]/10 text-[#9E122C] text-xs font-bold rounded-full">{{ selectedEmails.length }}</span>
                            </span>
                            <button @click="selectedEmails = []" class="text-xs text-gray-400 hover:text-red-500 transition">Clear all</button>
                        </div>
                        <div class="max-h-44 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                            <div v-for="item in selectedEmails" :key="item.email" class="flex items-center justify-between px-4 py-2.5 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ item.email }}</span>
                                    <span v-if="item.surname" class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ item.surname }}, {{ item.first_name }}</span>
                                </div>
                                <button @click="removeFromSelection(item.email)" class="text-gray-400 hover:text-red-500 p-1 rounded transition">
                                    <FontAwesomeIcon icon="xmark" class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row items-end gap-3 px-4 py-4 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex flex-col flex-1 w-full sm:w-auto">
                                <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 font-medium">Expiration Date <span class="text-red-500">*</span></label>
                                <input
                                    v-model="emailExpiresAt"
                                    type="datetime-local"
                                    class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] transition"
                                />
                                <p v-if="addEmailForm.errors.expires_at" class="text-xs text-red-500 mt-1">{{ addEmailForm.errors.expires_at }}</p>
                            </div>
                            <button
                                @click="confirmAddEmail"
                                :disabled="addEmailForm.processing || !emailExpiresAt"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 text-white rounded-lg font-semibold text-sm transition shadow-sm"
                            >
                                <FontAwesomeIcon icon="check-circle" class="w-4 h-4" />
                                {{ addEmailForm.processing ? 'Saving...' : `Allow ${selectedEmails.length} Override${selectedEmails.length !== 1 ? 's' : ''}` }}
                            </button>
                        </div>
                    </div>

                    <p v-if="selectedEmails.length === 0" class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                        Search for applicants above or expand the On Probation panel to stage names for override.
                    </p>
                </div>

                <!-- Active Email Overrides Table -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4 flex-wrap">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Active Email Overrides</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ allowed_emails.length }} override{{ allowed_emails.length !== 1 ? 's' : '' }} active
                            </p>
                        </div>
                        <div v-if="allowed_emails.length > 0" class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none z-10">
                                <FontAwesomeIcon icon="search" class="w-3.5 h-3.5 text-gray-400" />
                            </div>
                            <input
                                v-model="allowedEmailsSearch"
                                type="text"
                                placeholder="Filter overrides..."
                                class="pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-1 focus:ring-[#9E122C] focus:border-[#9E122C] outline-none transition"
                            />
                        </div>
                    </div>
                        
                    <div v-if="allowed_emails.length === 0" class="flex flex-col items-center justify-center py-14 text-gray-400">
                        <FontAwesomeIcon icon="info-circle" class="w-10 h-10 opacity-40 mb-3" />
                        <p class="text-sm">No email overrides are currently active.</p>
                    </div>
                    <div v-else-if="filteredAllowedEmails.length === 0" class="flex flex-col items-center justify-center py-10 text-gray-400">
                        <p class="text-sm">No overrides match your search.</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-3">Email</th>
                                    <th class="px-6 py-3">Name</th>
                                    <th class="px-6 py-3">Expiration Date</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in paginatedAllowedEmails" :key="item.email" class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 last:border-0">
                                    <!-- EDIT MODE -->
                                    <template v-if="editingEmail === item.email">
                                        <td class="px-6 py-3" colspan="3">
                                            <div class="flex flex-wrap items-end gap-3">
                                                <div class="flex flex-col">
                                                    <label class="text-xs text-gray-500 mb-1">Email</label>
                                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200 py-1.5">{{ item.email }}</span>
                                                </div>
                                                <div class="flex flex-col">
                                                    <label class="text-xs text-gray-500 mb-1">New Expiration Date</label>
                                                    <input
                                                        v-model="editEmailForm.expires_at"
                                                        type="datetime-local"
                                                        class="px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-1 focus:ring-[#9E122C] outline-none"
                                                    />
                                                    <p v-if="editEmailForm.errors.expires_at" class="text-xs text-red-500 mt-0.5">{{ editEmailForm.errors.expires_at }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button @click="submitEditEmail" :disabled="editEmailForm.processing" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#9E122C] hover:bg-[#800000] disabled:opacity-60 text-white rounded-lg text-xs font-medium transition">
                                                    <FontAwesomeIcon icon="check-circle" class="w-3 h-3" />Save
                                                </button>
                                                <button @click="cancelEditEmail" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg text-xs font-medium transition">
                                                    <FontAwesomeIcon icon="xmark" class="w-3 h-3" />Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </template>
                                    <!-- VIEW MODE -->
                                    <template v-else>
                                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white text-xs break-all max-w-[200px]">{{ item.email }}</td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400 text-sm">{{ item.name || '—' }}</td>
                                        <td class="px-6 py-4">
                                            <span v-if="item.expires_at" class="text-sm text-gray-700 dark:text-gray-300">
                                                {{ new Date(item.expires_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) }}
                                            </span>
                                            <span v-else class="text-sm text-gray-400 italic">Never expires</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <button @click="startEditEmail(item)" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white rounded-lg text-xs font-medium transition">
                                                    <FontAwesomeIcon icon="pencil" class="w-3 h-3" />Edit
                                                </button>
                                                <button @click="confirmRemoveEmail(item.email)" :disabled="deleteEmailForm.processing" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 dark:border-red-800 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-xs font-medium transition disabled:opacity-50">
                                                    <FontAwesomeIcon icon="trash" class="w-3 h-3" />Remove
                                                </button>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="allowedEmailsTotalPages > 1" class="flex items-center justify-between px-6 py-3 border-t border-gray-100 dark:border-gray-700">
                            <button @click="allowedEmailsCurrentPage--" :disabled="allowedEmailsCurrentPage === 1" class="px-3 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 disabled:opacity-50 hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium text-gray-700 dark:text-gray-300">Prev</button>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Page {{ allowedEmailsCurrentPage }} of {{ allowedEmailsTotalPages }}</span>
                            <button @click="allowedEmailsCurrentPage++" :disabled="allowedEmailsCurrentPage === allowedEmailsTotalPages" class="px-3 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 disabled:opacity-50 hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium text-gray-700 dark:text-gray-300">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ChangesConfirmationModal
            :show="confirmingAction !== null"
            :title="['addRange','editRange','addEmail','editEmail'].includes(confirmingAction) ? 'Confirm Override' : 'Revoke Override'"
            :subtitle="['addRange','editRange','addEmail','editEmail'].includes(confirmingAction) ? 'Are you sure you want to allow this override?' : 'Are you sure you want to revoke this override?'"
            :loading="confirmingAction === 'addRange' ? addRangeForm.processing : (confirmingAction === 'editRange' ? editForm.processing : (confirmingAction === 'removeRange' ? deleteRangeForm.processing : (confirmingAction === 'addEmail' ? addEmailForm.processing : (confirmingAction === 'editEmail' ? editEmailForm.processing : deleteEmailForm.processing))))"
            :confirmText="['addRange','editRange','addEmail','editEmail'].includes(confirmingAction) ? 'Confirm' : 'Confirm Revoke'"
            :confirmButtonClass="['addRange','editRange','addEmail','editEmail'].includes(confirmingAction) ? 'bg-[#9E122C] hover:bg-[#800918] text-white' : 'bg-red-600 hover:bg-red-700 text-white'"
            hideTable
            @confirm="proceedAction"
            @cancel="confirmingAction = null; confirmData = null"
        >
            <template #content>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <p v-if="confirmingAction === 'addRange'">
                        You are about to allow applicants with scores between
                        <strong>{{ addRangeForm.score_from }}</strong> and <strong>{{ addRangeForm.score_to }}</strong>
                        to bypass the registration cutoff until
                        <strong>{{ addRangeForm.expires_at ? new Date(addRangeForm.expires_at).toLocaleString() : '—' }}</strong>.
                    </p>
                    <p v-if="confirmingAction === 'editRange'">
                        You are about to update the score range to
                        <strong>{{ editForm.score_from }}–{{ editForm.score_to }}</strong>
                        with a new expiry of
                        <strong>{{ editForm.expires_at ? new Date(editForm.expires_at).toLocaleString() : '—' }}</strong>.
                    </p>
                    <p v-if="confirmingAction === 'removeRange'">
                        You are about to remove this score range override. Applicants in that range will no longer bypass the cutoff.
                    </p>
                    <p v-if="confirmingAction === 'addEmail'">
                        You are about to allow <strong>{{ selectedEmails.length }}</strong> applicant(s) to bypass the registration cutoff until
                        <strong>{{ emailExpiresAt ? new Date(emailExpiresAt).toLocaleString() : '—' }}</strong>.
                    </p>
                    <p v-if="confirmingAction === 'editEmail'">
                        You are about to update the override for <strong>{{ editEmailForm.email }}</strong> with a new expiry of
                        <strong>{{ editEmailForm.expires_at ? new Date(editEmailForm.expires_at).toLocaleString() : '—' }}</strong>.
                    </p>
                    <p v-if="confirmingAction === 'removeEmail'">
                        You are about to revoke the registration bypass for <strong>{{ confirmData }}</strong>. They will no longer be able to register if the cutoff is active.
                    </p>
                </div>
            </template>
        </ChangesConfirmationModal>

        <!-- Duplicate Email Warning Modal -->
        <Teleport to="body">
            <div v-if="duplicateWarning" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/40" @click="skipDuplicate"></div>

                <!-- Modal -->
                <div class="relative w-full max-w-sm bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">

                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Email already has an override</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        <span class="font-medium text-gray-700 dark:text-gray-200 break-all">{{ duplicateWarning.applicant.email }}</span>
                        ({{ duplicateWarning.applicant.surname }}, {{ duplicateWarning.applicant.first_name }}) is already allowed until
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ duplicateWarning.existingEntry.expires_at ? new Date(duplicateWarning.existingEntry.expires_at).toLocaleString() : 'never' }}</span>.
                        Proceeding will update their expiry to the new date you set.
                    </p>

                    <p v-if="duplicateBulkQueue.length > 0" class="text-xs text-gray-400 dark:text-gray-500 mb-4">
                        {{ duplicateBulkQueue.length }} more duplicate(s) remaining.
                    </p>

                    <div class="flex items-center justify-end gap-2">
                        <button
                            v-if="duplicateBulkQueue.length > 0"
                            @click="skipAllDuplicates"
                            class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition"
                        >
                            Skip all
                        </button>
                        <button
                            @click="skipDuplicate"
                            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        >
                            Skip
                        </button>
                        <button
                            @click="proceedAddDespiteDuplicate"
                            class="px-3 py-1.5 text-sm rounded-lg bg-[#9E122C] hover:bg-[#800000] text-white font-medium transition"
                        >
                            Proceed & Update
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </SuperAdminLayout>
</template>
