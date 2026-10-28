<!--
  Dashboard/Interviewer.vue — PUPTAS Interviewer Dashboard
  ─────────────────────────────────────────────────────────
  Serves Interviewers (role 4) and Admin/SuperAdmin visiting the interviewer stage.

  All existing workflow interactions are preserved:
  - beginInterview  (POST /interviewer-dashboard/start/{id})
  - cancelInterview (POST /interviewer-dashboard/cancel/{id})
  - acceptApplication (POST /interviewer-dashboard/accept/{id})  — requires program_id
  - rejectApplication (POST /interviewer-dashboard/reject/{id})
  - Low-slot warning banner (threshold: ≤10 slots)
  - Program selection for accept/reject

  Visual style follows the completed Admin / SuperAdmin dashboard redesign.
  Stage accent: sky (#0369A1)
-->
<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { LineChart } from 'vue-chart-3'
import {
    Chart as ChartJS, LineController, LineElement,
    CategoryScale, LinearScale, PointElement, Tooltip, Legend, Filler,
} from 'chart.js'
import InterviewerLayout from '@/Layouts/InterviewerLayout.vue'
import ChangesConfirmationModal from '@/Components/ChangesConfirmationModal.vue'
import ImageViewer from '@/Components/ImageViewer.vue'
import UserDetailsModal from '@/Pages/Applications/UserDetailsModal.vue'
import { useMaskingState } from '@/Composables/useMaskingState'

ChartJS.register(LineController, LineElement, CategoryScale, LinearScale, PointElement, Tooltip, Legend, Filler)

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    user:             { type: Object, default: null },
    pendingUsers:     { type: Array,  default: () => [] },
    assignedPrograms: { type: Array,  default: () => [] },
    summary:          { type: Object, default: () => ({ in_progress: 0, processed: 0 }) },
    chartData:        { type: Object, default: () => ({ submitted: [], accepted: [], returned: [], labels: [] }) },
    filters:          { type: Object, default: () => ({ start_date: '', end_date: '' }) },
})

const { isUnmasked } = useMaskingState()

const isAdminOrSuperAdmin = computed(() => [2, 7].includes(props.user?.role_id))
const startDateFilter = ref(props.filters?.start_date || '')
const endDateFilter   = ref(props.filters?.end_date   || '')
const showDateFilter  = ref(false)
const applyFilters = () => {
    router.get(window.location.pathname, {
        start_date: startDateFilter.value,
        end_date:   endDateFilter.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
    showDateFilter.value = false
}

// ── Low-slot warning ──────────────────────────────────────────────────────────
const LOW_SLOT_THRESHOLD = 10
const showLowSlotAlert   = ref(true)
const lowSlotPrograms    = computed(() =>
    (props.assignedPrograms || []).filter(p => p.slots != null && p.slots <= LOW_SLOT_THRESHOLD)
)
watch(() => props.assignedPrograms, () => { showLowSlotAlert.value = true }, { deep: true })

const decrementLocalSlot = (programId) => {
    const p = (props.assignedPrograms || []).find(p => p.id == programId)
    if (p && p.slots != null && p.slots > 0) p.slots -= 1
}

// ── Applicant queue ───────────────────────────────────────────────────────────
const searchQuery = ref('')
const currentPage = ref(1)
const PAGE_SIZE   = 8

const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    const list = props.pendingUsers || []
    if (!q) return list
    return list.filter(u =>
        `${u.firstname} ${u.lastname}`.toLowerCase().includes(q) ||
        (u.email || '').toLowerCase().includes(q)
    )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / PAGE_SIZE)))
const displayedUsers = computed(() => {
    const s = (currentPage.value - 1) * PAGE_SIZE
    return filteredUsers.value.slice(s, s + PAGE_SIZE)
})
watch(searchQuery, () => { currentPage.value = 1 })

// ── User selection & file fetch ───────────────────────────────────────────────
const selectedUser       = ref(null)
const selectedUserFiles  = ref({})
const selectedProgramId  = ref('')
const interviewStartTime = ref(null)
const interviewNotes     = ref('')

