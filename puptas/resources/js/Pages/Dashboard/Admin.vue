<!--
  Admin Dashboard — PUPTAS Admissions Operations
  ───────────────────────────────────────────────
  Workflow-focused dashboard for Admin (role 2).
  Emphasizes pending workload, admissions pipeline, applicant
  activity, and quick access to day-to-day admin functions.

  Data is served via DashboardController@index (role_id === 2 branch).
  Charts use vue-chart-3 wrapping Chart.js v3.
-->
<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { LineChart } from 'vue-chart-3'
import {
    Chart as ChartJS,
    LineController, LineElement,
    CategoryScale, LinearScale,
    PointElement, Tooltip, Legend, Filler,
} from 'chart.js'
import AppLayout from '@/Layouts/AppLayout.vue'
import UserDetailsModal from '@/Pages/Applications/UserDetailsModal.vue'
import ImageViewer from '@/Components/ImageViewer.vue'
import { useMaskingState } from '@/Composables/useMaskingState'

ChartJS.register(LineController, LineElement, CategoryScale, LinearScale, PointElement, Tooltip, Legend, Filler)

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    user:         { type: Object, default: null },
    allUsers:     { type: Array,  default: () => [] },
    summary:      { type: Object, default: () => ({ total: 0, accepted: 0, pending: 0, returned: 0 }) },
    stageSummary: { type: Object, default: () => ({
        document_evaluator: 0, grade_evaluator: 0, interviewer: 0,
        medical: 0, records: 0, enrollment: 0,
    })},
    chartData:    { type: Object, default: () => ({ submitted: [], accepted: [], returned: [], labels: [] }) },
    filters:      { type: Object, default: () => ({ start_date: '', end_date: '' }) },
})

const { isUnmasked } = useMaskingState()

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

// ── Applicant modal ───────────────────────────────────────────────────────────
const selectedUser      = ref(null)
const selectedUserFiles = ref({})
const showImageModal    = ref(false)
const previewImage      = ref('')
const previewAlt        = ref('Document preview')
const searchQuery       = ref('')
const currentPage       = ref(1)

const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    const users = props.allUsers || []
    if (!q) return users
    return users.filter(u =>
        `${u.firstname} ${u.lastname}`.toLowerCase().includes(q) ||
        (u.email || '').toLowerCase().includes(q)
    )
})

const PAGE_SIZE  = 5
const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / PAGE_SIZE)))
const displayedUsers = computed(() => {
    const start = (currentPage.value - 1) * PAGE_SIZE
    return filteredUsers.value.slice(start, start + PAGE_SIZE)
})
watch(searchQuery, () => { currentPage.value = 1 })

const selectUser = async (u) => {
    try {
        const res = await window.axios.get(`/admin-dashboard/user-files/${u.id}`)
        selectedUser.value = {
            ...u,
            ...res.data.user,
            application: { ...res.data.user.application, processes: res.data.user.application?.processes || [] },
            grades: res.data.user.grades || null,
        }
        selectedUserFiles.value = res.data.uploadedFiles || {}
    } catch {
        selectedUser.value = u
        selectedUserFiles.value = {}
    }
}

const closeUserCard  = () => { selectedUser.value = null; selectedUserFiles.value = {} }
const openImageModal = (file, label = '') => {
    const src = typeof file === 'string' ? file : file?.url || ''
    if (!src) return
    previewImage.value = src
    previewAlt.value   = label || file?.original_name || 'Document preview'
    showImageModal.value = true
}
const closeImageModal = () => { showImageModal.value = false; previewImage.value = '' }

// Snackbar
const snackbar = ref({ show: false, message: '', type: 'success' });
let snackbarTimer = null;
const showSnackbar = (message, type = 'success') => {
  snackbar.value = { show: true, message, type };
  clearTimeout(snackbarTimer);
  snackbarTimer = setTimeout(() => { snackbar.value.show = false; }, 5000);
};

