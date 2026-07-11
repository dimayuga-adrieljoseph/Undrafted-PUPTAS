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
    faUserSlash,
    faFileImport,
    faUpload
} from '@fortawesome/free-solid-svg-icons'
import axios from 'axios'
function debounce(fn, delay) {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}
library.add(faSearch, faCheckCircle, faExclamationCircle, faExclamationTriangle, faTrash, faUserTag, faChevronRight, faInfoCircle, faFilePdf, faFileExcel, faUserSlash, faFileImport, faUpload)

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

const exportData = (type) => {
    let url = type === 'pdf' ? route('waiver.export.pdf') : route('waiver.export.csv')
    if (taggedSearch.value) {
        url += `?search=${encodeURIComponent(taggedSearch.value)}`
    }
    window.location.href = url
}

// Bulk Import
const showImportModal = ref(false)
const importFile = ref(null)
const importPreviewData = ref(null)
const importUnmatched = ref([])
const importDuplicateCount = ref(0)
const importingFile = ref(false)
const confirmingImport = ref(false)
const importError = ref(null)
const importFileInput = ref(null)
const isDragging = ref(false)

const previewPage = ref(1)
const previewPerPage = 10

const paginatedPreview = computed(() => {
    if (!importPreviewData.value) return []
    const start = (previewPage.value - 1) * previewPerPage
    return importPreviewData.value.slice(start, start + previewPerPage)
})

const totalPreviewPages = computed(() => {
    if (!importPreviewData.value) return 0
    return Math.ceil(importPreviewData.value.length / previewPerPage)
})

const nextPreviewPage = () => { if (previewPage.value < totalPreviewPages.value) previewPage.value++ }
const prevPreviewPage = () => { if (previewPage.value > 1) previewPage.value-- }

const triggerFileInput = () => {
    importFileInput.value.click()
}

const onDragOver = (e) => { e.preventDefault(); isDragging.value = true; }
const onDragLeave = (e) => { e.preventDefault(); isDragging.value = false; }
const onDrop = (e) => {
    e.preventDefault()
    isDragging.value = false
    const files = e.dataTransfer.files
    if (files.length > 0) {
        const file = files[0]
        if (file.name.endsWith('.xlsx') || file.name.endsWith('.csv')) {
            handleFileUpload({ target: { files: [file] } })
        } else {
            importError.value = "Please upload a valid .xlsx or .csv file."
        }
    }
}