const selectUser = async (user) => {
    try {
        const res = await axios.get(`/interviewer-dashboard/application/${user.id}`)
        selectedUser.value = {
            ...user,
            application: {
                ...res.data.user.application,
                processes: res.data.user.application?.processes || [],
                program:   res.data.user.application?.program   || null,
            },
            grades:               res.data.user.grades || null,
            unqualified_programs: res.data.user.unqualified_programs || [],
        }
        selectedUserFiles.value = res.data.uploadedFiles || {}

        const ip = selectedUser.value.application?.processes?.find(
            p => p.stage === 'interviewer' && p.status === 'in_progress'
        )
        interviewStartTime.value = ip?.started_at
            ? (ip.started_at.endsWith('Z') ? ip.started_at : ip.started_at + 'Z')
            : null
    } catch (e) {
        console.error('Failed to load applicant:', e)
        showSnackbar('Failed to load applicant data.', 'error')
    }
}

const closeUserCard = () => {
    selectedUser.value      = null
    selectedProgramId.value = ''
    interviewStartTime.value = null
    interviewNotes.value    = ''
}

// ── Image viewer ──────────────────────────────────────────────────────────────
const previewImage    = ref('')
const previewAlt      = ref('Document preview')
const showImageModal  = ref(false)
const getFileUrl      = (f) => typeof f === 'string' ? f : (f?.url || '')
const hasImagePreview = (f) => Boolean(getFileUrl(f)) && (typeof f === 'string' || f?.isImage !== false)
const openImageModal  = (file, label = '') => {
    const src = getFileUrl(file)
    if (!src || !hasImagePreview(file)) return
    previewImage.value   = src
    previewAlt.value     = label || file?.original_name || 'Document preview'
    showImageModal.value = true
}
const closeImageModal = () => { showImageModal.value = false; previewImage.value = '' }

// ── Snackbar ──────────────────────────────────────────────────────────────────
const snackbar     = ref({ visible: false, message: '', type: 'success' })
let   snackTimer   = null
const showSnackbar = (msg, type = 'success') => {
    if (snackTimer) clearTimeout(snackTimer)
    snackbar.value = { visible: true, message: msg, type }
    snackTimer = setTimeout(() => { snackbar.value.visible = false }, 5000)
}

// ── Interview workflow ────────────────────────────────────────────────────────
const isSubmitting       = ref(false)
const isCancellingInterview = ref(false)
const showCancelModal    = ref(false)
const showAcceptModal    = ref(false)
const showRejectModal    = ref(false)

const isApplicantQualified = computed(() => {
    if (!selectedProgramId.value || !selectedUser.value?.unqualified_programs) return true
    return !selectedUser.value.unqualified_programs.some(p => p.id == selectedProgramId.value)
})

const beginInterview = async () => {
    try {
        const res = await axios.post(`/interviewer-dashboard/start/${selectedUser.value.id}`)
        const t = res.data.started_at
        interviewStartTime.value = t.endsWith('Z') ? t : t + 'Z'
    } catch (e) {
        showSnackbar(e.response?.data?.message || 'Failed to start interview.', 'error')
    }
}

const cancelInterview = () => { showCancelModal.value = true }

const confirmCancelInterview = async () => {
    isCancellingInterview.value = true
    try {
        await axios.post(`/interviewer-dashboard/cancel/${selectedUser.value.id}`)
        interviewStartTime.value = null
        if (selectedUser.value?.application?.processes) {
            const idx = selectedUser.value.application.processes.findIndex(p => p.stage === 'interviewer')
            if (idx !== -1) {
                selectedUser.value.application.processes[idx].started_at  = null
                selectedUser.value.application.processes[idx].performed_by = null
            }
        }
        showSnackbar('Interview cancelled.', 'info')
        showCancelModal.value = false
    } catch (e) {
        console.error('Cancel interview failed:', e)
        const msg = e.response?.data?.message || 'Failed to cancel interview.'
        showSnackbar(msg, 'error')
        if ([400, 409, 422].includes(e.response?.status)) {
            showCancelModal.value = false
            router.reload({
                only: ['pendingUsers', 'summary', 'assignedPrograms'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    if (selectedUser.value?.id) {
                        selectUser(selectedUser.value)
                    }
                }
            })
        }
    } finally {
        isCancellingInterview.value = false
    }
}

