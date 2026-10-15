<!--
  SuperAdmin Dashboard — PUPTAS System Overview
  ──────────────────────────────────────────────
  Provides system-wide admissions visibility, pipeline health, operational
  metrics, recent audit activity, and administrative workload summaries.

  Data is served via DashboardController@index (role_id === 7 branch).
  Charts use vue-chart-3 wrapping Chart.js v3.
-->
<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { LineChart, BarChart, DoughnutChart } from 'vue-chart-3'
import {
    Chart as ChartJS,
    LineController, BarController, DoughnutController,
    LineElement, BarElement, ArcElement,
    CategoryScale, LinearScale,
    PointElement, Tooltip, Legend, Filler,
} from 'chart.js'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import KpiCardSection from '@/Components/KpiCardSection.vue'
import { useMaskingState } from '@/Composables/useMaskingState'

ChartJS.register(
    LineController, BarController, DoughnutController,
    LineElement, BarElement, ArcElement,
    CategoryScale, LinearScale,
    PointElement, Tooltip, Legend, Filler,
)

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    user:             { type: Object,  default: null },
    allUsers:         { type: Array,   default: () => [] },
    summary:          { type: Object,  default: () => ({ total: 0, accepted: 0, pending: 0, returned: 0 }) },
    stageSummary:     { type: Object,  default: () => ({
        document_evaluator: 0, grade_evaluator: 0, interviewer: 0,
        medical: 0, records: 0, enrollment: 0,
    }) },
    chartData:        { type: Object,  default: () => ({ submitted: [], accepted: [], returned: [], labels: [] }) },
    filters:          { type: Object,  default: () => ({ start_date: '', end_date: '' }) },
    recentAuditLogs:  { type: Array,   default: () => [] },
    failedJobCount:   { type: Number,  default: 0 },
    staffCounts:      { type: Object,  default: () => ({}) },
    isMasked:         { type: Boolean, default: true },
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

// ── Computed pipeline totals ──────────────────────────────────────────────────
const total = computed(() => props.summary.total || 0)

const pipelineStages = computed(() => {
    const s = props.stageSummary || {}
    return [
        {
            key:   'document_evaluator',
            label: 'Document Evaluation',
            value: s.document_evaluator ?? 0,
            color: 'amber',
            hex:   '#D97706',
            icon:  'doc',
            route: '/evaluator-dashboard',
        },
        {
            key:   'grade_evaluator',
            label: 'Grade Evaluation',
            value: s.grade_evaluator ?? 0,
            color: 'violet',
            hex:   '#7C3AED',
            icon:  'grade',
            route: '/evaluator-dashboard',
        },
        {
            key:   'interviewer',
            label: 'Interview',
            value: s.interviewer ?? 0,
            color: 'sky',
            hex:   '#0369A1',
            icon:  'interview',
            route: '/interviewer-dashboard',
        },
        {
            key:   'medical',
            label: 'Medical Clearance',
            value: s.medical ?? 0,
            color: 'teal',
            hex:   '#0F766E',
            icon:  'medical',
            route: null,
        },
        {
            key:   'records',
            label: 'For Enrollment',
            value: s.records ?? 0,
            color: 'purple',
            hex:   '#6D28D9',
            icon:  'records',
            route: '/admin/records',
        },
        {
            key:   'enrollment',
            label: 'Enrolled',
            value: s.enrollment ?? 0,
            color: 'green',
            hex:   '#15803D',
            icon:  'enrolled',
            route: '/admin/records',
        },
    ]
})

