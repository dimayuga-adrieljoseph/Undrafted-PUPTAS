<!--
  Dashboard/Records.vue — PUPTAS Registrar / Record Staff Dashboard
  ──────────────────────────────────────────────────────────────────
  Serves Registrar (role 6) and Admin/SuperAdmin visiting the records route.
  baseUrl prop switches between /record-dashboard and /admin/records.

  All existing workflow interactions are preserved:
  - fetchUsers   async (GET  baseUrl/applicants)
  - fetchStats   async (GET  baseUrl/stats)
  - tag          (POST baseUrl/tag/{id})   → officially_enrolled
  - untag        (POST baseUrl/untag/{id}) → revert to temporary
  - return files (POST /record-dashboard/return-files/{user})
  - Auto-refresh every 10 s

  Visual style follows the completed Admin / SuperAdmin dashboard redesign.
  Stage accent: purple (#6D28D9) for records / enrollment
-->
<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Head } from '@inertiajs/vue3'
import RecordStaffLayout from '@/Layouts/RecordStaffLayout.vue'
import ImageViewer from '@/Components/ImageViewer.vue'
import UserDetailsModal from '@/Pages/Applications/UserDetailsModal.vue'
import { useMaskingState } from '@/Composables/useMaskingState'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    user:    { type: Object, default: null },
    // programs passed from RecordStaffDashboardController@index via DashboardService
    programs: { type: Array,  default: () => [] },
    summary:  { type: Object, default: () => ({ in_progress: 0, processed: 0 }) },
    // Admin uses /admin/records, Registrar uses /record-dashboard
    baseUrl: { type: String, default: '/record-dashboard' },
})

const { isUnmasked } = useMaskingState()

const isAdminOrSuperAdmin = computed(() => [2, 7].includes(props.user?.role_id))

// ── Async data (users + stats) ────────────────────────────────────────────────
const users    = ref([])
const programs = ref(props.programs || [])
const summary  = ref(props.summary  || { in_progress: 0, processed: 0 })
const isLoading = ref(true)
const loadError = ref('')