const promptAccept = () => {
    if (!selectedProgramId.value)
        return showSnackbar('Please select a program before accepting.', 'error')
    showAcceptModal.value = true
}

const acceptApplication = async () => {
    isSubmitting.value = true
    const acceptedProgramId = selectedProgramId.value
    try {
        await axios.post(`/interviewer-dashboard/accept/${selectedUser.value.id}`, {
            program_id: acceptedProgramId,
            start_time: interviewStartTime.value,
            notes:      interviewNotes.value,
        })
        showSnackbar('Application accepted successfully', 'success')
        decrementLocalSlot(acceptedProgramId)
        closeUserCard()
        showAcceptModal.value = false
        router.reload({ only: ['pendingUsers', 'summary', 'assignedPrograms'] })
    } catch (e) {
        console.error('Accept failed:', e)
        const msg = e.response?.data?.message || 'Failed to accept application'
        showSnackbar(msg, 'error')
        if ([400, 409, 422].includes(e.response?.status)) {
            showAcceptModal.value = false
            router.reload({
                only: ['pendingUsers', 'summary', 'assignedPrograms'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    if (selectedUser.value?.id) {
                        selectUser(selectedUser.value)
                    }
                }
            })
        }
    } finally {
        isSubmitting.value = false
    }
}

const promptReject = () => {
    if (!selectedProgramId.value)
        return showSnackbar('Please select a program before rejecting.', 'error')
    showRejectModal.value = true
}

const rejectApplication = async () => {
    isSubmitting.value = true
    try {
        await axios.post(`/interviewer-dashboard/reject/${selectedUser.value.id}`, {
            program_id: selectedProgramId.value,
            start_time: interviewStartTime.value,
            notes:      interviewNotes.value,
        })
        showSnackbar('Application rejected successfully', 'success')
        closeUserCard()
        showRejectModal.value = false
        router.reload({ only: ['pendingUsers', 'summary', 'assignedPrograms'] })
    } catch (e) {
        console.error('Reject failed:', e)
        const msg = e.response?.data?.message || 'Failed to reject application'
        showSnackbar(msg, 'error')
        if ([400, 409, 422].includes(e.response?.status)) {
            showRejectModal.value = false
            router.reload({
                only: ['pendingUsers', 'summary', 'assignedPrograms'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    if (selectedUser.value?.id) {
                        selectUser(selectedUser.value)
                    }
                }
            })
        }
    } finally {
        isSubmitting.value = false
    }
}

// ── Status helpers ────────────────────────────────────────────────────────────
const statusBadgeClass = (s) => {
    const v = (s || '').toLowerCase()
    if (['accepted', 'cleared_for_enrollment', 'officially_enrolled'].includes(v))
        return 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300'
    if (v === 'submitted')
        return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
    if (v === 'returned')
        return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'
    return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
}
const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
const formatDateTime = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true })
}