// ── Top-level metric cards ────────────────────────────────────────────────────
const metricCards = computed(() => [
    {
        id:          'total',
        label:       'Total Applicants',
        value:       total.value,
        description: 'Applications submitted this cycle',
        accent:      'maroon',
        icon:        'users',
    },
    {
        id:          'pending',
        label:       'Awaiting Processing',
        value:       props.summary.pending ?? 0,
        description: 'Submitted, not yet evaluated',
        accent:      'amber',
        icon:        'clock',
    },
    {
        id:          'accepted',
        label:       'Interview Passed',
        value:       props.summary.accepted ?? 0,
        description: 'Cleared interview stage',
        accent:      'sky',
        icon:        'check',
    },
    {
        id:          'enrolled',
        label:       'Officially Enrolled',
        value:       props.stageSummary?.enrollment ?? 0,
        description: 'Enrollment confirmed by records',
        accent:      'green',
        icon:        'graduate',
    },
    {
        id:          'returned',
        label:       'Returned',
        value:       props.summary.returned ?? 0,
        description: 'Require applicant correction',
        accent:      'red',
        icon:        'return',
    },
])

// ── Chart: application trends (line) ─────────────────────────────────────────
const lineChartData = computed(() => ({
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

const lineChartOptions = {
    responsive:          true,
    maintainAspectRatio: false,
    interaction:         { mode: 'index', intersect: false },
    plugins: {
        legend: {
            display:  true,
            position: 'top',
            align:    'end',
            labels: {
                boxWidth:   10,
                boxHeight:  10,
                borderRadius: 3,
                usePointStyle: true,
                pointStyle:    'circle',
                font:       { size: 11, family: 'Figtree, sans-serif' },
                color:      '#6B7280',
                padding:    16,
            },
        },
        tooltip: {
            backgroundColor: '#fff',
            titleColor:      '#111827',
            bodyColor:       '#374151',
            borderColor:     '#E7E5E4',
            borderWidth:     1,
            cornerRadius:    8,
            padding:         12,
            boxShadow:       '0 4px 12px rgba(0,0,0,0.08)',
        },
    },
    scales: {
        x: {
            grid:  { display: false },
            ticks: { color: '#9CA3AF', font: { size: 11 }, maxRotation: 0 },
            border: { dash: [4, 4] },
        },
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(156, 163, 175, 0.12)', drawBorder: false },
            ticks: { color: '#9CA3AF', font: { size: 11 }, callback: v => v.toLocaleString() },
        },
    },
}

// ── Chart: pipeline bar ───────────────────────────────────────────────────────
const pipelineBarData = computed(() => ({
    labels: pipelineStages.value.map(s => s.label),
    datasets: [{
        label:            'Applications in stage',
        data:             pipelineStages.value.map(s => s.value),
        backgroundColor:  pipelineStages.value.map(s => s.hex + 'CC'),
        borderColor:      pipelineStages.value.map(s => s.hex),
        borderWidth:      1,
        borderRadius:     6,
    }],
}))

const pipelineBarOptions = {
    responsive:          true,
    maintainAspectRatio: false,
    indexAxis:           'y',
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#fff',
            titleColor:      '#111827',
            bodyColor:       '#374151',
            borderColor:     '#E7E5E4',
            borderWidth:     1,
            cornerRadius:    8,
            padding:         10,
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            grid: { color: 'rgba(156, 163, 175, 0.12)' },
            ticks: { color: '#9CA3AF', font: { size: 11 } },
        },
        y: {
            grid: { display: false },
            ticks: { color: '#4B5563', font: { size: 11, weight: '500' } },
        },
    },
}

// ── Audit log helpers ─────────────────────────────────────────────────────────
const actionAccent = (action) => {
    const map = {
        LOGIN:    { bg: 'bg-blue-50 dark:bg-blue-900/20',    text: 'text-blue-700 dark:text-blue-300',   dot: 'bg-blue-500' },
        LOGOUT:   { bg: 'bg-gray-50 dark:bg-gray-800',       text: 'text-gray-600 dark:text-gray-400',   dot: 'bg-gray-400' },
        CREATE:   { bg: 'bg-green-50 dark:bg-green-900/20',  text: 'text-green-700 dark:text-green-300', dot: 'bg-green-500' },
        UPDATE:   { bg: 'bg-amber-50 dark:bg-amber-900/20',  text: 'text-amber-700 dark:text-amber-300', dot: 'bg-amber-500' },
        DELETE:   { bg: 'bg-red-50 dark:bg-red-900/20',      text: 'text-red-700 dark:text-red-300',     dot: 'bg-red-500' },
        DOWNLOAD: { bg: 'bg-purple-50 dark:bg-purple-900/20',text: 'text-purple-700 dark:text-purple-300',dot: 'bg-purple-500' },
    }
    return map[action] ?? map.UPDATE
}