const fetchUsers = async () => {
    try {
        const sep    = props.baseUrl.includes('?') ? '&' : '?'
        const unmask = isUnmasked.value ? `${sep}unmask=1` : ''
        const res = await fetch(`${props.baseUrl}/applicants${unmask}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        if (!res.ok) throw new Error('Failed to fetch applicants')
        users.value = await res.json()
    } catch (e) {
        loadError.value = e.message
    } finally {
        isLoading.value = false
    }
}

const fetchStats = async () => {
    try {
        const res = await axios.get(`${props.baseUrl}/stats`)
        summary.value  = res.data.summary  || { in_progress: 0, processed: 0 }
        programs.value = res.data.programs || []
    } catch (e) {
        console.error('Failed to fetch stats:', e)
    }
}

// Auto-refresh every 10 s
let autoRefreshTimer = null
onMounted(async () => {
    await Promise.all([fetchUsers(), fetchStats()])
    autoRefreshTimer = setInterval(async () => {
        await Promise.all([fetchUsers(), fetchStats()])
        if (selectedUser.value) {
            const exists = users.value.some(u => String(u.id) === String(selectedUser.value.id))
            if (!exists) closeUserCard()
        }
    }, 10000)
})
onBeforeUnmount(() => { if (autoRefreshTimer) clearInterval(autoRefreshTimer) })

// ── Applicant queue ───────────────────────────────────────────────────────────
const searchQuery = ref('')
const currentPage = ref(1)
const PAGE_SIZE   = 8

// Records queue: show applicants cleared_for_enrollment or officially_enrolled
const queuedUsers = computed(() =>
    users.value.filter(u =>
        u.pipeline_status === 'for_records' ||
        u.pipeline_status === 'officially_enrolled' ||
        u.enrollment_status === 'officially_enrolled'
    )
)

const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return queuedUsers.value
    return queuedUsers.value.filter(u =>
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
const selectedUser      = ref(null)
const selectedUserFiles = ref({})

const selectUser = async (user) => {
    try {
        const res = await axios.get(`${props.baseUrl}/application/${user.id}`)
        selectedUser.value = {
            ...user,
            application: {
                ...res.data.user.application,
                processes: res.data.user.application?.processes || [],
                program:   res.data.user.application?.program   || null,
            },
        }
        selectedUserFiles.value = res.data.uploadedFiles || {}
    } catch (e) {
        console.error('Failed to load applicant:', e)
        showSnackbar('Failed to load applicant data.')
    }
}

const closeUserCard = () => { selectedUser.value = null; selectedUserFiles.value = {} }

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
const snackbar   = ref({ visible: false, message: '' })
let   snackTimer = null
const showSnackbar = (msg) => {
    if (snackTimer) clearTimeout(snackTimer)
    snackbar.value = { visible: true, message: msg }
    snackTimer = setTimeout(() => { snackbar.value.visible = false }, 3500)
}

// ── Enrollment actions ────────────────────────────────────────────────────────
const tagApplication = async () => {
    try {
        const id = selectedUser.value.id
        await axios.post(`${props.baseUrl}/tag/${id}`)
        showSnackbar('Tagged as officially enrolled.')
        users.value = users.value.filter(u => u.id !== id)
        selectedUser.value = null
        await Promise.all([fetchUsers(), fetchStats()])
    } catch (e) {
        showSnackbar(e.response?.data?.message || 'Failed to tag application.')
    }
}

const untagApplication = async () => {
    try {
        await axios.post(`${props.baseUrl}/untag/${selectedUser.value.id}`)
        showSnackbar('Reverted to temporary enrolled.')
        selectedUser.value = null
        await Promise.all([fetchUsers(), fetchStats()])
    } catch (e) {
        showSnackbar(e.response?.data?.message || 'Failed to untag application.')
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
const enrollmentBadgeClass = (enrollStatus) =>
    enrollStatus === 'officially_enrolled'
        ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300'
        : 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-300'

const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

// Total application count across programs
const totalApplicationsCount = computed(() =>
    programs.value.reduce((sum, p) => sum + (p.applications_count || 0), 0)
)
</script>

<template>
    <Head title="Registrar Dashboard" />
    <RecordStaffLayout>

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
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[70] px-5 py-3 rounded-xl shadow-lg bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-sm font-medium pointer-events-none"
            >{{ snackbar.message }}</div>
        </Transition>

        <div class="dash-shell-scroll space-y-6 pb-8">

            <!-- ── Page header ──────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <span class="w-3 h-3 rounded-full bg-purple-600" aria-hidden="true"></span>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Registrar Dashboard</h1>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Process enrollment for medically cleared applicants and manage program registrations.
                    </p>
                </div>
                <a
                    :href="baseUrl === '/admin/records' ? '/admin/records/applications' : '/recordstaff-applications'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-maroon-light text-white hover:bg-maroon transition shadow-sm self-start sm:self-auto"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    All Applications
                </a>
            </div>

            <!-- ── Summary metric cards ───────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3">
                <div class="metric-card border-l-4 border-l-purple-600 animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Awaiting Enrollment</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-purple-700 dark:text-purple-400">
                        {{ (summary?.in_progress ?? 0).toLocaleString() }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Cleared, pending registration</p>
                </div>
                <div class="metric-card border-l-4 border-l-green-600 animate-slide-up">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Enrolled</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-green-700 dark:text-green-400">
                        {{ (summary?.processed ?? 0).toLocaleString() }}
                    </p>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Officially enrolled</p>
                </div>
            </div>

            <!-- ── Main grid: queue + programs ──────────────────────── -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- Enrollment queue (2/3) -->
                <div class="xl:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card flex flex-col">
                    <div class="px-5 pt-5 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Enrollment Queue</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                <span v-if="isLoading">Loading…</span>
                                <span v-else>{{ filteredUsers.length }} applicant{{ filteredUsers.length !== 1 ? 's' : '' }} — click to process enrollment</span>
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

                    <!-- Loading skeleton -->
                    <div v-if="isLoading" class="px-5 py-8 space-y-3">
                        <div v-for="n in 4" :key="n" class="h-12 bg-gray-100 dark:bg-gray-800 rounded-lg animate-pulse"></div>
                    </div>

                    <!-- Error state -->
                    <div v-else-if="loadError" class="px-5 py-8 text-center">
                        <p class="text-sm text-red-600 dark:text-red-400">{{ loadError }}</p>
                        <button @click="fetchUsers" class="mt-3 text-xs text-maroon-light underline hover:no-underline">Try again</button>
                    </div>

                    <!-- Table -->
                    <div v-else class="flex-1 overflow-x-auto">
                        <table class="w-full text-sm" aria-label="Enrollment queue">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/30">
                                    <th class="text-left px-5 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Applicant</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden sm:table-cell">Program</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide hidden md:table-cell">Date</th>
                                    <th class="text-left px-3 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Enrollment</th>
                                    <th class="px-5 py-2.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template v-if="displayedUsers.length === 0">
                                    <tr>
                                        <td colspan="5" class="px-5 py-12 text-center">
                                            <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                                {{ searchQuery ? 'No results found' : 'No applicants awaiting enrollment' }}
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
                                            <div class="w-7 h-7 rounded-full bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-300 flex items-center justify-center text-[11px] font-semibold shrink-0" aria-hidden="true">
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
                                        <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">{{ u.program?.code || u.application?.program?.code || '—' }}</span>
                                    </td>
                                    <td class="px-3 py-3 hidden md:table-cell">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(u.application?.created_at || u.created_at) }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="stage-badge" :class="enrollmentBadgeClass(u.enrollment_status)">
                                            {{ u.enrollment_status === 'officially_enrolled' ? 'Enrolled' : 'Pending' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <button
                                            @click.stop="selectUser(u)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg text-purple-700 border border-purple-300/50 bg-purple-50/60 hover:bg-purple-50 dark:text-purple-300 dark:border-purple-700/40 dark:bg-purple-900/10 dark:hover:bg-purple-900/20 transition"
                                            :aria-label="`Process enrollment for ${u.firstname} ${u.lastname}`"
                                        >
                                            Process
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="!isLoading && !loadError" class="px-5 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between shrink-0">
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

                <!-- Programs overview (1/3) -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-card shadow-card p-5 flex flex-col">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Programs Overview</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Applications per program</p>
                        </div>
                        <a
                            :href="baseUrl === '/admin/records' ? '/admin/records/programs-page' : '/record-programs'"
                            class="text-xs font-medium text-maroon-light hover:text-maroon dark:text-maroon-200 transition"
                        >View all →</a>
                    </div>

                    <!-- Program list -->
                    <div class="flex-1 overflow-y-auto space-y-2 min-h-0">
                        <div v-if="programs.length === 0" class="text-xs text-gray-400 dark:text-gray-500 py-8 text-center">No program data available</div>
                        <div
                            v-for="p in programs"
                            :key="p.id"
                            class="flex items-center gap-3 py-2 border-b border-gray-100 dark:border-gray-800 last:border-0"
                        >
                            <!-- Code badge -->
                            <div class="w-9 h-9 rounded-lg bg-maroon-50 text-maroon-light dark:bg-maroon-900/20 dark:text-maroon-200 flex items-center justify-center text-[10px] font-bold shrink-0">
                                {{ p.code?.charAt(0) || '?' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">{{ p.code }}</p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate">{{ p.name }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold tabular-nums text-maroon-light dark:text-maroon-200">{{ p.enrolled_count ?? p.applications_count ?? 0 }}</p>
                                <p class="text-[9px] text-gray-400 dark:text-gray-500">{{ p.enrolled_count != null ? 'enrolled' : 'applied' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 grid grid-cols-2 gap-3">
                        <div class="p-2.5 bg-gray-50 dark:bg-gray-800/40 rounded-lg">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400">Programs</p>
                            <p class="text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ programs.length }}</p>
                        </div>
                        <div class="p-2.5 bg-gray-50 dark:bg-gray-800/40 rounded-lg">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400">Enrolled</p>
                            <p class="text-lg font-bold tabular-nums text-green-700 dark:text-green-400">{{ summary?.processed ?? 0 }}</p>
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
                            { label: 'All Applications', href: baseUrl === '/admin/records' ? '/admin/records/applications' : '/recordstaff-applications', desc: 'Full application list' },
                            { label: 'Programs',         href: baseUrl === '/admin/records' ? '/admin/records/programs-page' : '/record-programs',           desc: 'Program slot overview' },
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
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Enrollment Actions</h4>

                    <!-- Current enrollment status -->
                    <div class="flex items-center justify-between px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <span class="text-xs text-gray-600 dark:text-gray-400">Status</span>
                        <span class="stage-badge" :class="enrollmentBadgeClass(selectedUser?.application?.enrollment_status)">
                            {{ selectedUser?.application?.enrollment_status === 'officially_enrolled' ? 'Officially Enrolled' : 'Pending' }}
                        </span>
                    </div>

                    <!-- Tag as enrolled -->
                    <button
                        v-if="selectedUser?.application?.enrollment_status !== 'officially_enrolled'"
                        @click="tagApplication"
                        class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-xs min-h-[40px] flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Tag as Officially Enrolled
                    </button>

                    <!-- Untag / revert -->
                    <button
                        @click="untagApplication"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition font-medium text-xs min-h-[40px] flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        Untag / Revert to Temporary
                    </button>
                </div>
            </template>
        </UserDetailsModal>

        <!-- ── ImageViewer ───────────────────────────────────────────── -->
        <ImageViewer :show="showImageModal" :src="previewImage" :alt="previewAlt" @close="closeImageModal"/>

    </RecordStaffLayout>
</template>