// ── Chart ─────────────────────────────────────────────────────────────────────
const chartDataset = computed(() => ({
    labels: props.chartData.labels || [],
    datasets: [
        { label: 'Submitted', data: props.chartData.submitted || [], borderColor: '#800000', backgroundColor: 'rgba(128,0,0,0.08)', fill: true, tension: 0.4, pointBackgroundColor: '#800000', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 3, pointHoverRadius: 5 },
        { label: 'Accepted',  data: props.chartData.accepted  || [], borderColor: '#15803D', backgroundColor: 'rgba(21,128,61,0.07)',  fill: true, tension: 0.4, pointBackgroundColor: '#15803D', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 3, pointHoverRadius: 5 },
        { label: 'Returned',  data: props.chartData.returned  || [], borderColor: '#B91C1C', backgroundColor: 'rgba(185,28,28,0.06)',  fill: true, tension: 0.4, pointBackgroundColor: '#B91C1C', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 3, pointHoverRadius: 5 },
    ],
}))
const chartOptions = {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, borderRadius: 3, usePointStyle: true, pointStyle: 'circle', font: { size: 11 }, color: '#6B7280', padding: 16 } },
        tooltip: { backgroundColor: '#fff', titleColor: '#111827', bodyColor: '#374151', borderColor: '#E7E5E4', borderWidth: 1, cornerRadius: 8, padding: 12 },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: '#9CA3AF', font: { size: 11 }, maxRotation: 0 } },
        y: { beginAtZero: true, grid: { color: 'rgba(156,163,175,0.12)' }, ticks: { color: '#9CA3AF', font: { size: 11 }, callback: v => v.toLocaleString() } },
    },
}
</script>

