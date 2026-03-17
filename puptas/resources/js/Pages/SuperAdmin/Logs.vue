<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import SuperAdminLayout from "@/Layouts/SuperAdminLayout.vue";
import AuditLogDetailsModal from "@/Pages/Modal/AuditLogDetailsModal.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
import axios from "axios";
import {
    faHistory,
    faEye,
    faTimes,
    faSearch,
    faFilter,
    faChevronLeft,
    faChevronRight,
    faInfoCircle,
    faNetworkWired,
    faUser,
    faClock,
    faGlobe,
    faCode,
    faServer,
    faShieldAlt,
} from "@fortawesome/free-solid-svg-icons";

library.add(
    faHistory,
    faEye,
    faTimes,
    faSearch,
    faFilter,
    faChevronLeft,
    faChevronRight,
    faInfoCircle,
    faNetworkWired,
    faUser,
    faClock,
    faGlobe,
    faCode,
    faServer,
    faShieldAlt
);

const page = usePage();
const logs = computed(() => page.props.logs?.data || []);
const pagination = computed(() => page.props.logs || {});
const users = computed(() => page.props.users || []);
const logTypes = computed(() => page.props.logTypes || ["SYSTEM", "AUDIT", "SECURITY"]);
const initialFilters = computed(() => page.props.filters || {});

// State
const selectedLog = ref(null);
const showModal = ref(false);
const searchQuery = ref("");
const filterAction = ref("");
const serverFilters = ref({
    user_id: initialFilters.value.user_id ?? "",
    date: initialFilters.value.date ?? "",
    log_type: initialFilters.value.log_type ?? "",
});

// Auto-polling state
const pollIntervalId = ref(null);
const lastKnownId = ref(0);
const newLogIds = ref(new Set());
const liveTotal = ref(0);
const isReloadingLogs = ref(false);
const POLL_INTERVAL = 5000; // 5 seconds

// Initialize lastKnownId from current logs
const initLastKnownId = () => {
    const currentLogs = logs.value;
    if (currentLogs.length > 0) {
        lastKnownId.value = Math.max(...currentLogs.map(l => l.id));
    } else {
        lastKnownId.value = 0;
    }
    liveTotal.value = pagination.value.total || 0;
};

const buildServerParams = () => {
    const params = {};

    if (serverFilters.value.user_id) params.user_id = serverFilters.value.user_id;
    if (serverFilters.value.date) params.date = serverFilters.value.date;
    if (serverFilters.value.log_type) params.log_type = serverFilters.value.log_type;

    return params;
};

// Poll for new logs
const pollForNewLogs = async () => {
    try {
        const response = await axios.get('/admin/audit-logs/check-new', {
            params: {
                since_id: lastKnownId.value,
                ...buildServerParams(),
            }
        });

        const { latest_id, total, new_log_ids } = response.data;
        const latestId = Number(latest_id) || 0;
        const hasNewLogs = latestId > lastKnownId.value;

        if (hasNewLogs && !isReloadingLogs.value) {
            // Track new log IDs for highlighting when the API provides them.
            if (Array.isArray(new_log_ids) && new_log_ids.length > 0) {
                new_log_ids.forEach((id) => newLogIds.value.add(id));
            }

            // Update total count
            liveTotal.value = total;

            // Update last known ID
            lastKnownId.value = latestId;
            isReloadingLogs.value = true;

            // Reload the Inertia page data to reflect new logs
            router.reload({
                only: ['logs'],
                data: buildServerParams(),
                preserveState: false,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    // Clear highlight after 5 seconds
                    setTimeout(() => {
                        newLogIds.value.clear();
                    }, 5000);
                },
                onFinish: () => {
                    isReloadingLogs.value = false;
                },
            });
        }
    } catch (error) {
        // Silently ignore polling errors to avoid disrupting the UI
        isReloadingLogs.value = false;
    }
};

// Start/stop polling
const startPolling = () => {
    if (pollIntervalId.value) return;
    pollIntervalId.value = setInterval(pollForNewLogs, POLL_INTERVAL);
};

const stopPolling = () => {
    if (pollIntervalId.value) {
        clearInterval(pollIntervalId.value);
        pollIntervalId.value = null;
    }
};

const isNewLog = (logId) => {
    return newLogIds.value.has(logId);
};

// Lifecycle
onMounted(() => {
    initLastKnownId();
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});

watch(
    logs,
    () => {
        initLastKnownId();
    },
    { deep: false }
);

// Computed
const filteredLogs = computed(() => {
    let result = logs.value;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (log) =>
                log.description?.toLowerCase().includes(query) ||
                log.module_name?.toLowerCase().includes(query) ||
                log.username?.toLowerCase().includes(query) ||
                log.user_role?.toLowerCase().includes(query)
        );
    }

    if (filterAction.value) {
        result = result.filter((log) => log.action_type === filterAction.value);
    }

    return result;
});

