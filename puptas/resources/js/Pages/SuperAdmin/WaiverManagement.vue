<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { Head, Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import ChangesConfirmationModal from '@/Components/ChangesConfirmationModal.vue'
import Pagination from '@/Components/Pagination.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faSearch,
    faCheckCircle,
    faExclamationCircle,
    faExclamationTriangle,
    faTrash,
    faUserTag,
    faChevronRight,
    faInfoCircle,
    faFilePdf,
    faFileExcel,
    faUserSlash
} from '@fortawesome/free-solid-svg-icons'
import axios from 'axios'
function debounce(fn, delay) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}
library.add(faSearch, faCheckCircle, faExclamationCircle, faExclamationTriangle, faTrash, faUserTag, faChevronRight, faInfoCircle, faFilePdf, faFileExcel, faUserSlash)

const props = defineProps({
    tagged_applicants: {
        type: Object,
        default: () => ({ data: [] }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    }
})

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

// Eligible Search
const searchQuery = ref('')
const eligibleApplicants = ref([])
const isSearching = ref(false)
const searchError = ref(null)

const handleSearch = async () => {
    if (!searchQuery.value || searchQuery.value.length < 2) {
        searchError.value = "Please enter at least 2 characters."
        return
    }

    isSearching.value = true
    searchError.value = null
    eligibleApplicants.value = []

    try {
        const response = await axios.post(route('waiver.search'), {
            query: searchQuery.value
        })
        eligibleApplicants.value = response.data.applicants
        if (eligibleApplicants.value.length === 0) {
            searchError.value = "No eligible applicants found."
        }
    } catch (error) {
        searchError.value = error.response?.data?.message || "An error occurred while searching."
    } finally {
        isSearching.value = false
    }
}

// Tagged List Search
const taggedSearch = ref(props.filters.search || '')
const searchTagged = debounce(() => {
    router.get(
        route('waiver.index'),
        { search: taggedSearch.value },
        { preserveState: true, preserveScroll: true, replace: true }
    )
}, 300)

const tagForm = useForm({
    test_passer_id: '',
})

const untagForm = useForm({
    test_passer_id: '',
    reason: '',
})

const confirmingAction = ref(null)
const confirmData = ref(null)

const confirmTag = (applicant) => {
    confirmingAction.value = 'tag'
    confirmData.value = applicant
}

const confirmUntag = (applicant) => {
    confirmingAction.value = 'untag'
    confirmData.value = applicant
    untagForm.reason = ''
}

const proceedAction = () => {
    if (confirmingAction.value === 'tag') {
        tagForm.test_passer_id = confirmData.value.test_passer_id
        tagForm.post(route('waiver.tag'), {
            preserveScroll: true,
            onSuccess: () => {
                confirmingAction.value = null
                // Remove from local search results
                eligibleApplicants.value = eligibleApplicants.value.filter(
                    a => a.test_passer_id !== confirmData.value.test_passer_id
                )
                confirmData.value = null
            }
        })
    } else if (confirmingAction.value === 'untag') {
        untagForm.test_passer_id = confirmData.value.test_passer_id
        untagForm.post(route('waiver.untag'), {
            preserveScroll: true,
            onSuccess: () => {
                confirmingAction.value = null
                confirmData.value = null
            }
        })
    }
}

const getStatusBadgeClass = (statusId) => {
    switch (statusId) {
        case 1: return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        case 2: return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
        case 3: return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
        case 4: return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
        case 5: return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/50 dark:text-red-300 dark:border-red-800 border'
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
    }
}

const getStatusLabel = (status) => {
    if (!status) return 'Unknown'
    const map = {
        on_probation: 'On Probation',
        qualified: 'Qualified',
        accepted: 'Accepted',
        not_qualified: 'Not Qualified',
        waitlisted: 'Waitlisted',
    }
    return map[status] || status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<template>
    <Head title="Waiver Management" />
    <SuperAdminLayout>
        <div class="px-4 md:px-8 py-8 w-full max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-[#9E122C]/10 rounded-xl">
                        <FontAwesomeIcon icon="user-tag" class="h-6 w-6 text-[#9E122C]" />
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Waiver Management</h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1 text-sm">
                            Manage applicants on the Waiver Program (On Probation).
                        </p>
                    </div>
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
                <div v-if="Object.keys(page.props.errors).length > 0" class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-800 dark:text-red-300 mb-4">
                    <FontAwesomeIcon icon="exclamation-triangle" class="w-5 h-5 mt-0.5 flex-shrink-0" />
                    <ul class="text-sm font-medium list-disc list-inside">
                        <li v-for="(error, key) in page.props.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>
            </div>

            <!-- Tag New Applicants Section -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden mb-8">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Tag New Waiver Applicant</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Search for an applicant to add to the Waiver Program.</p>
                </div>

                <div class="p-6">
                    <div class="flex gap-4 items-start">
                        <div class="flex-1 max-w-md">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <FontAwesomeIcon icon="search" class="text-gray-400" />
                                </div>
                                <input 
                                    v-model="searchQuery" 
                                    @keyup.enter="handleSearch"
                                    type="text" 
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] sm:text-sm"
                                    placeholder="Search by name or reference no..."
                                >
                            </div>
                            <p v-if="searchError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ searchError }}</p>
                        </div>
                        <button 
                            @click="handleSearch" 
                            :disabled="isSearching"
                            class="px-4 py-2 bg-gray-900 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-800 dark:hover:bg-gray-600 transition-colors disabled:opacity-50 text-sm font-medium"
                        >
                            {{ isSearching ? 'Searching...' : 'Search' }}
                        </button>
                    </div>

                    <!-- Search Results -->
                    <div v-if="eligibleApplicants.length > 0" class="mt-6 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Applicant</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Program</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Current Status</th>
                                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Action</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="applicant in eligibleApplicants" :key="applicant.test_passer_id" class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ applicant.surname }}, {{ applicant.first_name }} {{ applicant.middle_name }}</span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ applicant.reference_number }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-900 dark:text-white">{{ applicant.user?.current_application?.program?.code || 'N/A' }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="getStatusBadgeClass(applicant.passer_status_id)">
                                                {{ getStatusLabel(applicant.passer_status?.status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button 
                                                @click="confirmTag(applicant)"
                                                class="text-[#9E122C] hover:text-[#7A0D20] bg-[#9E122C]/10 hover:bg-[#9E122C]/20 px-3 py-1.5 rounded-lg transition-colors"
                                            >
                                                Tag as Waiver
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tagged Applicants List -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Tagged Waiver Applicants</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">List of all applicants currently on probation under the Waiver Program.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative max-w-xs">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <FontAwesomeIcon icon="search" class="text-gray-400" />
                            </div>
                            <input 
                                v-model="taggedSearch" 
                                @input="searchTagged"
                                type="text" 
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] sm:text-sm"
                                placeholder="Search list..."
                            >
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Applicant</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Program</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="tagged_applicants.data.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <FontAwesomeIcon icon="user-slash" class="text-4xl mb-4 text-gray-300 dark:text-gray-600" />
                                        <p class="text-base font-medium">No tagged waiver applicants found</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="applicant in tagged_applicants.data" :key="applicant.test_passer_id" class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ applicant.surname }}, {{ applicant.first_name }} {{ applicant.middle_name }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ applicant.reference_number }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 dark:text-white">{{ applicant.user?.current_application?.program?.code || 'N/A' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1 items-start">
                                        <span class="px-2.5 py-1 inline-flex items-center gap-1.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                            ⚠️ Waiver Program
                                        </span>
                                        <span class="px-2.5 py-1 inline-flex items-center gap-1.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 border border-red-200 dark:border-red-800">
                                            🔴 On Probation
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button 
                                        @click="confirmUntag(applicant)"
                                        class="text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 px-3 py-1.5 rounded-lg transition-colors"
                                        title="Remove Tag"
                                    >
                                        <FontAwesomeIcon icon="trash" /> Untag
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700" v-if="tagged_applicants.data.length > 0">
                    <Pagination :links="tagged_applicants.links" />
                </div>
            </div>
        </div>

        <!-- Confirmation Modals -->
        <ChangesConfirmationModal
            :show="confirmingAction !== null"
            @cancel="confirmingAction = null; confirmData = null"
            @confirm="proceedAction"
            :loading="tagForm.processing || untagForm.processing"
            :confirm-text="confirmingAction === 'tag' ? 'Confirm Tagging' : 'Confirm Untagging'"
            :confirm-button-class="confirmingAction === 'untag' ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-[#9E122C] hover:bg-[#800918] text-white'"
            disable-changes-validation
        >
            <template #title>
                {{ confirmingAction === 'tag' ? 'Tag as Waiver Applicant' : 'Remove Waiver Tag' }}
            </template>
            <template #content>
                <div v-if="confirmingAction === 'tag'">
                    <p class="text-gray-600 dark:text-gray-400">
                        Are you sure you want to tag <span class="font-bold text-gray-900 dark:text-white">{{ confirmData?.first_name }} {{ confirmData?.surname }}</span> as a Waiver Applicant?
                    </p>
                    <div class="mt-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                        <div class="flex gap-3">
                            <FontAwesomeIcon icon="exclamation-triangle" class="text-amber-500 mt-0.5" />
                            <div class="text-sm text-amber-800 dark:text-amber-300">
                                <p class="font-medium">Important changes will occur:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1 opacity-90">
                                    <li>Application will be flagged as Waivered.</li>
                                    <li>Applicant's status will be changed to <span class="font-bold">On Probation</span>.</li>
                                    <li>This action will be recorded in the audit logs.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="confirmingAction === 'untag'">
                    <p class="text-gray-600 dark:text-gray-400">
                        Are you sure you want to remove the Waiver Tag from <span class="font-bold text-gray-900 dark:text-white">{{ confirmData?.first_name }} {{ confirmData?.surname }}</span>?
                    </p>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason for removal <span class="text-red-500">*</span></label>
                        <input 
                            v-model="untagForm.reason" 
                            type="text" 
                            required
                            class="block w-full border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] sm:text-sm p-2"
                            placeholder="e.g. Added by mistake, requirements met, etc."
                        >
                        <p v-if="untagForm.errors.reason" class="mt-1 text-sm text-red-600">{{ untagForm.errors.reason }}</p>
                    </div>
                    <p class="mt-4 text-sm text-gray-500">The applicant's status will revert to their previous status before being tagged (or Unqualified if unavailable).</p>
                </div>
            </template>
        </ChangesConfirmationModal>
    </SuperAdminLayout>
</template>
