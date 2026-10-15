<!--
  SuperAdmin/Logs.vue — PUPTAS System Logs / Audit Trail
  ───────────────────────────────────────────────────────
  Redesigned to match the Admin/SuperAdmin dashboard visual language.
  Preserves all existing functionality:
    - Server-side filters (log_type, user_id, date, search)
    - Client-side action_type filter (operates on current page)
    - 5-second auto-polling for new events (Inertia router.reload)
    - New-log highlight animation
    - AuditLogDetailsModal (view individual event)
    - AuditLogAiModal (DeepSeek AI analysis)
    - Paginator with page-jump input
    - PII masking pass-through

  Design system tokens used:
    - metric-card, stage-badge, rounded-card, shadow-card (from app.css / tailwind.config)
    - dash-shell-scroll for natural vertical scroll
    - maroon-light (#9E122C) for primary actions
    - Semantic action badge colors
-->
<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import PageContainer from '@/Components/PageContainer.vue'
import AuditLogDetailsModal from '@/Pages/Modal/AuditLogDetailsModal.vue'
import AuditLogAiModal from '@/Pages/Modal/AuditLogAiModal.vue'

// ── Page props ────────────────────────────────────────────────────────────────
const page       = usePage()
const logs       = computed(() => page.props.logs?.data || [])
const pagination = computed(() => page.props.logs || {})
const users      = computed(() => page.props.users || [])
const logTypes   = computed(() => page.props.logTypes || ['SYSTEM', 'AUDIT', 'SECURITY'])
const initialFilters = computed(() => page.props.filters || {})
const logCounts  = computed(() => page.props.logCounts || { SYSTEM: 0, AUDIT: 0, SECURITY: 0 })
const metrics    = computed(() => page.props.metrics || { total: 0, today: 0, create: 0, update: 0, delete: 0 })

// ── Modals ────────────────────────────────────────────────────────────────────
const selectedLog  = ref(null)
const showModal    = ref(false)
const showAiModal  = ref(false)

const viewDetails = (log) => { selectedLog.value = log; showModal.value = true }
const closeModal  = () => { showModal.value = false; selectedLog.value = null }

// ── Filters ───────────────────────────────────────────────────────────────────
const filterAction = ref('')  // client-side, on current page only
const serverFilters = ref({
    user_id:  initialFilters.value.user_id  ?? '',
    date:     initialFilters.value.date     ?? '',
    log_type: initialFilters.value.log_type ?? '',
    search:   initialFilters.value.search   ?? '',
})

const buildServerParams = () => {
    const p = {}
    if (serverFilters.value.user_id)  p.user_id  = serverFilters.value.user_id
    if (serverFilters.value.date)     p.date     = serverFilters.value.date
    if (serverFilters.value.log_type) p.log_type = serverFilters.value.log_type
    if (serverFilters.value.search)   p.search   = serverFilters.value.search
    return p
}

const applyServerFilters = () => {
    router.get(route('audit-logs.index'), buildServerParams(), {
        preserveState: true, preserveScroll: true, replace: true,
        onSuccess: () => { newLogIds.value.clear(); initLastKnownId() },
    })
}

const clearServerFilters = () => {
    serverFilters.value = { user_id: '', date: '', log_type: '', search: '' }
    filterAction.value  = ''
    applyServerFilters()
}

const hasActiveFilters = computed(() =>
    serverFilters.value.user_id  || serverFilters.value.date ||
    serverFilters.value.log_type || serverFilters.value.search || filterAction.value
)

// ── Client-side action filter (current page only) ─────────────────────────────
const filteredLogs = computed(() => {
    if (!filterAction.value) return logs.value
    return logs.value.filter(l => l.action_type === filterAction.value)
})

const actionTypes = computed(() => {
    const s = new Set()
    logs.value.forEach(l => { if (l.action_type) s.add(l.action_type) })
    return Array.from(s).sort()
})

// ── Auto-polling (5 s) ────────────────────────────────────────────────────────
const pollIntervalId  = ref(null)
const lastKnownId     = ref(0)
const newLogIds       = ref(new Set())
const isReloading     = ref(false)
const POLL_INTERVAL   = 5000

const initLastKnownId = () => {
    const ids = logs.value.map(l => l.id)
    lastKnownId.value = ids.length ? Math.max(...ids) : 0
}

const pollForNewLogs = () => {
    if (isReloading.value) return
    const prev = lastKnownId.value
    isReloading.value = true
    router.reload({
        only: ['logs'],
        data: buildServerParams(),
        preserveState: true, preserveScroll: true, replace: true,
        onSuccess: () => {
            const incoming = logs.value.filter(l => l.id > prev).map(l => l.id)
            incoming.forEach(id => newLogIds.value.add(id))
            initLastKnownId()
            if (incoming.length) setTimeout(() => newLogIds.value.clear(), 5000)
        },
        onFinish: () => { isReloading.value = false },
    })
}

onMounted(() => {
    initLastKnownId()
    pollIntervalId.value = setInterval(pollForNewLogs, POLL_INTERVAL)
})
onUnmounted(() => { if (pollIntervalId.value) clearInterval(pollIntervalId.value) })
watch(logs, initLastKnownId, { deep: false })

const isNewLog = (id) => newLogIds.value.has(id)

// ── Pagination ────────────────────────────────────────────────────────────────
const getPageUrl = (pageNum) => {
    const p = new URLSearchParams({ page: String(pageNum), ...buildServerParams() })
    return (pagination.value.path || '') + '?' + p.toString()
}

// ── Formatters ────────────────────────────────────────────────────────────────
const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    })
}
const formatDateShort = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleString('en-US', {
        month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true,
    })
}