const actionTypes = computed(() => {
    const types = new Set();
    logs.value.forEach((log) => {
        if (log.action_type) types.add(log.action_type);
    });
    return Array.from(types);
});

const totalByType = (type) => logs.value.filter((log) => log.log_type === type).length;

const applyServerFilters = () => {
    router.get(route("audit-logs.index"), buildServerParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            newLogIds.value.clear();
            initLastKnownId();
        },
    });
};

const clearServerFilters = () => {
    serverFilters.value = {
        user_id: "",
        date: "",
        log_type: "",
    };

    applyServerFilters();
};

// Methods
const viewDetails = async (log) => {
    selectedLog.value = log;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedLog.value = null;
};

const formatDate = (date) => {
    if (!date) return "N/A";
    return new Date(date).toLocaleString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    });
};

const getActionBadgeClass = (action) => {
    const classes = {
        LOGIN: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300",
        LOGOUT: "bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300",
        CREATE: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
        UPDATE: "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300",
        DELETE: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300",
    };
    return classes[action] || "bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300";
};

const getLogTypeBadgeClass = (type) => {
    const classes = {
        SYSTEM: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
        AUDIT: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300",
        SECURITY: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300",
    };

    return classes[type] || "bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300";
};

const getPageUrl = (pageNum) => {
    const params = new URLSearchParams({ page: pageNum.toString(), ...buildServerParams() });
    return pagination.value.path + "?" + params.toString();
};
</script>

