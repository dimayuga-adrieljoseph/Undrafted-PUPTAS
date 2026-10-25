<!--
  Dashboard/Evaluator.vue — PUPTAS Document & Grade Evaluator Dashboard
  ───────────────────────────────────────────────────────────────────────
  Serves both Document Evaluator (role 3, stage=document_evaluator)
  and Grade Evaluator (role 8, stage=grade_evaluator) via EvaluatorLayout.

  All existing workflow interactions are preserved:
  - startReview / cancelReview  (POST /evaluator/start-review/{id}, cancel-review/{id})
  - passApplication              (POST /evaluator/pass-application/{id})
  - rejectApplication            (POST /evaluator/reject-application/{id})
  - flagApplication              (POST /evaluator/flag-application/{id})
  - returnApplication            (POST /dashboard/return-files/{user})
  - Auto-refresh every 10 s      (Inertia router.reload)

  Visual style follows the completed Admin / SuperAdmin dashboard redesign:
  - dash-shell-scroll, metric-card, stage-badge, rounded-card, shadow-card
  - Stage accent: amber for document_evaluator, violet for grade_evaluator
-->
<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { LineChart } from 'vue-chart-3'
import {
    Chart as ChartJS, LineController, LineElement,
    CategoryScale, LinearScale, PointElement, Tooltip, Legend, Filler,
} from 'chart.js'
import EvaluatorLayout from '@/Layouts/EvaluatorLayout.vue'
import ChangesConfirmationModal from '@/Components/ChangesConfirmationModal.vue'
import ImageViewer from '@/Components/ImageViewer.vue'
import UserDetailsModal from '@/Pages/Applications/UserDetailsModal.vue'
import { useMaskingState } from '@/Composables/useMaskingState'

ChartJS.register(LineController, LineElement, CategoryScale, LinearScale, PointElement, Tooltip, Legend, Filler)

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    user:         { type: Object, default: null },
    pendingUsers: { type: Array,  default: () => [] },
    summary:      { type: Object, default: () => ({ in_progress: 0, processed: 0 }) },
    chartData:    { type: Object, default: () => ({ submitted: [], accepted: [], returned: [], labels: [] }) },
    filters:      { type: Object, default: () => ({ start_date: '', end_date: '' }) },
    stage:        { type: String, default: '' },
})

const { isUnmasked } = useMaskingState()

// ── Stage identity ────────────────────────────────────────────────────────────
const currentStage = computed(() =>
    props.stage || (props.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator')
)
const isDocEval  = computed(() => currentStage.value === 'document_evaluator')
const stageLabel = computed(() => isDocEval.value ? 'Document Evaluation' : 'Grade Evaluation')
const stageHex   = computed(() => isDocEval.value ? '#D97706' : '#7C3AED')
const isAdminOrSuperAdmin = computed(() => [2, 7].includes(props.user?.role_id))
const viewAllLink = computed(() => currentStage.value === 'document_evaluator' ? '/document-evaluator-applications' : `/evaluator-applications?stage=${currentStage.value}`)

// ── Auto-refresh (10 s) ───────────────────────────────────────────────────────
let autoRefreshTimer = null
const refreshDashboard = () => {
    router.reload({ only: ['pendingUsers', 'summary', 'chartData'], preserveState: true, preserveScroll: true })
}
onMounted(() => { autoRefreshTimer = setInterval(refreshDashboard, 10000) })
onBeforeUnmount(() => { if (autoRefreshTimer) clearInterval(autoRefreshTimer) })

// ── Date filter ───────────────────────────────────────────────────────────────
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

// ── Applicant queue ───────────────────────────────────────────────────────────
const searchQuery  = ref('')
const currentPage  = ref(1)
const PAGE_SIZE    = 8

const filteredApplicants = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    const list = props.pendingUsers || []
    if (!q) return list
    return list.filter(a =>
        `${a.firstname} ${a.lastname}`.toLowerCase().includes(q) ||
        (a.email || '').toLowerCase().includes(q)
    )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filteredApplicants.value.length / PAGE_SIZE)))