const logTypeAccent = (type) => {
    if (type === 'SECURITY') return 'text-red-600 dark:text-red-400'
    if (type === 'SYSTEM')   return 'text-blue-600 dark:text-blue-400'
    return 'text-amber-600 dark:text-amber-400'
}

const formatTimestamp = (ts) => {
    if (!ts) return '—'
    const d = new Date(ts)
    return d.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true })
}

const truncate = (str, n = 68) => {
    if (!str) return '—'
    return str.length > n ? str.slice(0, n) + '…' : str
}

// ── Staff role label helper ───────────────────────────────────────────────────
const roleLabelMap = { 2: 'Admin', 3: 'Doc Evaluator', 4: 'Interviewer', 5: 'Medical', 6: 'Registrar', 8: 'Grade Evaluator' }
const staffRoleRows = computed(() =>
    Object.entries(props.staffCounts || {}).map(([id, count]) => ({
        label: roleLabelMap[parseInt(id)] ?? `Role ${id}`,
        count,
    }))
)

// ── System health ─────────────────────────────────────────────────────────────
const systemStatus = computed(() => {
    if (props.failedJobCount > 5)  return { label: 'Degraded',  color: 'amber', icon: 'warn' }
    if (props.failedJobCount > 0)  return { label: 'Issues',    color: 'amber', icon: 'warn' }
    return                                { label: 'Operational', color: 'green', icon: 'ok'  }
})
</script>