const handleFileUpload = async (event) => {
    const file = event.target.files[0]
    if (!file) return

    importFile.value = file
    importingFile.value = true
    importError.value = null
    importPreviewData.value = null
    importUnmatched.value = []
    previewPage.value = 1

    const formData = new FormData()
    formData.append('file', file)

    try {
        const response = await axios.post(route('waiver.import.preview'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        importPreviewData.value = response.data.matched
        importUnmatched.value = response.data.unmatched
        importDuplicateCount.value = response.data.duplicate_count ?? 0
    } catch (error) {
        importError.value = error.response?.data?.message || 'Failed to upload and parse file.'
        importFile.value = null
    } finally {
        importingFile.value = false
        event.target.value = null
    }
}

const cancelImport = () => {
    showImportModal.value = false
    importFile.value = null
    importPreviewData.value = null
    importUnmatched.value = []
    importError.value = null
}

const confirmImport = () => {
    if (!importPreviewData.value || importPreviewData.value.length === 0) return

    confirmingImport.value = true
    importError.value = null

    router.post(route('waiver.import.confirm'), {
        applicants: importPreviewData.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cancelImport()
            confirmingImport.value = false
        },
        onError: (errors) => {
            importError.value = errors.message || 'An error occurred during import.'
            confirmingImport.value = false
        }
    })
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
                
                <div class="flex items-center gap-3">
                    <button @click="showImportModal = true" class="inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] border border-transparent rounded-xl text-sm font-medium text-white hover:bg-[#800918] transition shadow-sm">
                        <FontAwesomeIcon icon="file-import" />
                        Import from Sheet
                    </button>
                    <button @click="exportData('csv')" class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm">
                        <FontAwesomeIcon icon="file-excel" class="text-green-600" />
                        Export CSV
                    </button>
                    <button @click="exportData('pdf')" class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm">
                        <FontAwesomeIcon icon="file-pdf" class="text-red-500" />
                        Export PDF
                    </button>
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
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Strand / Score</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status / Prog. Offering</th>
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
                                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ applicant.user?.email || 'N/A' }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col">
                                                <span class="text-sm text-gray-900 dark:text-white">{{ applicant.strand || 'N/A' }}</span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Score: {{ applicant.pupcet_total_score || 'N/A' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col gap-1">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ applicant.waiver_list_status || getStatusLabel(applicant.passer_status?.status) }}
                                                </span>
                                                <span class="text-xs text-[#9E122C] font-medium mt-1">
                                                    {{ applicant.waiver_program_offering || applicant.user?.current_application?.program?.code || 'N/A' }}
                                                </span>
                                            </div>
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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Strand / Score</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status / Prog. Offering</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="tagged_applicants.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center">
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
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ applicant.email || applicant.user?.email || 'N/A' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="text-sm text-gray-900 dark:text-white">{{ applicant.strand || 'N/A' }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Score: {{ applicant.pupcet_total_score || 'N/A' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1 items-start">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                            {{ applicant.waiver_list_status || getStatusLabel(applicant.passer_status?.status) }}
                                        </span>
                                        <span class="px-2.5 py-1 inline-flex items-center gap-1.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 border border-red-200 dark:border-red-800">
                                            🔴 On Probation
                                        </span>
                                        <span class="text-xs text-[#9E122C] font-medium mt-1">
                                            {{ applicant.waiver_program_offering || applicant.user?.current_application?.program?.code || 'N/A' }}
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

        <!-- Import Modal -->
        <ChangesConfirmationModal
            :show="showImportModal"
            @cancel="cancelImport"
            @confirm="confirmImport"
            :loading="confirmingImport"
            confirm-text="Confirm Import"
            confirm-button-class="bg-[#9E122C] hover:bg-[#800918] text-white"
            disable-changes-validation
        >
            <template #title>
                Import Waiver Applicants
            </template>
            <template #content>
                <div class="flex flex-col gap-4">
                    <!-- Step 1: Upload -->
                <!-- Importerror -->
                    <p v-if="importError" class="mt-4 text-sm text-red-600 dark:text-red-400 font-medium bg-red-50 dark:bg-red-900/20 p-2 rounded w-full text-center">{{ importError }}</p>

                    <div v-if="!importPreviewData"
                         @dragover="onDragOver"
                         @dragleave="onDragLeave"
                         @drop="onDrop"
                         :class="['flex flex-col items-center justify-center p-8 border-2 border-dashed rounded-xl transition-colors', isDragging ? 'border-[#9E122C] bg-red-50 dark:bg-red-900/10' : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50']">
                        <FontAwesomeIcon icon="upload" :class="['text-4xl mb-4 transition-colors', isDragging ? 'text-[#9E122C]' : 'text-gray-400']" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Upload Excel Sheet</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 text-center">
                            Drag and drop the official waiver applicants list (.xlsx, .csv) here, or click to browse. <br>
                            The system will match applicants based on the "Reference Number" column.
                        </p>
                        
                        <input type="file" ref="importFileInput" accept=".xlsx,.csv" class="hidden" @change="handleFileUpload" />
                        
                        <button 
                            @click="triggerFileInput"
                            :disabled="importingFile"
                            class="px-5 py-2.5 bg-gray-900 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-800 dark:hover:bg-gray-600 transition disabled:opacity-50 font-medium"
                        >
                            {{ importingFile ? 'Processing file...' : 'Select File' }}
                        </button>
                        
                    </div>
                    
                    <!-- Step 2: Preview -->
                    <div v-else class="flex flex-col gap-4">
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl flex items-start gap-3">
                            <FontAwesomeIcon icon="info-circle" class="text-blue-500 mt-0.5" />
                            <div class="text-sm text-blue-800 dark:text-blue-300">
                                <p class="font-medium">File successfully processed!</p>
                                <p class="mt-1">Found <strong>{{ importPreviewData.length }}</strong> matched applicants ready to be tagged/updated.</p>
                            </div>
                        </div>

                        <div v-if="importDuplicateCount > 0" class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl flex items-start gap-3">
                            <FontAwesomeIcon icon="info-circle" class="text-blue-500 mt-0.5" />
                            <div class="text-sm text-blue-800 dark:text-blue-300">
                                <p>Found <strong>{{ importDuplicateCount }}</strong> duplicate reference number(s) in the uploaded file. Only the first occurrence of each will be imported.</p>
                            </div>
                        </div>

                        <div v-if="importUnmatched.length > 0" class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl flex items-start gap-3">
                            <FontAwesomeIcon icon="exclamation-triangle" class="text-amber-500 mt-0.5" />
                            <div class="text-sm text-amber-800 dark:text-amber-300">
                                <p class="font-medium">Warning: {{ importUnmatched.length }} applicants not found</p>
                                <p class="mt-1">The following reference numbers were not found in the system. They will be skipped during import.</p>
                                <div class="mt-2 max-h-32 overflow-y-auto bg-white/50 dark:bg-black/20 p-2 rounded border border-amber-200/50 dark:border-amber-800/50">
                                    <ul class="list-disc list-inside text-xs space-y-1">
                                        <li v-for="um in importUnmatched" :key="um.reference_number">
                                            {{ um.reference_number }} - {{ um.name || 'Unknown' }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white mb-2 text-sm flex items-center justify-between">
                                Matched Applicants Preview
                                <span class="text-xs text-gray-500 font-normal">Page {{ previewPage }} of {{ totalPreviewPages }}</span>
                            </h4>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="ap in paginatedPreview" :key="ap.reference_number">
                                            <td class="px-4 py-2 text-xs">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ ap.system_name }}</div>
                                                <div class="text-gray-500">{{ ap.reference_number }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-xs">
                                                <span v-if="ap.is_already_tagged" class="text-amber-600 font-medium">Update Info</span>
                                                <span v-else class="text-green-600 font-medium">Tag & Update</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination Controls -->
                            <div class="flex items-center justify-between mt-3" v-if="totalPreviewPages > 1">
                                <button @click="prevPreviewPage" :disabled="previewPage === 1" class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50 text-xs font-medium transition">
                                    Previous
                                </button>
                                <span class="text-xs text-gray-500">Showing {{ (previewPage - 1) * previewPerPage + 1 }} - {{ Math.min(previewPage * previewPerPage, importPreviewData.length) }} of {{ importPreviewData.length }}</span>
                                <button @click="nextPreviewPage" :disabled="previewPage === totalPreviewPages" class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50 text-xs font-medium transition">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </ChangesConfirmationModal>
    </SuperAdminLayout>
</template>