const displayedApplicants = computed(() => {
    const s = (currentPage.value - 1) * PAGE_SIZE
    return filteredApplicants.value.slice(s, s + PAGE_SIZE)
})
watch(searchQuery, () => { currentPage.value = 1 })

// ── User selection & file fetch ───────────────────────────────────────────────
const selectedUser      = ref(null)
const selectedUserFiles = ref({})

const selectUser = async (user) => {
    try {
        const res = await axios.get(`/dashboard/user-files/${user.id}?stage=${currentStage.value}`)
        const d = res.data.user
        selectedUser.value = {
            ...user,
            ...d,
            application: {
                ...d.application,
                processes:    d.application?.processes    || [],
                program:      d.application?.program      || null,
                second_choice: d.application?.second_choice || null,
                third_choice:  d.application?.third_choice  || null,
            },
            grades:         d.grades          || null,
            pipeline_status: user.pipeline_status,
        }
        selectedUserFiles.value = res.data.uploadedFiles || {}
    } catch (e) {
        console.error('Failed to load applicant:', e)
        showToast('Failed to load applicant data.', 'error')
    }
}

const closeUserCard = () => {
    selectedUser.value      = null
    selectedUserFiles.value = {}
    isEvaluating.value      = false
    filesToReturn.value     = {}
    returnNote.value        = ''
    refreshDashboard()
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

// ── Toast ─────────────────────────────────────────────────────────────────────
const toastMsg     = ref('')
const toastType    = ref('success')
const toastVisible = ref(false)
let   toastTimer   = null
const showToast = (msg, type = 'success') => {
    if (toastTimer) clearTimeout(toastTimer)
    toastMsg.value     = msg
    toastType.value    = type
    toastVisible.value = true
    toastTimer = setTimeout(() => { toastVisible.value = false }, 5000)
}

// ── Review workflow state ─────────────────────────────────────────────────────
const isEvaluating         = ref(false)
const filesToReturn        = ref({})
const returnNote           = ref('')
const requiresPromissoryNote = ref(false)
const evaluationError      = ref('')
const isSubmitting         = ref(false)
const isStartingReview     = ref(false)
const isCancellingReview   = ref(false)
const showCancelModal      = ref(false)
const showPassModal        = ref(false)
const showReturnModal      = ref(false)

const returnNoteCharCount = computed(() => returnNote.value.length)

const isEvaluationCompleted = computed(() => {
    if (!selectedUser.value?.application?.processes) return false
    const p = selectedUser.value.application.processes.find(x => x.stage === currentStage.value)
    return p && p.status === 'completed'
})
const hasStartedReview = computed(() => {
    if (!selectedUser.value?.application?.processes) return false
    const p = selectedUser.value.application.processes.find(x => x.stage === currentStage.value)
    return p && !!p.started_at
})
const reviewStartTime = computed(() => {
    const p = selectedUser.value?.application?.processes?.find(x => x.stage === currentStage.value)
    return p?.started_at || null
})

const startEvaluation = () => { isEvaluating.value = true; evaluationError.value = '' }
const cancelEvaluation = () => { isEvaluating.value = false; filesToReturn.value = {}; returnNote.value = '' }

const startReview = async () => {
    const processes = selectedUser.value.application.processes
    const idx = processes.findIndex(p => p.stage === currentStage.value)
    if (idx === -1) return showToast('Error finding application process.', 'error')
    isStartingReview.value = true
    try {
        const res = await axios.post(`/evaluator/start-review/${processes[idx].id}`)
        selectedUser.value = {
            ...selectedUser.value,
            application: {
                ...selectedUser.value.application,
                processes: processes.map((p, i) => i === idx ? { ...p, started_at: res.data.started_at } : p),
            },
        }
        showToast('Review started.')
    } catch (err) {
        if (err.response?.status === 409) {
            try {
                const refetch = await axios.get(`/dashboard/user-files/${selectedUser.value.id}?stage=${currentStage.value}`)
                const d = refetch.data.user
                selectedUser.value = {
                    ...selectedUser.value, ...d,
                    application: { ...d.application, processes: d.application?.processes || [] },
                }
                refreshDashboard()
                showToast(err.response?.data?.message || 'Review was already started.', 'error')
            } catch { /* ignore */ }
        } else {
            const msg = err.response?.data?.message || 'Failed to start review.'
            showToast(msg, 'error')
            if ([400, 409, 422].includes(err.response?.status)) {
                refreshDashboard()
                if (selectedUser.value?.id) {
                    try {
                        const refetch = await axios.get(`/dashboard/user-files/${selectedUser.value.id}?stage=${currentStage.value}`)
                        const d = refetch.data.user
                        selectedUser.value = {
                            ...selectedUser.value, ...d,
                            application: { ...d.application, processes: d.application?.processes || [] },
                        }
                    } catch { /* ignore */ }
                }
            }
        }
    } finally {
        isStartingReview.value = false
    }
}

const cancelReview = () => { showCancelModal.value = true }

const confirmCancelReview = async () => {
    const processes = selectedUser.value.application.processes
    const idx = processes.findIndex(p => p.stage === currentStage.value)
    if (idx === -1) return
    isCancellingReview.value = true
    try {
        await axios.post(`/evaluator/cancel-review/${processes[idx].id}`)
        selectedUser.value = {
            ...selectedUser.value,
            application: {
                ...selectedUser.value.application,
                processes: processes.map((p, i) => i === idx ? { ...p, started_at: null, reviewed_by: null } : p),
            },
        }
        showToast('Review cancelled.', 'info')
        showCancelModal.value = false
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to cancel review.'
        showToast(msg, 'error')
        if ([400, 409, 422].includes(err.response?.status)) {
            refreshDashboard()
            if (selectedUser.value?.id) {
                try {
                    const refetch = await axios.get(`/dashboard/user-files/${selectedUser.value.id}?stage=${currentStage.value}`)
                    const d = refetch.data.user
                    selectedUser.value = {
                        ...selectedUser.value, ...d,
                        application: { ...d.application, processes: d.application?.processes || [] },
                    }
                } catch { /* ignore */ }
            }
        }
    } finally {
        isCancellingReview.value = false
    }
}

const promptReturn = () => {
    const selected = Object.keys(filesToReturn.value).filter(k => filesToReturn.value[k])
    if (isDocEval.value && selected.length === 0 && !returnNote.value.trim()) {
        return showToast('Please select at least one file or provide a reason.', 'error')
    }
    if (!isDocEval.value && !returnNote.value.trim()) {
        return showToast('Please provide a reason.', 'error')
    }
    showReturnModal.value = true
}

const submitReturn = async () => {
    evaluationError.value = ''
    if (returnNoteCharCount.value > 400) {
        evaluationError.value = 'The reason cannot exceed 400 characters.'
        showReturnModal.value = false
        return
    }
    isSubmitting.value = true
    try {
        const payload = {
            note: returnNote.value,
            requires_promissory_note: requiresPromissoryNote.value,
            ...(isDocEval.value
                ? { requires_guidance_office: true }
                : { requires_admission_office: true }),
        }
        await axios.post(`/evaluator/flag-application/${selectedUser.value.id}?stage=${currentStage.value}`, payload)
        showToast(isDocEval.value ? 'Flagged for Guidance Office.' : 'Flagged for Admissions Office.')
        showReturnModal.value = false
        closeUserCard()
    } catch (err) {
        showReturnModal.value = false
        const msg = err.response?.data?.message || err.response?.data?.errors?.note?.[0] || 'Action failed.'
        evaluationError.value = msg
        showToast(msg, 'error')
        if ([400, 409, 422].includes(err.response?.status)) {
            refreshDashboard()
            if (selectedUser.value?.id) {
                try {
                    const refetch = await axios.get(`/dashboard/user-files/${selectedUser.value.id}?stage=${currentStage.value}`)
                    const d = refetch.data.user
                    selectedUser.value = {
                        ...selectedUser.value, ...d,
                        application: { ...d.application, processes: d.application?.processes || [] },
                    }
                } catch { /* ignore */ }
            }
        }
    } finally {
        isSubmitting.value = false
    }
}

const submitPass = async () => {
    evaluationError.value = ''
    isSubmitting.value = true
    try {
        await axios.post(`/evaluator/pass-application/${selectedUser.value.id}?stage=${currentStage.value}`, { note: '' })
        showPassModal.value = false
        closeUserCard()
        showToast('Applicant passed successfully!')
    } catch (err) {
        showPassModal.value = false
        const msg = err.response?.data?.message || 'Failed to pass application.'
        evaluationError.value = msg
        showToast(msg, 'error')
        if ([400, 409, 422].includes(err.response?.status)) {
            refreshDashboard()
            if (selectedUser.value?.id) {
                try {
                    const refetch = await axios.get(`/dashboard/user-files/${selectedUser.value.id}?stage=${currentStage.value}`)
                    const d = refetch.data.user
                    selectedUser.value = {
                        ...selectedUser.value, ...d,
                        application: { ...d.application, processes: d.application?.processes || [] },
                    }
                } catch { /* ignore */ }
            }
        }
    } finally {
        isSubmitting.value = false
    }
}

// ── Status helpers ────────────────────────────────────────────────────────────
const pipelineStatusLabel = (s) => {
    const map = {
        for_evaluation: 'For Evaluation', evaluation_returned: 'Returned', evaluation_passed: 'Passed',
        for_interview: 'For Interview', interview_returned: 'Returned', interview_passed: 'Passed',
        for_medical: 'For Medical', medical_cleared: 'Medical Cleared', for_records: 'For Records',
        officially_enrolled: 'Enrolled', rejected: 'Rejected', submitted: 'Submitted', returned: 'Returned',
    }
    return map[s] || (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : 'Unknown')
}

const pipelineStatusClass = (s) => {
    if (['evaluation_passed', 'interview_passed', 'medical_cleared', 'officially_enrolled'].includes(s))
        return 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300'
    if (['evaluation_returned', 'interview_returned', 'rejected', 'returned'].includes(s))
        return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'
    if (['for_evaluation', 'submitted'].includes(s))
        return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
    if (s === 'for_interview') return 'bg-sky-50 text-sky-700 dark:bg-sky-900/20 dark:text-sky-300'
    if (s === 'for_medical')   return 'bg-teal-50 text-teal-700 dark:bg-teal-900/20 dark:text-teal-300'
    if (s === 'for_records')   return 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-300'
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
    <Head :title="isDocEval ? 'Document Evaluation' : 'Grade Evaluation'" />
    <EvaluatorLayout>
        <!-- Success/Error Toast Notification -->
        <Teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="transform opacity-0 translate-y-[-1rem]"
                enter-to-class="transform opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="transform opacity-100 translate-y-0"
                leave-to-class="transform opacity-0 translate-y-[-1rem]"
            >
                <div v-if="toastVisible" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-[99999] w-full max-w-md px-4 pointer-events-auto">
                    <div :class="['rounded-lg shadow-2xl overflow-hidden border', toastType === 'success' ? 'bg-green-50 dark:bg-green-900 border-green-500 dark:border-green-400' : 'bg-red-50 dark:bg-red-900 border-red-500 dark:border-red-400']">
                        <div class="p-4 flex items-start">
                            <svg v-if="toastType === 'success'" class="w-5 h-5 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="w-5 h-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                <p :class="['text-sm font-medium', toastType === 'success' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200']">{{ toastMsg }}</p>
                            </div>
                            <div class="ml-4 flex-shrink-0 flex">
                                <button @click="toastVisible = false" :class="['rounded-md inline-flex focus:outline-none', toastType === 'success' ? 'text-green-500 hover:text-green-600' : 'text-red-500 hover:text-red-600']">
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

        <!-- ── Toast ──────────────────────────────────────────────────────── -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-3 opacity-0"
        >
            <div
                v-if="toastVisible"
                role="status"
                aria-live="polite"
                class="fixed top-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium max-w-sm"
                :class="toastType === 'success'
                    ? 'bg-green-50 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-200 dark:border-green-700'
                    : toastType === 'info'
                    ? 'bg-sky-50 text-sky-800 border-sky-200 dark:bg-sky-900/30 dark:text-sky-200 dark:border-sky-700'
                    : 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-200 dark:border-red-700'"
            >
                <svg v-if="toastType === 'success'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <svg v-else-if="toastType === 'info'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ toastMsg }}
                <button @click="toastVisible = false" class="ml-auto shrink-0 p-0.5 rounded hover:opacity-70 transition" aria-label="Dismiss">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </Transition>

        <div class="dash-shell-scroll space-y-6 pb-8">

            <!-- ── Page header ──────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <span
                            class="w-3 h-3 rounded-full"
                            :style="{ backgroundColor: stageHex }"
                            aria-hidden="true"
                        ></span>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                            {{ stageLabel }}
                        </h1>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <template v-if="isDocEval">
                            Review submitted admission documents and flag or pass applications for grade evaluation.
                        </template>
                        <template v-else>
                            Verify academic grades and pass qualified applicants to the interview stage.
                        </template>
                    </p>
                </div>
                <a
                    :href="isDocEval ? '/document-evaluator-applications' : '/evaluator-applications'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-maroon-light text-white hover:bg-maroon transition shadow-sm self-start sm:self-auto"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    All Applications
                </a>
            </div>

            <!-- ── Summary metric cards ───────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3">
                <div class="metric-card border-l-4 animate-slide-up" :style="{ borderLeftColor: stageHex }">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">In Queue</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                        {{ (summary?.in_progress ?? 0).toLocaleString() }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Awaiting evaluation</p>
                </div>
                <div class="metric-card border-l-4 border-l-green-600 animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Processed</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-green-700 dark:text-green-400">
                        {{ (summary?.processed ?? 0).toLocaleString() }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Evaluations completed</p>
                </div>
            </div>

            <!-- ── Main grid: queue (primary) + chart (secondary) ─────── -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- Applicant queue (2/3) -->
                <div class="xl:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card flex flex-col">
                    <!-- Queue header -->
                    <div class="px-5 pt-5 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                        <div>
                            <div class="flex items-center gap-3">
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Pending Queue</h2>
                                <Link :href="viewAllLink" class="text-xs text-maroon-light hover:text-maroon font-medium transition dark:text-red-400 dark:hover:text-red-300">
                                    View All
                                </Link>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ filteredApplicants.length }} applicant{{ filteredApplicants.length !== 1 ? 's' : '' }} — click a row to open evaluation
                            </p>
                        </div>
                        <!-- Search -->
                        <div class="relative sm:w-56 shrink-0">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
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
                        <table class="w-full text-sm" aria-label="Pending evaluation queue">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/30">
                                    <th class="text-left px-5 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Applicant</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden sm:table-cell">Program</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden md:table-cell">Submitted</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</th>
                                    <th class="px-5 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template v-if="displayedApplicants.length === 0">
                                    <tr>
                                        <td colspan="5" class="px-5 py-12 text-center">
                                            <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                                {{ searchQuery ? 'No results found' : 'Queue is empty — no pending evaluations' }}
                                            </p>
                                        </td>
                                    </tr>
                                </template>
                                <tr
                                    v-for="applicant in displayedApplicants"
                                    :key="applicant.id"
                                    @click="selectUser(applicant)"
                                    class="hover:bg-gray-50/70 dark:hover:bg-gray-800/50 cursor-pointer transition group"
                                >
                                    <!-- Applicant -->
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 rounded-full bg-maroon-50 text-maroon-light dark:bg-maroon-900/30 dark:text-maroon-200 flex items-center justify-center text-[11px] font-semibold shrink-0" aria-hidden="true">
                                                {{ (applicant.firstname || '?').charAt(0) }}{{ (applicant.lastname || '').charAt(0) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                                                    {{ applicant.firstname }} {{ applicant.lastname }}
                                                    <span v-if="applicant.is_masked" class="ml-1 px-1 py-0.5 rounded text-[9px] font-mono bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">MASKED</span>
                                                </p>
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ applicant.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Program -->
                                    <td class="px-3 py-3 hidden sm:table-cell">
                                        <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">{{ applicant.program?.code || '—' }}</span>
                                    </td>
                                    <!-- Submitted -->
                                    <td class="px-3 py-3 hidden md:table-cell">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(applicant.application?.created_at || applicant.created_at) }}</span>
                                    </td>
                                    <!-- Status -->
                                    <td class="px-3 py-3">
                                        <span class="stage-badge" :class="pipelineStatusClass(applicant.pipeline_status)">
                                            {{ pipelineStatusLabel(applicant.pipeline_status) }}
                                        </span>
                                    </td>
                                    <!-- Action -->
                                    <td class="px-5 py-3 text-right">
                                        <button
                                            @click.stop="selectUser(applicant)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg text-maroon-light border border-maroon-light/30 bg-maroon-50/60 hover:bg-maroon-50 dark:text-maroon-200 dark:border-maroon-700/40 dark:bg-maroon-900/10 dark:hover:bg-maroon-900/20 transition"
                                            :aria-label="`Review ${applicant.firstname} ${applicant.lastname}`"
                                        >
                                            Review
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
                            <button @click="currentPage--" :disabled="currentPage === 1" class="w-6 h-6 flex items-center justify-center rounded border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs" aria-label="Previous page">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button @click="currentPage++" :disabled="currentPage === totalPages" class="w-6 h-6 flex items-center justify-center rounded border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs" aria-label="Next page">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Trend chart (1/3) -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card flex flex-col">
                    <div class="px-5 pt-5 pb-3 flex items-start justify-between shrink-0">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Application Trends</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Daily volume overview</p>
                        </div>
                        <!-- Date filter -->
                        <div class="relative shrink-0">
                            <button
                                @click="showDateFilter = !showDateFilter"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                                aria-label="Open date range filter"
                            >
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
                    <div class="flex-1 px-5 pb-5 min-h-[200px]">
                        <LineChart :chart-data="chartDataset" :options="chartOptions" class="w-full h-full"/>
                    </div>
                </div>
            </div>

            <!-- ── Quick actions ─────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <a
                        v-for="action in [
                            { label: 'All Applications',    href: isDocEval ? '/document-evaluator-applications' : '/evaluator-applications', desc: 'Open full application list' },
                            { label: 'My Programs',         href: isDocEval ? '/document-evaluator-programs'    : '/evaluator-programs',       desc: 'Programs assigned to me'  },
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
            :available-programs="[]"
            :is-changing-course="false"
            :course-change-message="''"
            :change-course-selected-id="''"
            @close="closeUserCard"
            @open-image="openImageModal"
        >
            <template #top-actions>
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl space-y-3">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        {{ stageLabel }} Actions
                    </h4>

                    <!-- Review status info -->
                    <div v-if="hasStartedReview" class="flex items-center gap-2 px-3 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 rounded-lg text-xs text-amber-700 dark:text-amber-300">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Review started {{ formatDateTime(reviewStartTime) }}
                    </div>

                    <!-- Start / Cancel review -->
                    <div class="flex gap-2">
                        <button
                            v-if="!hasStartedReview && !isEvaluationCompleted"
                            @click="startReview"
                            :disabled="isStartingReview"
                            class="flex-1 px-3 py-2 bg-maroon-light text-white rounded-lg hover:bg-maroon transition font-medium text-xs min-h-[36px] disabled:opacity-60"
                        >
                            {{ isStartingReview ? 'Starting…' : 'Start Review' }}
                        </button>
                        <button
                            v-if="hasStartedReview && !isEvaluationCompleted"
                            @click="cancelReview"
                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition font-medium text-xs min-h-[36px]"
                        >
                            Cancel Review
                        </button>
                    </div>

                    <!-- Evaluation in progress -->
                    <template v-if="!isEvaluationCompleted">
                        <div v-if="!isEvaluating" class="flex gap-2">
                            <button
                                @click="showPassModal = true"
                                class="flex-1 px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-xs min-h-[36px]"
                            >Pass</button>
                            <button
                                @click="startEvaluation"
                                class="flex-1 px-3 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition font-medium text-xs min-h-[36px]"
                            >Return / Flag</button>
                        </div>

                        <!-- Return/flag form -->
                        <div v-else class="space-y-2">
                            <label class="block text-[11px] font-medium text-gray-600 dark:text-gray-400">Reason <span class="text-gray-400">({{ returnNoteCharCount }}/400)</span></label>
                            <textarea
                                v-model="returnNote"
                                rows="3"
                                maxlength="400"
                                placeholder="Describe the issue…"
                                class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white resize-none focus:border-maroon-light focus:ring-maroon-light"
                            ></textarea>
                            <p v-if="evaluationError" class="text-xs text-red-600 dark:text-red-400">{{ evaluationError }}</p>
                            <div class="flex gap-2">
                                <button @click="promptReturn" :disabled="isSubmitting" class="flex-1 px-3 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition font-medium text-xs min-h-[36px] disabled:opacity-60">{{ isSubmitting ? 'Submitting…' : 'Submit Flag' }}</button>
                                <button @click="cancelEvaluation" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition font-medium text-xs min-h-[36px]">Cancel</button>
                            </div>
                        </div>
                    </template>

                    <!-- Already evaluated -->
                    <div v-else class="flex items-center gap-2 px-3 py-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700/40 rounded-lg text-xs text-green-700 dark:text-green-300">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Evaluation complete
                    </div>
                </div>
            </template>
        </UserDetailsModal>

        <!-- ── ImageViewer ───────────────────────────────────────────── -->
        <ImageViewer :show="showImageModal" :src="previewImage" :alt="previewAlt" @close="closeImageModal"/>

        <!-- ── Cancel review confirm modal ──────────────────────────── -->
        <ChangesConfirmationModal
            :show="showCancelModal"
            title="Cancel Review?"
            message="This will clear the review start time. The application will remain in your queue."
            confirm-label="Yes, Cancel"
            cancel-label="Keep Reviewing"
            @confirm="confirmCancelReview"
            @cancel="showCancelModal = false"
        />

        <!-- ── Pass confirm modal ────────────────────────────────────── -->
        <ChangesConfirmationModal
            :show="showPassModal"
            title="Pass Application?"
            :message="`This will mark the application as passed and forward it to the ${isDocEval ? 'grade evaluation' : 'interview'} stage.`"
            confirm-label="Yes, Pass"
            cancel-label="Cancel"
            @confirm="submitPass"
            @cancel="showPassModal = false"
        />

        <!-- ── Flag/return confirm modal ─────────────────────────────── -->
        <ChangesConfirmationModal
            :show="showReturnModal"
            title="Flag Application?"
            :message="`This will flag the application and notify the ${isDocEval ? 'Guidance Office' : 'Admissions Office'}.`"
            confirm-label="Yes, Flag"
            cancel-label="Cancel"
            @confirm="submitReturn"
            @cancel="showReturnModal = false"
        />

    </EvaluatorLayout>
</template>