const tagApplication = async () => {
    try {
        const res = await window.axios.post(`/admin-dashboard/tag/${selectedUser.value.id}`)
        showSnackbar('Tagged as officially enrolled.')
        if (selectedUser.value?.application) {
            selectedUser.value.application.enrollment_status = res.data.enrollment_status || 'officially_enrolled'
            selectedUser.value.application.status = res.data.application_status || 'accepted'
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to tag application.';
        showSnackbar(msg, 'error');
        if ([400, 409, 422].includes(e.response?.status)) {
            router.reload({ only: ['pendingUsers', 'summary', 'chartData'], preserveState: true, preserveScroll: true });
            if (selectedUser.value?.id) {
                selectUser(selectedUser.value);
            }
        }
    }
}

const untagApplication = async () => {
    try {
        await window.axios.post(`/admin-dashboard/untag/${selectedUser.value.id}`)
        showSnackbar('Reverted to temporary enrolled.')
        if (selectedUser.value?.application) {
            selectedUser.value.application.enrollment_status = 'temporary'
            selectedUser.value.application.status = 'cleared_for_enrollment'
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to untag application.';
        showSnackbar(msg, 'error');
        if ([400, 409, 422].includes(e.response?.status)) {
            router.reload({ only: ['pendingUsers', 'summary', 'chartData'], preserveState: true, preserveScroll: true });
            if (selectedUser.value?.id) {
                selectUser(selectedUser.value);
            }
        }
    }
}

// ── Workload cards ────────────────────────────────────────────────────────────
const workloadItems = computed(() => {
    const s = props.stageSummary || {}
    return [
        {
            label:  'Document Evaluation',
            value:  s.document_evaluator ?? 0,
            icon:   'doc',
            color:  'amber',
            hex:    '#D97706',
            href:   '/evaluator-dashboard',
            urgent: (s.document_evaluator ?? 0) > 20,
        },
        {
            label:  'Grade Evaluation',
            value:  s.grade_evaluator ?? 0,
            icon:   'grade',
            color:  'violet',
            hex:    '#7C3AED',
            href:   '/evaluator-dashboard',
            urgent: (s.grade_evaluator ?? 0) > 20,
        },
        {
            label:  'Interview Queue',
            value:  s.interviewer ?? 0,
            icon:   'interview',
            color:  'sky',
            hex:    '#0369A1',
            href:   '/interviewer-dashboard',
            urgent: (s.interviewer ?? 0) > 15,
        },
        {
            label:  'Medical Processing',
            value:  s.medical ?? 0,
            icon:   'medical',
            color:  'teal',
            hex:    '#0F766E',
            href:   null,
            urgent: false,
        },
        {
            label:  'Awaiting Enrollment',
            value:  s.records ?? 0,
            icon:   'records',
            color:  'purple',
            hex:    '#6D28D9',
            href:   '/admin/records',
            urgent: false,
        },
        {
            label:  'Officially Enrolled',
            value:  s.enrollment ?? 0,
            icon:   'enrolled',
            color:  'green',
            hex:    '#15803D',
            href:   '/admin/records',
            urgent: false,
        },
    ]
})

// ── Quick actions ─────────────────────────────────────────────────────────────
const quickActions = [
    { label: 'Review Applications', href: '/applications',           desc: 'All applicant submissions' },
    { label: 'Document Evaluation', href: '/evaluator-dashboard',    desc: 'Pending document review' },
    { label: 'Grade Evaluation',    href: '/evaluator-dashboard',    desc: 'Academic record review' },
    { label: 'Interview Queue',     href: '/interviewer-dashboard',  desc: 'Scheduled interviews' },
    { label: 'Records & Enrollment',href: '/admin/records',          desc: 'Enrollment processing' },
    { label: 'Reports',             href: '/admin/reports',          desc: 'Generate and export reports' },
]

// ── Chart ─────────────────────────────────────────────────────────────────────
const chartDataset = computed(() => ({
    labels: props.chartData.labels || [],
    datasets: [
        {
            label:            'Submitted',
            data:             props.chartData.submitted || [],
            borderColor:      '#800000',
            backgroundColor:  'rgba(128, 0, 0, 0.08)',
            fill:             true,
            tension:          0.4,
            pointBackgroundColor: '#800000',
            pointBorderColor:     '#ffffff',
            pointBorderWidth:     2,
            pointRadius:          3,
            pointHoverRadius:     5,
        },
        {
            label:            'Accepted',
            data:             props.chartData.accepted || [],
            borderColor:      '#15803D',
            backgroundColor:  'rgba(21, 128, 61, 0.07)',
            fill:             true,
            tension:          0.4,
            pointBackgroundColor: '#15803D',
            pointBorderColor:     '#ffffff',
            pointBorderWidth:     2,
            pointRadius:          3,
            pointHoverRadius:     5,
        },
        {
            label:            'Returned',
            data:             props.chartData.returned || [],
            borderColor:      '#B91C1C',
            backgroundColor:  'rgba(185, 28, 28, 0.06)',
            fill:             true,
            tension:          0.4,
            pointBackgroundColor: '#B91C1C',
            pointBorderColor:     '#ffffff',
            pointBorderWidth:     2,
            pointRadius:          3,
            pointHoverRadius:     5,
        },
    ],
}))

const chartOptions = {
    responsive:          true,
    maintainAspectRatio: false,
    interaction:         { mode: 'index', intersect: false },
    plugins: {
        legend: {
            display:  true,
            position: 'top',
            align:    'end',
            labels:   {
                boxWidth: 10, boxHeight: 10, borderRadius: 3,
                usePointStyle: true, pointStyle: 'circle',
                font: { size: 11, family: 'Figtree, sans-serif' },
                color: '#6B7280', padding: 16,
            },
        },
        tooltip: {
            backgroundColor: '#fff', titleColor: '#111827',
            bodyColor: '#374151', borderColor: '#E7E5E4',
            borderWidth: 1, cornerRadius: 8, padding: 12,
        },
    },
    scales: {
        x: {
            grid:   { display: false },
            ticks:  { color: '#9CA3AF', font: { size: 11 }, maxRotation: 0 },
            border: { dash: [4, 4] },
        },
        y: {
            beginAtZero: true,
            grid:  { color: 'rgba(156, 163, 175, 0.12)' },
            ticks: { color: '#9CA3AF', font: { size: 11 }, callback: v => v.toLocaleString() },
        },
    },
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const statusBadgeClass = (status) => {
    const s = (status || '').toLowerCase()
    if (['accepted', 'cleared_for_enrollment', 'officially_enrolled'].includes(s))
        return 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300'
    if (s === 'submitted')
        return 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'
    if (s === 'returned')
        return 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300'
    return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
}

const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
    <Head title="Admissions Dashboard" />

    <AppLayout>
        <template #title>
            <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">Admissions Dashboard</span>
        </template>

        <div class="dash-shell-scroll space-y-6 pb-8">

            <!-- ── Page header ──────────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Admissions Overview
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Your admissions workload, applicant pipeline, and activity — PUP Taguig PUPTAS
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        href="/applications"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-maroon-light text-white hover:bg-maroon transition shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        All Applications
                    </Link>
                </div>
            </div>

            <!-- ── Summary cards row ─────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <!-- Total -->
                <div class="metric-card border-l-4 border-l-maroon-light animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Applications</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ (summary.total || 0).toLocaleString() }}</p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">All submitted this cycle</p>
                </div>
                <!-- Pending -->
                <div class="metric-card border-l-4 border-l-amber-500 animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Awaiting Evaluation</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ (summary.pending || 0).toLocaleString() }}</p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Submitted, not yet reviewed</p>
                </div>
                <!-- Accepted (interview passed) -->
                <div class="metric-card border-l-4 border-l-sky-600 animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Interview Passed</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-400">{{ (summary.accepted || 0).toLocaleString() }}</p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Cleared interview stage</p>
                </div>
                <!-- Returned -->
                <div class="metric-card border-l-4 border-l-red-600 animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Returned</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-red-600 dark:text-red-400">{{ (summary.returned || 0).toLocaleString() }}</p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Awaiting applicant correction</p>
                </div>
            </div>

            <!-- ── Workload pipeline ─────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Stage Workload</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Active queue at each processing stage — click to open that stage</p>
                    </div>
                    <Link href="/applications" class="text-xs font-medium text-maroon-light hover:text-maroon dark:text-maroon-200 transition">View all →</Link>
                </div>

                <!-- Funnel progress row -->
                <div class="flex items-stretch gap-0 mb-5 overflow-x-auto pb-1">
                    <template v-for="(stage, idx) in workloadItems" :key="stage.label">
                        <component
                            :is="stage.href ? 'a' : 'div'"
                            :href="stage.href || undefined"
                            class="group flex flex-col items-center justify-between flex-1 min-w-[90px] py-3 px-2 transition-all duration-150 relative"
                            :class="[
                                stage.href ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded-lg' : '',
                            ]"
                        >
                            <!-- Stage value -->
                            <div class="text-center">
                                <p
                                    class="text-xl font-bold tabular-nums leading-none"
                                    :style="{ color: stage.hex }"
                                >{{ stage.value.toLocaleString() }}</p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-tight font-medium">{{ stage.label }}</p>
                            </div>
                            <!-- Urgent badge -->
                            <div v-if="stage.urgent" class="mt-2">
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    High volume
                                </span>
                            </div>
                            <div v-else class="mt-2 h-[18px]"></div>

                            <!-- Color bar at bottom -->
                            <div class="mt-2 w-full h-1.5 rounded-full" :style="{ backgroundColor: stage.hex + '33' }">
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :style="{
                                        backgroundColor: stage.hex,
                                        width: summary.total > 0 ? Math.min(100, Math.round((stage.value / summary.total) * 100)) + '%' : '0%',
                                    }"
                                ></div>
                            </div>
                        </component>

                        <!-- Arrow separator (not after last) -->
                        <div
                            v-if="idx < workloadItems.length - 1"
                            class="flex items-center justify-center text-gray-300 dark:text-gray-700 self-center px-0.5 shrink-0"
                            aria-hidden="true"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ── Chart + recent applicants ────────────────────────────── -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- Trend chart (2/3) -->
                <div class="xl:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card">
                    <div class="px-5 pt-5 pb-3 flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Application Trends</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Daily volume of submissions, acceptances, and returns</p>
                        </div>
                        <!-- Date filter -->
                        <div class="relative shrink-0">
                            <button
                                @click="showDateFilter = !showDateFilter"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Date Range
                                <svg class="w-3 h-3 text-gray-400 transition-transform duration-150" :class="{ 'rotate-180': showDateFilter }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div v-if="showDateFilter" class="absolute right-0 mt-1.5 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-20 p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Custom range</span>
                                    <button @click="showDateFilter = false" class="text-gray-400 hover:text-gray-600 p-0.5 rounded" aria-label="Close">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">Start date</label>
                                        <input type="date" v-model="startDateFilter" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-maroon-light focus:ring-maroon-light"/>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">End date</label>
                                        <input type="date" v-model="endDateFilter" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-maroon-light focus:ring-maroon-light"/>
                                    </div>
                                    <button @click="applyFilters" class="w-full px-3 py-2 bg-maroon-light text-white text-xs font-semibold rounded-lg hover:bg-maroon transition">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 pb-5" style="height: 280px;">
                        <LineChart :chart-data="chartDataset" :options="chartOptions" class="w-full h-full"/>
                    </div>
                </div>

                <!-- Recent applicants (1/3) -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card flex flex-col">
                    <div class="px-5 pt-5 pb-3 flex items-center justify-between shrink-0">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recent Applicants</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Click a row to view details</p>
                        </div>
                        <Link href="/applications" class="text-xs font-medium text-maroon-light hover:text-maroon dark:text-maroon-200 transition">View all →</Link>
                    </div>

                    <!-- Search -->
                    <div class="px-5 pb-3 shrink-0">
                        <div class="relative">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Search applicant…"
                                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-maroon-light focus:border-maroon-light transition"
                            />
                        </div>
                    </div>

                    <!-- List -->
                    <div class="flex-1 divide-y divide-gray-100 dark:divide-gray-800 overflow-y-auto">
                        <template v-if="displayedUsers.length === 0">
                            <div class="px-5 py-10 text-center">
                                <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <p class="text-sm text-gray-400 dark:text-gray-500">
                                    {{ searchQuery ? 'No results found' : 'No applicants yet' }}
                                </p>
                            </div>
                        </template>

                        <button
                            v-for="u in displayedUsers"
                            :key="u.id"
                            @click="selectUser(u)"
                            class="w-full px-5 py-3 flex items-center gap-3 hover:bg-gray-50/70 dark:hover:bg-gray-800/50 transition text-left focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-800/50"
                        >
                            <!-- Avatar -->
                            <div class="w-8 h-8 rounded-full bg-maroon-50 text-maroon-light dark:bg-maroon-900/30 dark:text-maroon-200 flex items-center justify-center text-xs font-semibold shrink-0" aria-hidden="true">
                                {{ (u.firstname || '?').charAt(0) }}{{ (u.lastname || '').charAt(0) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                                    {{ u.firstname }} {{ u.lastname }}
                                    <span v-if="u.is_masked" class="ml-1 px-1 py-0.5 rounded text-[9px] font-mono bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">MASKED</span>
                                </p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">
                                    {{ u.application?.program?.code || '—' }} · {{ formatDate(u.application?.created_at) }}
                                </p>
                            </div>
                            <span class="shrink-0 stage-badge" :class="statusBadgeClass(u.application?.status)">
                                {{ u.application?.status || '—' }}
                            </span>
                        </button>
                    </div>

                    <!-- Pagination -->
                    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between shrink-0">
                        <p class="text-[11px] text-gray-400 dark:text-gray-500">Page {{ currentPage }} / {{ totalPages }}</p>
                        <div class="flex items-center gap-1.5">
                            <button
                                @click="currentPage--"
                                :disabled="currentPage === 1"
                                class="w-6 h-6 flex items-center justify-center rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs"
                                aria-label="Previous"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button
                                @click="currentPage++"
                                :disabled="currentPage === totalPages"
                                class="w-6 h-6 flex items-center justify-center rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs"
                                aria-label="Next"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Quick actions ──────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <Link
                        v-for="action in quickActions"
                        :key="action.href"
                        :href="action.href"
                        class="group flex flex-col gap-2 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 hover:border-maroon-100 hover:bg-maroon-50/40 dark:hover:border-maroon-800/40 dark:hover:bg-maroon-900/10 transition"
                    >
                        <div class="w-8 h-8 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 group-hover:border-maroon-light/30 group-hover:bg-maroon-50 dark:group-hover:bg-maroon-900/20 flex items-center justify-center transition shadow-sm">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-gray-700 dark:text-gray-300 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition leading-tight">{{ action.label }}</p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5 leading-tight">{{ action.desc }}</p>
                        </div>
                    </Link>
                </div>
            </div>

        </div><!-- /dash-shell-scroll -->

        <!-- ── Applicant detail modal ─────────────────────────────────────── -->
        <UserDetailsModal
            :selected-user="selectedUser"
            :selected-user-files="selectedUserFiles"
            :current-user="null"
            :available-programs="[]"
            :is-changing-course="false"
            :course-change-message="''"
            :change-course-selected-id="''"
            @close="closeUserCard"
            @open-image="openImageModal"
        >
            <template #top-actions>
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Application Details</h4>
                    <div class="space-y-2 text-sm">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                            <span :class="statusBadgeClass(selectedUser?.application?.status)" class="stage-badge mt-1 inline-flex">
                                {{ selectedUser?.application?.status || 'Unknown' }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Applied On</p>
                            <p class="font-medium text-gray-900 dark:text-white text-xs">{{ formatDate(selectedUser?.application?.created_at) }}</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <Link
                            :href="`/applications/user/${selectedUser?.id}`"
                            class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-maroon-light text-white rounded-lg hover:bg-maroon transition font-medium text-sm"
                        >
                            View Full Application
                        </Link>
                    </div>
                </div>
            </template>
        </UserDetailsModal>

        <!-- ── Image viewer ───────────────────────────────────────────────── -->
        <ImageViewer
            :show="showImageModal"
            :src="previewImage"
            :alt="previewAlt"
            @close="closeImageModal"
        />

        <!-- ── Snackbar ───────────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-3 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-3 opacity-0"
            >
                <div
                    v-if="snackbar.show"
                    class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[99999] px-5 py-3 text-white text-sm font-medium rounded-xl shadow-2xl flex items-center gap-3 pointer-events-auto"
                    :class="snackbar.type === 'error' ? 'bg-red-600 dark:bg-red-700' : 'bg-gray-900 dark:bg-gray-700'"
                    role="status"
                    aria-live="polite"
                >
                    <svg v-if="snackbar.type !== 'error'" class="w-4 h-4 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ snackbar.message }}
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