// ── Action badge ──────────────────────────────────────────────────────────────
// Maps actual AuditLog::ACTION_* constants to PUPTAS semantic colors
const actionBadgeClass = (action) => {
    const map = {
        CREATE:   'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300',
        UPDATE:   'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300',
        DELETE:   'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300',
        LOGIN:    'bg-sky-50 text-sky-700 dark:bg-sky-900/20 dark:text-sky-300',
        LOGOUT:   'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        READ:     'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300',
        DOWNLOAD: 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-300',
    }
    return map[action] || 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
}

// ── Log-type badge ─────────────────────────────────────────────────────────────
const logTypeBadgeClass = (type) => {
    const map = {
        SYSTEM:   'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300',
        AUDIT:    'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300',
        SECURITY: 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300',
    }
    return map[type] || 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
}

// ── Metric card definitions ───────────────────────────────────────────────────
const metricCards = computed(() => [
    {
        id:     'total',
        label:  'Total Events',
        value:  metrics.value.total,
        accent: 'maroon',
        desc:   'All recorded audit events',
    },
    {
        id:     'today',
        label:  "Today's Events",
        value:  metrics.value.today,
        accent: 'sky',
        desc:   'Events recorded today',
    },
    {
        id:     'create',
        label:  'Create Actions',
        value:  metrics.value.create,
        accent: 'green',
        desc:   'Record creation events',
    },
    {
        id:     'update',
        label:  'Update Actions',
        value:  metrics.value.update,
        accent: 'amber',
        desc:   'Record modification events',
    },
    {
        id:     'delete',
        label:  'Delete Actions',
        value:  metrics.value.delete,
        accent: 'red',
        desc:   'Record deletion events',
    },
])

const metricAccentClass = (accent) => {
    const map = {
        maroon: 'border-l-maroon-light',
        sky:    'border-l-sky-600',
        green:  'border-l-green-600',
        amber:  'border-l-amber-500',
        red:    'border-l-red-600',
    }
    return map[accent] || 'border-l-gray-400'
}

const metricValueClass = (accent) => {
    const map = {
        maroon: 'text-maroon-light dark:text-maroon-200',
        sky:    'text-sky-700 dark:text-sky-400',
        green:  'text-green-700 dark:text-green-400',
        amber:  'text-amber-700 dark:text-amber-400',
        red:    'text-red-700 dark:text-red-400',
    }
    return map[accent] || 'text-gray-900 dark:text-white'
}
</script>