<template>
    <Head title="System Overview — SuperAdmin" />

    <SuperAdminLayout>
        <template #title>
            <div class="flex items-center gap-2.5">
                <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">System Overview</span>
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-maroon-50 text-maroon-light border border-maroon-100 dark:bg-maroon-900/30 dark:text-maroon-200 dark:border-maroon-800/50">
                    <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 8 8" aria-hidden="true"><circle cx="4" cy="4" r="4"/></svg>
                    Superadmin
                </span>
            </div>
        </template>

        <div class="dash-shell-scroll space-y-6 pb-8">

            <!-- ── Page header ──────────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        System Overview
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Admissions operations, pipeline health, and system activity — PUP Taguig PUPTAS
                    </p>
                </div>
                <!-- Quick links -->
                <div class="flex items-center gap-2 flex-wrap">
                    <Link
                        href="/admin/audit-logs"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700 transition shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Audit Logs
                    </Link>
                    <Link
                        href="/dashboard/kpi"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-maroon-light text-white hover:bg-maroon dark:hover:bg-maroon-light transition shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        KPI Report
                    </Link>
                </div>
            </div>

            <!-- ── Executive metric cards ─────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div
                    v-for="card in metricCards"
                    :key="card.id"
                    class="metric-card animate-slide-up"
                    :class="{
                        'border-l-4 border-l-maroon-light': card.accent === 'maroon',
                        'border-l-4 border-l-amber-500':   card.accent === 'amber',
                        'border-l-4 border-l-sky-600':     card.accent === 'sky',
                        'border-l-4 border-l-green-700':   card.accent === 'green',
                        'border-l-4 border-l-red-700':     card.accent === 'red',
                    }"
                >
                    <!-- Icon -->
                    <div class="mb-3">
                        <!-- Users icon -->
                        <template v-if="card.icon === 'users'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-maroon-50 text-maroon-light dark:bg-maroon-900/30 dark:text-maroon-200">
                                <svg class="w-4.5 h-4.5 w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                        </template>
                        <!-- Clock icon -->
                        <template v-else-if="card.icon === 'clock'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </template>
                        <!-- Check icon -->
                        <template v-else-if="card.icon === 'check'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-sky-50 text-sky-700 dark:bg-sky-900/20 dark:text-sky-400">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </template>
                        <!-- Graduate icon -->
                        <template v-else-if="card.icon === 'graduate'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                </svg>
                            </div>
                        </template>
                        <!-- Return icon -->
                        <template v-else>
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                            </div>
                        </template>
                    </div>

                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 leading-tight">{{ card.label }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white leading-none">
                        {{ card.value.toLocaleString() }}
                    </p>
                    <p class="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500 leading-tight">{{ card.description }}</p>
                </div>
            </div>

            <!-- ── Main analytics grid ────────────────────────────────────── -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- Application trends chart (2/3) -->
                <div class="xl:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card">
                    <div class="px-5 pt-5 pb-3 flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Application Volume</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Daily submission, acceptance, and return trends</p>
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
                            <!-- Date picker dropdown -->
                            <div
                                v-if="showDateFilter"
                                class="absolute right-0 mt-1.5 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-20 p-4"
                            >
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Custom range</span>
                                    <button @click="showDateFilter = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-0.5 rounded" aria-label="Close date picker">
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
                                    <button
                                        @click="applyFilters"
                                        class="w-full px-3 py-2 bg-maroon-light text-white text-xs font-semibold rounded-lg hover:bg-maroon transition"
                                    >
                                        Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 pb-5" style="height: 260px;">
                        <LineChart :chart-data="lineChartData" :options="lineChartOptions" class="w-full h-full"/>
                    </div>
                </div>

                <!-- System health + staff counts (1/3) -->
                <div class="flex flex-col gap-4">
                    <!-- System health -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">System Status</h2>
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold"
                                :class="{
                                    'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400': systemStatus.color === 'green',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400': systemStatus.color === 'amber',
                                }"
                            >
                                <span class="w-1.5 h-1.5 rounded-full inline-block" :class="{ 'bg-green-500': systemStatus.color === 'green', 'bg-amber-500': systemStatus.color === 'amber' }"></span>
                                {{ systemStatus.label }}
                            </span>
                        </div>
                        <div class="space-y-2.5 text-sm">
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                                <span class="text-gray-600 dark:text-gray-400 text-xs">Failed Background Jobs</span>
                                <span
                                    class="text-xs font-semibold tabular-nums"
                                    :class="failedJobCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                                >
                                    {{ failedJobCount }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                                <span class="text-gray-600 dark:text-gray-400 text-xs">Email Queue</span>
                                <Link href="/admin/email-tracking" class="text-xs font-medium text-maroon-light hover:underline dark:text-maroon-200">View</Link>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800">
                                <span class="text-gray-600 dark:text-gray-400 text-xs">IDP Sync</span>
                                <Link href="/admin/system-settings/idp-sync-monitor" class="text-xs font-medium text-maroon-light hover:underline dark:text-maroon-200">Monitor</Link>
                            </div>
                            <div class="flex items-center justify-between py-1.5">
                                <span class="text-gray-600 dark:text-gray-400 text-xs">Audit Logs</span>
                                <Link href="/admin/audit-logs" class="text-xs font-medium text-maroon-light hover:underline dark:text-maroon-200">View all</Link>
                            </div>
                        </div>
                    </div>

                    <!-- Staff summary -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5 flex-1">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Active Staff</h2>
                        <div v-if="staffRoleRows.length === 0" class="text-xs text-gray-400 dark:text-gray-500 text-center py-4">No staff accounts found</div>
                        <div class="space-y-2">
                            <div
                                v-for="row in staffRoleRows"
                                :key="row.label"
                                class="flex items-center justify-between"
                            >
                                <span class="text-xs text-gray-600 dark:text-gray-400">{{ row.label }}</span>
                                <span class="text-xs font-semibold tabular-nums text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">{{ row.count }}</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                            <Link href="/users" class="text-xs font-medium text-maroon-light hover:underline dark:text-maroon-200">Manage Users →</Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Admissions pipeline ─────────────────────────────────────── -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                <!-- Pipeline bar chart -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Admissions Pipeline</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Applications currently at each stage</p>
                    </div>
                    <div style="height: 220px;">
                        <BarChart :chart-data="pipelineBarData" :options="pipelineBarOptions" class="w-full h-full"/>
                    </div>
                </div>

                <!-- Stage cards grid -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Stage Workload</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Active queue at each processing stage</p>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <component
                            :is="stage.route ? 'a' : 'div'"
                            v-for="stage in pipelineStages"
                            :key="stage.key"
                            :href="stage.route || undefined"
                            class="group rounded-xl border p-3 transition-all duration-150"
                            :class="[
                                stage.route ? 'cursor-pointer hover:shadow-md' : '',
                                'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-gray-50/50 dark:bg-gray-800/30'
                            ]"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span
                                    class="w-2 h-2 rounded-full shrink-0"
                                    :style="{ backgroundColor: stage.hex }"
                                    aria-hidden="true"
                                ></span>
                                <svg v-if="stage.route" class="w-3 h-3 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 font-medium leading-tight">{{ stage.label }}</p>
                            <p class="text-xl font-bold tabular-nums leading-none mt-1" :style="{ color: stage.hex }">
                                {{ stage.value.toLocaleString() }}
                            </p>
                        </component>
                    </div>
                </div>
            </div>

            <!-- ── KPI Performance Dashboard ──────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                <KpiCardSection />
            </div>

            <!-- ── Recent audit activity + recent applicants ───────────────── -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

                <!-- Recent audit logs -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card">
                    <div class="px-5 pt-5 pb-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recent Activity</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Latest system and audit events</p>
                        </div>
                        <Link
                            href="/admin/audit-logs"
                            class="text-xs font-medium text-maroon-light hover:text-maroon dark:text-maroon-200 transition"
                        >
                            View all →
                        </Link>
                    </div>

                    <!-- Log list -->
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template v-if="recentAuditLogs.length === 0">
                            <div class="px-5 py-8 text-center">
                                <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <p class="text-sm text-gray-400 dark:text-gray-500">No recent activity</p>
                            </div>
                        </template>

                        <div
                            v-for="log in recentAuditLogs"
                            :key="log.id"
                            class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition"
                        >
                            <!-- Action dot + badge -->
                            <div class="mt-0.5 shrink-0">
                                <span
                                    class="w-2 h-2 rounded-full block mt-1"
                                    :class="actionAccent(log.action_type).dot"
                                    aria-hidden="true"
                                ></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center flex-wrap gap-1.5 mb-0.5">
                                    <span
                                        class="stage-badge"
                                        :class="[actionAccent(log.action_type).bg, actionAccent(log.action_type).text]"
                                    >{{ log.action_type }}</span>
                                    <span v-if="log.log_type" class="text-[10px] font-medium" :class="logTypeAccent(log.log_type)">{{ log.log_type }}</span>
                                    <span v-if="log.module_name" class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ log.module_name }}</span>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 leading-snug">{{ truncate(log.description) }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500">{{ log.username || '—' }}</span>
                                    <span class="text-[10px] text-gray-300 dark:text-gray-600">·</span>
                                    <time class="text-[10px] text-gray-400 dark:text-gray-500">{{ formatTimestamp(log.created_at) }}</time>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800">
                        <Link href="/admin/audit-logs" class="text-xs font-medium text-maroon-light hover:underline dark:text-maroon-200">
                            Open full audit trail →
                        </Link>
                    </div>
                </div>

                <!-- Recent applicants -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card">
                    <div class="px-5 pt-5 pb-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recent Applicants</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Latest application submissions</p>
                        </div>
                        <Link
                            href="/applications"
                            class="text-xs font-medium text-maroon-light hover:text-maroon dark:text-maroon-200 transition"
                        >
                            View all →
                        </Link>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template v-if="allUsers.length === 0">
                            <div class="px-5 py-8 text-center">
                                <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <p class="text-sm text-gray-400 dark:text-gray-500">No applicants yet</p>
                            </div>
                        </template>

                        <div
                            v-for="applicant in allUsers.slice(0, 8)"
                            :key="applicant.id"
                            class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition"
                        >
                            <!-- Avatar -->
                            <div class="w-8 h-8 rounded-full bg-maroon-50 text-maroon-light dark:bg-maroon-900/30 dark:text-maroon-200 flex items-center justify-center text-xs font-semibold shrink-0" aria-hidden="true">
                                {{ (applicant.firstname || '?').charAt(0) }}{{ (applicant.lastname || '').charAt(0) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">
                                        {{ applicant.firstname }} {{ applicant.lastname }}
                                        <span
                                            v-if="applicant.is_masked"
                                            class="ml-1 px-1 py-0.5 rounded text-[9px] font-mono bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                        >MASKED</span>
                                    </p>
                                </div>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">
                                    {{ applicant.application?.program?.code || '—' }} ·
                                    <time>{{ applicant.application?.created_at ? new Date(applicant.application.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '—' }}</time>
                                </p>
                            </div>
                            <!-- Status badge -->
                            <span
                                class="shrink-0 stage-badge"
                                :class="{
                                    'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300': ['accepted','cleared_for_enrollment','officially_enrolled'].includes(applicant.application?.status),
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300': applicant.application?.status === 'submitted',
                                    'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300': applicant.application?.status === 'returned',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400': !['accepted','cleared_for_enrollment','officially_enrolled','submitted','returned'].includes(applicant.application?.status),
                                }"
                            >
                                {{ applicant.application?.status || '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800">
                        <Link href="/applications" class="text-xs font-medium text-maroon-light hover:underline dark:text-maroon-200">
                            Open applications →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ── Quick admin shortcuts ───────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">System Administration</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <Link
                        v-for="item in [
                            { label: 'Audit Logs',        href: '/admin/audit-logs',                       icon: 'logs'     },
                            { label: 'API Clients',       href: '/admin/api-clients',                      icon: 'api'      },
                            { label: 'Cutoff Settings',   href: '/admin/cutoff-settings',                  icon: 'cutoff'   },
                            { label: 'Reg. Overrides',    href: '/admin/score-overrides',                  icon: 'override' },
                            { label: 'IDP Sync Monitor',  href: '/admin/system-settings/idp-sync-monitor', icon: 'sync'     },
                        ]"
                        :key="item.href"
                        :href="item.href"
                        class="flex flex-col items-center gap-2 p-3.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 hover:border-maroon-100 hover:bg-maroon-50/40 dark:hover:border-maroon-800/40 dark:hover:bg-maroon-900/10 transition group text-center"
                    >
                        <!-- Icons by key -->
                        <div class="w-9 h-9 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 group-hover:border-maroon-light/30 group-hover:bg-maroon-50 dark:group-hover:bg-maroon-900/20 flex items-center justify-center transition shadow-sm">
                            <template v-if="item.icon === 'logs'">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                            </template>
                            <template v-else-if="item.icon === 'api'">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18"/>
                                </svg>
                            </template>
                            <template v-else-if="item.icon === 'cutoff'">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </template>
                            <template v-else-if="item.icon === 'override'">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </template>
                            <template v-else>
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            </template>
                        </div>
                        <span class="text-[11px] font-medium text-gray-600 dark:text-gray-400 group-hover:text-maroon-light dark:group-hover:text-maroon-200 transition leading-tight">{{ item.label }}</span>
                    </Link>
                </div>
            </div>

        </div><!-- /dash-shell-scroll -->
    </SuperAdminLayout>
</template>