<template>
    <SuperAdminLayout title="Audit Logs">
        <div class="px-4 md:px-8 py-8 w-full">
            <!-- Header Section -->
            <div class="mb-8">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-xl">
                        <FontAwesomeIcon icon="history" class="h-6 w-6 text-purple-600 dark:text-purple-300" />
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">System Logs</h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">Monitor System, Audit, and Security events</p>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Total Logs</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ liveTotal || pagination.total || 0 }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-300">
                            <FontAwesomeIcon icon="history" class="w-6 h-6" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">System Logs</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalByType('SYSTEM') }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                            <FontAwesomeIcon icon="server" class="w-6 h-6" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Audit Logs</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalByType('AUDIT') }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-300">
                            <FontAwesomeIcon icon="code" class="w-6 h-6" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Security Logs</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalByType('SECURITY') }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300">
                            <FontAwesomeIcon icon="shield-alt" class="w-6 h-6" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Log Type Filter (server) -->
                        <div class="w-full lg:w-48">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Log Type</label>
                            <div class="relative">
                                <select
                                    v-model="serverFilters.log_type"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-600 focus:border-transparent appearance-none"
                                >
                                    <option value="">All Types</option>
                                    <option v-for="type in logTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                                <FontAwesomeIcon icon="filter" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" />
                            </div>
                        </div>

                        <!-- User Filter (server) -->
                        <div class="w-full lg:w-64">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User</label>
                            <select
                                v-model="serverFilters.user_id"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                            >
                                <option value="">All Users</option>
                                <option v-for="u in users" :key="u.id" :value="String(u.id)">
                                    {{ `${u.firstname} ${u.lastname}`.trim() }} ({{ u.email }})
                                </option>
                            </select>
                        </div>

                        <!-- Date Filter (server) -->
                        <div class="w-full lg:w-52">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date</label>
                            <input
                                v-model="serverFilters.date"
                                type="date"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                            />
                        </div>

                        <!-- Search -->
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Logs</label>
                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search by description, module, user..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                                />
                                <FontAwesomeIcon icon="search" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" />
                            </div>
                        </div>

                        <!-- Action Filter -->
                        <div class="w-full lg:w-48">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Action Type</label>
                            <div class="relative">
                                <select
                                    v-model="filterAction"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-600 focus:border-transparent appearance-none"
                                >
                                    <option value="">All Actions</option>
                                    <option v-for="type in actionTypes" :key="type" :value="type">
                                        {{ type }}
                                    </option>
                                </select>
                                <FontAwesomeIcon icon="filter" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" />
                            </div>
                        </div>

                        <div class="w-full lg:w-auto flex items-end gap-2">
                            <button
                                type="button"
                                @click="applyServerFilters"
                                class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition text-sm font-medium"
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                @click="clearServerFilters"
                                class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition text-sm font-medium"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Logs Table Container with Scroll -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
    <!-- Table Header - Fixed -->
    <div class="grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
        <div class="col-span-2">Timestamp</div>
        <div class="col-span-1">User</div>
        <div class="col-span-1">Role</div>
        <div class="col-span-1">Type</div>
        <div class="col-span-1">Module</div>
        <div class="col-span-1">Action</div>
        <div class="col-span-4">Description</div>
        <div class="col-span-1 text-right">Actions</div>
    </div>

    <!-- Scrollable Table Body -->
        <div class="max-h-[400px] overflow-y-auto simple-scrollbar">
            <div v-if="filteredLogs.length === 0" class="text-center py-12">
                <FontAwesomeIcon icon="history" class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                <p class="text-gray-500 dark:text-gray-400 text-sm">No audit logs found</p>
            </div>

            <div v-for="log in filteredLogs" :key="log.id" 
                :class="[
                    'grid grid-cols-12 gap-4 px-6 py-3 transition border-b border-gray-100 dark:border-gray-700 last:border-0',
                    isNewLog(log.id)
                        ? 'bg-green-50/70 dark:bg-green-900/10 animate-highlight-fade'
                        : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
                ]">
                <div class="col-span-2 text-gray-600 dark:text-gray-300 text-sm">
                    {{ formatDate(log.created_at) }}
                </div>
                <div class="col-span-1">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-900 dark:text-white text-sm truncate" :title="log.username || log.user_id">
                            {{ log.username || log.user_id || "N/A" }}
                        </span>
                    </div>
                </div>
                <div class="col-span-1 text-gray-600 dark:text-gray-300 text-sm truncate" :title="log.user_role">
                    {{ log.user_role || "N/A" }}
                </div>
                <div class="col-span-1">
                    <span
                        :class="[
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                            getLogTypeBadgeClass(log.log_type),
                        ]"
                    >
                        {{ log.log_type || "N/A" }}
                    </span>
                </div>
                <div class="col-span-1 text-gray-900 dark:text-white text-sm truncate" :title="log.module_name">
                    {{ log.module_name || "N/A" }}
                </div>
                <div class="col-span-1">
                    <span
                        :class="[
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                            getActionBadgeClass(log.action_type),
                        ]"
                    >
                        {{ log.action_type || "N/A" }}
                    </span>
                </div>
                <div class="col-span-4 text-gray-500 dark:text-gray-400 text-sm truncate" :title="log.description">
                    {{ log.description || "N/A" }}
                </div>
                <div class="col-span-1 text-right">
                    <button
                        @click="viewDetails(log)"
                        class="text-gray-400 hover:text-purple-600 dark:hover:text-purple-300 transition"
                        title="View Details"
                    >
                        <FontAwesomeIcon icon="eye" class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex flex-col sm:flex-row justify-between items-center gap-4 px-2">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Showing <span class="font-medium text-gray-700 dark:text-gray-300">{{ pagination.from || 0 }}</span> to 
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ pagination.to || 0 }}</span> of 
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ pagination.total }}</span> entries
        </div>
        
        <div class="flex items-center gap-2">
            <a
                v-if="pagination.current_page > 1"
                :href="getPageUrl(pagination.current_page - 1)"
                class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex items-center gap-1"
            >
                <FontAwesomeIcon icon="chevron-left" class="w-3 h-3" />
                <span>Previous</span>
            </a>
            
            <div class="flex items-center gap-1">
                <span class="px-3 py-1.5 bg-purple-600 text-white rounded-lg text-sm font-medium">{{ pagination.current_page }}</span>
                <span class="text-gray-400 dark:text-gray-500 text-sm">/</span>
                <span class="px-3 py-1.5 text-gray-600 dark:text-gray-300 text-sm">{{ pagination.last_page }}</span>
            </div>
            
            <a
                v-if="pagination.current_page < pagination.last_page"
                :href="getPageUrl(pagination.current_page + 1)"
                class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex items-center gap-1"
            >
                <span>Next</span>
                <FontAwesomeIcon icon="chevron-right" class="w-3 h-3" />
            </a>
        </div>
    </div>
        </div>

        <!-- Details Modal -->
        <AuditLogDetailsModal
            :show="showModal"
            :log="selectedLog"
            @close="closeModal"
        />
    </SuperAdminLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* New log highlight animation */
@keyframes highlight-fade {
    0% {
        background-color: rgba(34, 197, 94, 0.2);
    }
    50% {
        background-color: rgba(34, 197, 94, 0.1);
    }
    100% {
        background-color: transparent;
    }
}

.animate-highlight-fade {
    animation: highlight-fade 5s ease-out forwards;
}

/* Simple white scrollbar */
.simple-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.simple-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.simple-scrollbar::-webkit-scrollbar-thumb {
    background: white;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.simple-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #f9fafb;
}

/* Dark mode scrollbar */
.dark .simple-scrollbar::-webkit-scrollbar-track {
    background: #1f2937;
}

.dark .simple-scrollbar::-webkit-scrollbar-thumb {
    background: #374151;
    border: 1px solid #4b5563;
}

.dark .simple-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #4b5563;
}

/* For Firefox */
.simple-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: white #f1f1f1;
}

.dark .simple-scrollbar {
    scrollbar-color: #374151 #1f2937;
}
</style>