<template>
    <Head title="System Logs" />
    <SuperAdminLayout>
        <template #title>
            <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">System Logs</span>
        </template>

        <PageContainer>
        <div class="dash-shell-scroll space-y-6 pb-8">

            <!-- ── Page header ──────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <span class="w-3 h-3 rounded-full bg-maroon-light" aria-hidden="true"></span>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                            System Logs
                        </h1>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Monitor system activity and review recorded administrative and application events.
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Live indicator -->
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-700/40">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse" aria-hidden="true"></span>
                        Live — updates every 5 s
                    </span>
                    <!-- AI Analytics -->
                    <button
                        @click="showAiModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-maroon-light text-white hover:bg-maroon transition shadow-sm"
                        aria-label="Open AI Analytics"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        AI Analytics
                    </button>
                </div>
            </div>

            <!-- ── Summary metric cards ──────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div
                    v-for="card in metricCards"
                    :key="card.id"
                    class="metric-card border-l-4 animate-slide-up"
                    :class="metricAccentClass(card.accent)"
                >
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums" :class="metricValueClass(card.accent)">
                        {{ card.value.toLocaleString() }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500 leading-tight">{{ card.desc }}</p>
                </div>
            </div>

            <!-- ── Log-type counts row ────────────────────────────────── -->
            <div class="grid grid-cols-3 gap-3">
                <button
                    v-for="type in logTypes"
                    :key="type"
                    @click="serverFilters.log_type = serverFilters.log_type === type ? '' : type; applyServerFilters()"
                    class="bg-white dark:bg-gray-900 border rounded-card shadow-card px-4 py-3 flex items-center justify-between transition hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none focus:ring-2 focus:ring-maroon-light"
                    :class="serverFilters.log_type === type
                        ? 'border-maroon-light/60 bg-maroon-50/30 dark:bg-maroon-900/10 dark:border-maroon-700/40'
                        : 'border-gray-200 dark:border-gray-800'"
                    :aria-pressed="serverFilters.log_type === type"
                    :aria-label="`Filter by ${type} logs — ${logCounts[type] ?? 0} events`"
                >
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ type }}</p>
                        <p class="text-xl font-bold tabular-nums text-gray-900 dark:text-white mt-0.5">
                            {{ (logCounts[type] ?? 0).toLocaleString() }}
                        </p>
                    </div>
                    <span class="stage-badge" :class="logTypeBadgeClass(type)">{{ type }}</span>
                </button>
            </div>

            <!-- ── Filter toolbar ────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <!-- Log type -->
                    <div class="flex flex-col gap-1 min-w-[130px]">
                        <label for="filter-log-type" class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Log Type</label>
                        <select
                            id="filter-log-type"
                            v-model="serverFilters.log_type"
                            class="text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-maroon-light focus:ring-maroon-light py-1.5 pl-2.5 pr-7"
                        >
                            <option value="">All Types</option>
                            <option v-for="t in logTypes" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>

                    <!-- Action type (client-side) -->
                    <div class="flex flex-col gap-1 min-w-[140px]">
                        <label for="filter-action" class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Action</label>
                        <select
                            id="filter-action"
                            v-model="filterAction"
                            class="text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-maroon-light focus:ring-maroon-light py-1.5 pl-2.5 pr-7"
                        >
                            <option value="">All Actions</option>
                            <option v-for="a in actionTypes" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>

                    <!-- User -->
                    <div class="flex flex-col gap-1 min-w-[180px] flex-1">
                        <label for="filter-user" class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">User</label>
                        <select
                            id="filter-user"
                            v-model="serverFilters.user_id"
                            class="text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-maroon-light focus:ring-maroon-light py-1.5 pl-2.5 pr-7"
                        >
                            <option value="">All Users</option>
                            <option v-for="u in users" :key="u.id" :value="String(u.id)">
                                {{ u.id === 'system' ? 'System / API' : `${u.firstname} ${u.lastname}`.trim() }}
                                {{ u.id !== 'system' && u.email ? `(${u.email})` : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Date -->
                    <div class="flex flex-col gap-1">
                        <label for="filter-date" class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Date</label>
                        <input
                            id="filter-date"
                            v-model="serverFilters.date"
                            type="date"
                            class="text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-maroon-light focus:ring-maroon-light py-1.5 px-2.5"
                        />
                    </div>

                    <!-- Search -->
                    <div class="flex flex-col gap-1 flex-1 min-w-[200px]">
                        <label for="filter-search" class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Search</label>
                        <div class="relative">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                id="filter-search"
                                v-model="serverFilters.search"
                                @keyup.enter="applyServerFilters"
                                type="search"
                                placeholder="Email, description, module…"
                                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white placeholder-gray-400 focus:border-maroon-light focus:ring-maroon-light focus:outline-none transition"
                                aria-label="Search audit logs"
                            />
                        </div>
                    </div>

                    <!-- Apply / Clear -->
                    <div class="flex items-end gap-2 shrink-0">
                        <button
                            @click="applyServerFilters"
                            class="px-3 py-1.5 rounded-lg bg-maroon-light text-white text-xs font-semibold hover:bg-maroon transition min-h-[34px]"
                            aria-label="Apply filters"
                        >Apply</button>
                        <button
                            v-if="hasActiveFilters"
                            @click="clearServerFilters"
                            class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-800 transition min-h-[34px]"
                            aria-label="Clear all filters"
                        >Clear</button>
                    </div>
                </div>
            </div>

            <!-- ── Log table ─────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card overflow-hidden">

                <!-- Table header -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" aria-label="Audit log events">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-800/40">
                                <th scope="col" class="text-left px-4 py-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide whitespace-nowrap">Timestamp</th>
                                <th scope="col" class="text-left px-3 py-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">User</th>
                                <th scope="col" class="text-left px-3 py-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden sm:table-cell">Role</th>
                                <th scope="col" class="text-left px-3 py-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Type</th>
                                <th scope="col" class="text-left px-3 py-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden md:table-cell">Module</th>
                                <th scope="col" class="text-left px-3 py-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Action</th>
                                <th scope="col" class="text-left px-4 py-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden lg:table-cell">Description</th>
                                <th scope="col" class="px-4 py-3 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                            <!-- Empty state -->
                            <tr v-if="filteredLogs.length === 0">
                                <td colspan="8" class="px-4 py-16 text-center">
                                    <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No audit events found</p>
                                    <p v-if="hasActiveFilters" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        Try adjusting your filters or
                                        <button @click="clearServerFilters" class="text-maroon-light underline hover:no-underline">clear them</button>
                                    </p>
                                </td>
                            </tr>

                            <!-- Log rows -->
                            <tr
                                v-for="log in filteredLogs"
                                :key="log.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition group"
                                :class="isNewLog(log.id) ? 'log-new-highlight' : ''"
                            >
                                <!-- Timestamp -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <time
                                        :datetime="log.created_at"
                                        class="text-xs text-gray-600 dark:text-gray-400 font-mono"
                                        :title="formatDate(log.created_at)"
                                    >{{ formatDateShort(log.created_at) }}</time>
                                </td>

                                <!-- User -->
                                <td class="px-3 py-3 max-w-[140px]">
                                    <p class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate" :title="log.username || log.user_id || '—'">
                                        {{ log.username || log.user_id || '—' }}
                                    </p>
                                    <p v-if="log.ip_address" class="text-[10px] font-mono text-gray-400 dark:text-gray-500 truncate">{{ log.ip_address }}</p>
                                </td>

                                <!-- Role -->
                                <td class="px-3 py-3 hidden sm:table-cell">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 truncate" :title="log.user_role">
                                        {{ log.user_role || '—' }}
                                    </span>
                                </td>

                                <!-- Log Type -->
                                <td class="px-3 py-3">
                                    <span class="stage-badge" :class="logTypeBadgeClass(log.log_type)">
                                        {{ log.log_type || '—' }}
                                    </span>
                                </td>

                                <!-- Module -->
                                <td class="px-3 py-3 hidden md:table-cell max-w-[110px]">
                                    <span class="text-xs text-gray-600 dark:text-gray-400 truncate block" :title="log.module_name">
                                        {{ log.module_name || '—' }}
                                    </span>
                                </td>

                                <!-- Action -->
                                <td class="px-3 py-3">
                                    <span class="stage-badge" :class="actionBadgeClass(log.action_type)">
                                        {{ log.action_type || '—' }}
                                    </span>
                                </td>

                                <!-- Description -->
                                <td class="px-4 py-3 hidden lg:table-cell max-w-[260px]">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate" :title="log.description">
                                        {{ log.description || '—' }}
                                    </p>
                                </td>

                                <!-- Details button -->
                                <td class="px-4 py-3 text-right">
                                    <button
                                        @click="viewDetails(log)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg text-maroon-light border border-maroon-light/30 bg-maroon-50/60 hover:bg-maroon-50 dark:text-maroon-200 dark:border-maroon-700/40 dark:bg-maroon-900/10 dark:hover:bg-maroon-900/20 transition opacity-0 group-hover:opacity-100 focus:opacity-100"
                                        :aria-label="`View details for log #${log.id}`"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </button>
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="pagination.last_page > 1"
                    class="px-4 py-3 border-t border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-3"
                >
                    <p class="text-xs text-gray-500 dark:text-gray-400 shrink-0">
                        <template v-if="pagination.total">
                            Showing {{ pagination.from || 0 }}–{{ pagination.to || 0 }} of {{ pagination.total.toLocaleString() }} events
                        </template>
                        <template v-else>No results</template>
                    </p>
                    <div class="flex items-center gap-2">
                        <!-- Previous -->
                        <button
                            :disabled="pagination.current_page === 1"
                            @click.prevent="router.visit(getPageUrl(pagination.current_page - 1))"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            aria-label="Previous page"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            Prev
                        </button>

                        <!-- Page input -->
                        <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                            <span>Page</span>
                            <input
                                type="number"
                                :value="pagination.current_page"
                                min="1"
                                :max="pagination.last_page || 1"
                                @change="router.visit(getPageUrl(Math.max(1, Math.min(+$event.target.value, pagination.last_page || 1))))"
                                class="w-14 px-2 py-1 text-center text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-1 focus:ring-maroon-light focus:border-maroon-light"
                                :aria-label="`Current page, ${pagination.current_page} of ${pagination.last_page}`"
                            />
                            <span>of <strong>{{ pagination.last_page || 1 }}</strong></span>
                        </div>

                        <!-- Next -->
                        <button
                            :disabled="pagination.current_page === pagination.last_page"
                            @click.prevent="router.visit(getPageUrl(pagination.current_page + 1))"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            aria-label="Next page"
                        >
                            Next
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Single-page count line (no nav needed) -->
                <div
                    v-else-if="pagination.total"
                    class="px-4 py-3 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400"
                >
                    {{ filteredLogs.length }} event{{ filteredLogs.length !== 1 ? 's' : '' }} on this page
                </div>
            </div>

        </div><!-- /dash-shell-scroll -->
        </PageContainer>

        <!-- ── Details modal ─────────────────────────────────────────── -->
        <AuditLogDetailsModal
            :show="showModal"
            :log="selectedLog"
            @close="closeModal"
        />

        <!-- ── AI Analytics modal ────────────────────────────────────── -->
        <AuditLogAiModal
            :show="showAiModal"
            @close="showAiModal = false"
        />

    </SuperAdminLayout>
</template>

<style scoped>
/* New-log highlight — fades from green tint to transparent */
@keyframes log-new {
    0%   { background-color: rgba(21, 128, 61, 0.12); }
    60%  { background-color: rgba(21, 128, 61, 0.06); }
    100% { background-color: transparent; }
}

.log-new-highlight {
    animation: log-new 5s ease-out forwards;
}

@media (prefers-reduced-motion: reduce) {
    .log-new-highlight { animation: none; }
}
</style>