<template>
    <Head title="Interview Dashboard" />
    <InterviewerLayout>
        <!-- Toast / Snackbar Notification -->
        <Teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="transform opacity-0 translate-y-[-1rem]"
                enter-to-class="transform opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="transform opacity-100 translate-y-0"
                leave-to-class="transform opacity-0 translate-y-[-1rem]"
            >
                <div v-if="snackbar.visible" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-[99999] w-full max-w-md px-4 pointer-events-auto">
                    <div :class="['rounded-lg shadow-2xl overflow-hidden border', snackbar.type === 'success' ? 'bg-green-50 dark:bg-green-900 border-green-500 dark:border-green-400' : 'bg-red-50 dark:bg-red-900 border-red-500 dark:border-red-400']">
                        <div class="p-4 flex items-start">
                            <svg v-if="snackbar.type === 'success'" class="w-5 h-5 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="w-5 h-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                <p :class="['text-sm font-medium', snackbar.type === 'success' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200']">{{ snackbar.message }}</p>
                            </div>
                            <div class="ml-4 flex-shrink-0 flex">
                                <button @click="snackbar.visible = false" :class="['rounded-md inline-flex focus:outline-none', snackbar.type === 'success' ? 'text-green-500 hover:text-green-600' : 'text-red-500 hover:text-red-600']">
                                    <span class="sr-only">Close</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- ── Snackbar ──────────────────────────────────────────────── -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-3 opacity-0"
        >
            <div
                v-if="snackbar.visible"
                role="status"
                aria-live="polite"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[70] flex items-center gap-2 px-5 py-3 rounded-xl shadow-lg text-sm font-medium pointer-events-none"
                :class="snackbar.type === 'success' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                      : snackbar.type === 'error'   ? 'bg-red-700 text-white'
                      : 'bg-sky-700 text-white'"
            >{{ snackbar.message }}</div>
        </Transition>

        <div class="dash-shell-scroll space-y-6 pb-8">

            <!-- ── Low-slot warning ───────────────────────────────────── -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="showLowSlotAlert && lowSlotPrograms.length"
                    role="alert"
                    class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-card p-4 flex items-start gap-3"
                >
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-amber-700 dark:text-amber-300">Low Slots Warning</p>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">Program(s) with {{ LOW_SLOT_THRESHOLD }} or fewer slots remaining:</p>
                        <ul class="mt-1.5 space-y-0.5">
                            <li v-for="p in lowSlotPrograms" :key="p.id" class="text-xs font-medium text-amber-800 dark:text-amber-200">
                                {{ p.code }} – {{ p.name }} <span class="font-bold">({{ p.slots }} slot{{ p.slots !== 1 ? 's' : '' }} left)</span>
                            </li>
                        </ul>
                    </div>
                    <button @click="showLowSlotAlert = false" class="shrink-0 p-1.5 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-800/30 transition text-amber-500" aria-label="Dismiss low-slot warning">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </Transition>

            <!-- ── Page header ──────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <span class="w-3 h-3 rounded-full bg-sky-600" aria-hidden="true"></span>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Interview Dashboard</h1>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Conduct interviews, select programs, and pass or reject applicants to the medical stage.
                    </p>
                </div>
                <a
                    href="/interviewer-applications"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-maroon-light text-white hover:bg-maroon transition shadow-sm self-start sm:self-auto"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8z"/></svg>
                    All Applications
                </a>
            </div>

            <!-- ── Summary cards ─────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3">
                <div class="metric-card border-l-4 border-l-sky-600 animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">In Queue</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-400">
                        {{ (summary?.in_progress ?? 0).toLocaleString() }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Awaiting interview</p>
                </div>
                <div class="metric-card border-l-4 border-l-green-600 animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Processed</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-green-700 dark:text-green-400">
                        {{ (summary?.processed ?? 0).toLocaleString() }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Interviews completed</p>
                </div>
            </div>

            <!-- ── Applicant queue + chart ───────────────────────────── -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- Interview queue (2/3) -->
                <div class="xl:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card flex flex-col">
                    <div class="px-5 pt-5 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Interview Queue</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ filteredUsers.length }} applicant{{ filteredUsers.length !== 1 ? 's' : '' }} — click to open interview panel
                            </p>
                        </div>
                        <div class="relative sm:w-56 shrink-0">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Search applicant…"
                                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-maroon-light focus:border-maroon-light transition"
                                aria-label="Search applicants"
                            />
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="flex-1 overflow-x-auto">
                        <table class="w-full text-sm" aria-label="Interview queue">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/30">
                                    <th class="text-left px-5 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Applicant</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden sm:table-cell">Program</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden md:table-cell">Applied</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</th>
                                    <th class="px-5 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template v-if="displayedUsers.length === 0">
                                    <tr>
                                        <td colspan="5" class="px-5 py-12 text-center">
                                            <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8z"/></svg>
                                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                                {{ searchQuery ? 'No results found' : 'No interviews pending' }}
                                            </p>
                                        </td>
                                    </tr>
                                </template>
                                <tr
                                    v-for="u in displayedUsers"
                                    :key="u.id"
                                    @click="selectUser(u)"
                                    class="hover:bg-gray-50/70 dark:hover:bg-gray-800/50 cursor-pointer transition"
                                >
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 rounded-full bg-sky-50 text-sky-700 dark:bg-sky-900/20 dark:text-sky-300 flex items-center justify-center text-[11px] font-semibold shrink-0" aria-hidden="true">
                                                {{ (u.firstname || '?').charAt(0) }}{{ (u.lastname || '').charAt(0) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                                                    {{ u.firstname }} {{ u.lastname }}
                                                    <span v-if="u.is_masked" class="ml-1 px-1 py-0.5 rounded text-[9px] font-mono bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">MASKED</span>
                                                </p>
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ u.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 hidden sm:table-cell">
                                        <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">{{ u.program?.code || '—' }}</span>
                                    </td>
                                    <td class="px-3 py-3 hidden md:table-cell">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(u.application?.created_at || u.created_at) }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="stage-badge" :class="statusBadgeClass(u.status)">{{ u.status || '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <button
                                            @click.stop="selectUser(u)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg text-sky-700 border border-sky-300/50 bg-sky-50/60 hover:bg-sky-50 dark:text-sky-300 dark:border-sky-700/40 dark:bg-sky-900/10 dark:hover:bg-sky-900/20 transition"
                                            :aria-label="`Interview ${u.firstname} ${u.lastname}`"
                                        >
                                            Interview
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between shrink-0">
                        <p class="text-[11px] text-gray-400 dark:text-gray-500">Page {{ currentPage }} / {{ totalPages }}</p>
                        <div class="flex items-center gap-1.5">
                            <button @click="currentPage--" :disabled="currentPage === 1" class="w-6 h-6 flex items-center justify-center rounded border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition" aria-label="Previous page">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button @click="currentPage++" :disabled="currentPage === totalPages" class="w-6 h-6 flex items-center justify-center rounded border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition" aria-label="Next page">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right: trend chart + program slots -->
                <div class="flex flex-col gap-4">
                    <!-- Chart -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card flex flex-col">
                        <div class="px-5 pt-5 pb-3 flex items-start justify-between shrink-0">
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Application Trends</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Daily overview</p>
                            </div>
                            <div class="relative shrink-0">
                                <button @click="showDateFilter = !showDateFilter" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition" aria-label="Open date range filter">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Filter
                                </button>
                                <div v-if="showDateFilter" class="absolute right-0 mt-1.5 w-60 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-20 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Date range</span>
                                        <button @click="showDateFilter = false" class="text-gray-400 hover:text-gray-600 p-0.5 rounded" aria-label="Close date filter"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                    </div>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Start</label>
                                            <input type="date" v-model="startDateFilter" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-maroon-light focus:ring-maroon-light"/>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">End</label>
                                            <input type="date" v-model="endDateFilter" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-maroon-light focus:ring-maroon-light"/>
                                        </div>
                                        <button @click="applyFilters" class="w-full px-3 py-2 bg-maroon-light text-white text-xs font-semibold rounded-lg hover:bg-maroon transition">Apply</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="px-5 pb-5" style="height: 180px;">
                            <LineChart :chart-data="chartDataset" :options="chartOptions" class="w-full h-full"/>
                        </div>
                    </div>

                    <!-- Program slots -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5 flex-1">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Program Slots</h2>
                        <div v-if="assignedPrograms.length === 0" class="text-xs text-gray-400 dark:text-gray-500 py-4 text-center">No programs assigned</div>
                        <div class="space-y-2.5">
                            <div
                                v-for="p in assignedPrograms"
                                :key="p.id"
                                class="flex items-center justify-between"
                            >
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">{{ p.code }}</p>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ p.name }}</p>
                                </div>
                                <span
                                    class="shrink-0 text-xs font-bold tabular-nums px-2 py-0.5 rounded"
                                    :class="p.slots != null && p.slots <= LOW_SLOT_THRESHOLD
                                        ? 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'
                                        : 'bg-sky-50 text-sky-700 dark:bg-sky-900/20 dark:text-sky-300'"
                                >{{ p.slots ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Quick actions ─────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <a
                        v-for="action in [
                            { label: 'All Applications', href: '/interviewer-applications', desc: 'Full application list'    },
                            { label: 'My Programs',      href: '/interviewer-programs',     desc: 'Assigned programs'        },
                            ...(isAdminOrSuperAdmin ? [{ label: 'Admin Dashboard', href: '/dashboard', desc: 'Back to admin overview' }] : []),
                        ]"
                        :key="action.href"
                        :href="action.href"
                        class="group flex flex-col gap-2 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 hover:border-maroon-100 hover:bg-maroon-50/40 dark:hover:border-maroon-800/40 dark:hover:bg-maroon-900/10 transition"
                    >
                        <p class="text-[11px] font-semibold text-gray-700 dark:text-gray-300 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition">{{ action.label }}</p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 leading-tight">{{ action.desc }}</p>
                    </a>
                </div>
            </div>

        </div><!-- /dash-shell-scroll -->

        <!-- ── UserDetailsModal ──────────────────────────────────────── -->
        <UserDetailsModal
            :selected-user="selectedUser"
            :selected-user-files="selectedUserFiles"
            :current-user="user"
            :available-programs="assignedPrograms"
            :is-changing-course="false"
            :course-change-message="''"
            :change-course-selected-id="''"
            @close="closeUserCard"
            @open-image="openImageModal"
        >
            <template #top-actions>
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl space-y-3">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Interview Actions</h4>

                    <!-- Interview start time -->
                    <div v-if="interviewStartTime" class="flex items-center gap-2 px-3 py-2 bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-700/40 rounded-lg text-xs text-sky-700 dark:text-sky-300">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Started {{ formatDateTime(interviewStartTime) }}
                    </div>

                    <!-- Start / cancel interview -->
                    <div class="flex gap-2">
                        <button
                            v-if="!interviewStartTime"
                            @click="beginInterview"
                            class="flex-1 px-3 py-2 bg-sky-600 text-white rounded-lg hover:bg-sky-700 transition font-medium text-xs min-h-[36px]"
                        >Start Interview</button>
                        <button
                            v-if="interviewStartTime"
                            @click="cancelInterview"
                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition font-medium text-xs min-h-[36px]"
                        >Cancel Interview</button>
                    </div>

                    <!-- Program selection -->
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Select Program</label>
                        <select
                            v-model="selectedProgramId"
                            class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-maroon-light focus:ring-maroon-light"
                        >
                            <option value="">— Choose program —</option>
                            <option v-for="p in assignedPrograms" :key="p.id" :value="p.id">
                                {{ p.code }} – {{ p.name }} ({{ p.slots }} slots)
                            </option>
                        </select>
                        <p v-if="selectedProgramId && !isApplicantQualified" class="mt-1 text-[10px] text-amber-600 dark:text-amber-400">
                            Applicant does not meet requirements for this program.
                        </p>
                    </div>

                    <!-- Interview notes -->
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400 mb-1">Notes <span class="text-gray-400">(optional)</span></label>
                        <textarea
                            v-model="interviewNotes"
                            rows="2"
                            maxlength="1000"
                            placeholder="Interview notes…"
                            class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white resize-none focus:border-maroon-light focus:ring-maroon-light"
                        ></textarea>
                    </div>

                    <!-- Accept / Reject -->
                    <div class="flex gap-2">
                        <button
                            @click="promptAccept"
                            :disabled="isSubmitting"
                            class="flex-1 px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-xs min-h-[36px] disabled:opacity-60"
                        >Accept</button>
                        <button
                            @click="promptReject"
                            :disabled="isSubmitting"
                            class="flex-1 px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium text-xs min-h-[36px] disabled:opacity-60"
                        >Reject</button>
                    </div>
                </div>
            </template>
        </UserDetailsModal>

        <!-- ── ImageViewer ───────────────────────────────────────────── -->
        <ImageViewer :show="showImageModal" :src="previewImage" :alt="previewAlt" @close="closeImageModal"/>

        <!-- ── Cancel interview confirm ──────────────────────────────── -->
        <ChangesConfirmationModal
            :show="showCancelModal"
            title="Cancel Interview?"
            subtitle="This will clear the interview start time. The applicant will remain in your queue."
            confirm-text="Yes, Cancel"
            cancel-text="Keep Going"
            confirm-button-class="bg-red-600 hover:bg-red-700 text-white"
            :hide-table="true"
            :loading="isCancellingInterview"
            @confirm="confirmCancelInterview"
            @cancel="showCancelModal = false"
        />

        <!-- ── Accept confirm ────────────────────────────────────────── -->
        <ChangesConfirmationModal
            :show="showAcceptModal"
            title="Accept Applicant?"
            :subtitle="`This will accept the applicant into the selected program and forward to medical processing.`"
            confirm-text="Yes, Accept"
            cancel-text="Cancel"
            confirm-button-class="bg-green-600 hover:bg-green-700 text-white"
            :hide-table="true"
            :loading="isSubmitting"
            @confirm="acceptApplication"
            @cancel="showAcceptModal = false"
        />

        <!-- ── Reject confirm ────────────────────────────────────────── -->
        <ChangesConfirmationModal
            :show="showRejectModal"
            title="Reject Applicant?"
            subtitle="This action cannot be undone. The applicant will be notified."
            confirm-text="Yes, Reject"
            cancel-text="Cancel"
            confirm-button-class="bg-red-600 hover:bg-red-700 text-white"
            :hide-table="true"
            :loading="isSubmitting"
            @confirm="rejectApplication"
            @cancel="showRejectModal = false"
        />

    </InterviewerLayout>
</template>
