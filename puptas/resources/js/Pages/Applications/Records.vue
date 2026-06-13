<template>
    <Head title="All Records Applications" />
    <RecordStaffLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8 overflow-x-hidden overflow-y-auto">
            <!-- Filters and Controls -->
            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 mb-6">
                <!-- Search Input -->
                <div class="flex-1 relative">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2 dark:text-gray-200"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                    />
                </div>

                <!-- Status Filter Dropdown -->
                <div class="relative">
                    <button
                        @click="showStatusDropdown = !showStatusDropdown"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex items-center space-x-2"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L15 12.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 019 17v-4.586L3.293 6.707A1 1 0 013 6V4z"
                            />
                        </svg>
                        <span>{{ statusFilter ? getStatusText({ pipeline_status: statusFilter }) : 'All Status' }}</span>
                    </button>
                    <div
                        v-if="showStatusDropdown"
                        class="absolute top-full mt-2 right-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 rounded z-50 text-sm min-w-[200px] dark:border-gray-700"
                    >
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="statusFilter = ''; showStatusDropdown = false;">
                            All
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="statusFilter = 'medical_cleared'; showStatusDropdown = false;">
                            Medical Cleared
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="statusFilter = 'for_records'; showStatusDropdown = false;">
                            For Records
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="statusFilter = 'officially_enrolled'; showStatusDropdown = false;">
                            Officially Enrolled
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="statusFilter = 'rejected'; showStatusDropdown = false;">
                            Rejected
                        </button>
                    </div>
                </div>

                <!-- Sort By -->
                <select v-model="sortKey" class="px-7 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent">
                    <option value="lastname">Last Name</option>
                    <option value="firstname">First Name</option>
                    <option value="program.name">Course</option>
                    <option value="status">Status</option>
                </select>

                <!-- Sort Order -->
                <button 
                    @click="sortAsc = !sortAsc" 
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex items-center space-x-2"
                >
                    <span>{{ sortAsc ? 'Ascending' : 'Descending' }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="sortAsc" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4 4m0 0l4-4m-4 4V4" />
                    </svg>
                </button>

                <!-- Clear Filters -->
                <button 
                    @click="clearFilters" 
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium"
                >
                    Clear
                </button>
            </div>

            <!-- Users Table -->
            <div v-if="isLoading" class="text-center text-gray-500 py-8 dark:text-gray-300">Loading applicants…</div>
            <div v-else-if="errorMessage" class="text-center text-red-500 py-8 dark:text-red-300">Error: {{ errorMessage }}</div>

            <!-- Users Table -->
            <div v-else class="bg-white dark:bg-gray-800/20 rounded-xl shadow p-2 overflow-x-auto">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Showing {{ paginatedUsers.length }} of {{ filteredUsers.length }} users
                </div>
                
                <table class="min-w-full text-base">
                    <thead>
                        <tr class="text-left font-semibold text-black dark:text-white">
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('lastname')">
                                Name
                                <span v-if="sortKey === 'lastname'" class="ml-1">
                                    {{ sortAsc ? '↑' : '↓' }}
                                </span>
                            </th>
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('program.name')">
                                Course
                                <span v-if="sortKey === 'program.name'" class="ml-1">
                                    {{ sortAsc ? '↑' : '↓' }}
                                </span>
                            </th>
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('status')">
                                Status
                                <span v-if="sortKey === 'status'" class="ml-1">
                                    {{ sortAsc ? '↑' : '↓' }}
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="user in paginatedUsers"
                            :key="user.id"
                            @click="selectUser(user)"
                            class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/30 transition"
                        >
                            <td class="py-3 text-gray-900 dark:text-white font-medium">
                                {{ user.firstname }} {{ user.lastname }}
                            </td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">
                                {{ user.program?.name || "—" }}
                            </td>
                            <td class="py-3">
                                <span
                                    :class="getStatusClass(user)"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ getStatusText(user) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="paginatedUsers.length === 0">
                            <td colspan="3" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                No applicants found matching your criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-400">
                            <span v-if="!filteredUsers.length || filteredUsers.length === 0">
                                Showing 0 to 0 of 0 results
                            </span>
                            <span v-else>
                                Showing {{ (currentPage - 1) * itemsPerPage + 1 }} 
                                to {{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }} 
                                of {{ filteredUsers.length }} results
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button
                                :disabled="currentPage === 1"
                                @click.prevent="currentPage--"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                            >
                                <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                Previous
                            </button>
                            <div class="flex items-center space-x-2 mx-2 text-sm text-gray-700 dark:text-gray-300">
                                <span>Page</span>
                                <input
                                    type="number"
                                    :value="currentPage"
                                    min="1"
                                    :max="totalPages || 1"
                                    @change="currentPage = Math.max(1, Math.min($event.target.value, totalPages || 1))"
                                    class="w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent font-medium text-sm"
                                />
                                <span>of <span class="font-semibold">{{ totalPages || 1 }}</span></span>
                            </div>
                            <button
                                :disabled="currentPage === totalPages || totalPages === 0"
                                @click.prevent="currentPage++"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                            >
                                Next
                                <svg class="h-5 w-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicant Details Panel -->
        <transition name="slide-fade">
            <div
                v-if="selectedUser"
                class="fixed top-0 right-0 w-full md:w-[400px] h-full bg-white dark:bg-gray-900 z-50 shadow-2xl flex flex-col overflow-hidden"
            >
                <!-- Panel Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shrink-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Applicant Details</h3>
                    <button
                        @click="closeUserCard"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                        aria-label="Close panel"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Scrollable Body -->
                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                    <!-- Profile Card -->
                    <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <div class="w-14 h-14 rounded-full bg-[#9E122C] text-white flex items-center justify-center text-xl font-bold shrink-0">
                            {{ (selectedUser.firstname || selectedUser.email || '?').charAt(0).toUpperCase() }}{{ (selectedUser.lastname || '').charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                                {{ selectedUser.lastname ? `${selectedUser.lastname}, ${selectedUser.firstname}` : (selectedUser.email || '—') }}
                            </h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ selectedUser.reference_number || 'No reference number' }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ selectedUser.email }}</p>
                        </div>
                    </div>

                    <!-- Enrollment Status + Program -->
                    <div class="p-4 bg-[#9E122C]/5 dark:bg-[#9E122C]/10 rounded-xl border border-[#9E122C]/20">
                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                            {{ selectedUser?.application?.enrollment_status === 'officially_enrolled' ? 'Officially Enrolled In' : 'Temporarily Enrolled In' }}
                        </h4>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ selectedUser?.application?.program?.code }} – {{ selectedUser?.application?.program?.name }}
                        </p>
                    </div>

                    <!-- Enrollment Actions -->
                    <div class="flex gap-2">
                        <button
                            v-if="selectedUser?.application?.enrollment_status !== 'officially_enrolled'"
                            @click="acceptApplication"
                            class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition"
                        >
                            Tag: Officially Enrolled
                        </button>
                        <button
                            @click="untagApplication"
                            class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                        >
                            Untag
                        </button>
                    </div>

                    <!-- Uploaded Documents -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Uploaded Documents</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div
                                v-for="(file, key) in selectedUserFiles"
                                :key="key"
                                class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden min-w-0"
                            >
                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2 truncate">{{ formatFileKey(key) }}</p>
                                <img
                                    v-if="hasImagePreview(file)"
                                    :src="getFileUrl(file)"
                                    alt="Uploaded Document"
                                    class="w-full aspect-[4/3] object-cover rounded-lg cursor-pointer hover:opacity-80 transition"
                                    @click="openImageModal(file)"
                                />
                                <div
                                    v-else
                                    class="w-full aspect-[4/3] flex items-center justify-center text-xs text-gray-400 dark:text-gray-500 bg-gray-200 dark:bg-gray-700 rounded-lg"
                                >
                                    No file
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Application History -->
                    <div v-if="selectedUser?.application?.processes?.length">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Application History</h4>
                        <div class="space-y-3">
                            <div
                                v-for="(process, index) in selectedUser.application.processes"
                                :key="index"
                                class="relative pl-6 pb-3 border-l-2 border-[#9E122C] last:border-0"
                            >
                                <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-[#9E122C] border-2 border-white dark:border-gray-900"></div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ formatStage(process.stage) }}
                                    <span :class="{
                                        'text-green-600 dark:text-green-400': process.status === 'completed',
                                        'text-yellow-600 dark:text-yellow-400': process.status === 'in_progress',
                                        'text-red-600 dark:text-red-400': process.status === 'returned',
                                    }">• {{ capitalize(process.status) }}</span>
                                </p>
                                <p v-if="process.notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">{{ process.notes }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ formatDate(process.created_at) }}</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </transition>
        <!-- Snackbar -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-4 py-2 rounded shadow-lg z-50 dark:text-gray-900"
            >
                {{ snackbar.message }}
            </div>
        </transition>

        <!-- Image Preview Modal -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50 dark:bg-white"
            @click.self="closeImageModal"
        >
            <img
                :src="previewImage"
                alt="Preview"
                class="max-w-full max-h-full rounded shadow-lg"
            />
            <button
                @click="closeImageModal"
                class="absolute top-5 right-5 text-white text-4xl font-bold hover:text-gray-300 dark:text-gray-900"
                aria-label="Close preview"
            >
                &times;
            </button>
        </div>
    </RecordStaffLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { Head } from "@inertiajs/vue3";

import {
    Chart as ChartJS,
    LineController,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Tooltip,
    Title,
    Legend,
} from "chart.js";

ChartJS.register(
    LineController,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Tooltip,
    Title,
    Legend
);

import { usePage } from "@inertiajs/vue3";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBolt } from "@fortawesome/free-solid-svg-icons";
import RecordStaffLayout from "@/Layouts/RecordStaffLayout.vue";

const currentPage = ref(1);
const itemsPerPage = 10;
const sortKey = ref("lastname"); // default to lastname
const statusFilter = ref("");
const sortAsc = ref(true);
const showStatusDropdown = ref(false);
const filterDropdownRef = ref(null);
const autoRefreshTimer = ref(null);
const POLL_INTERVAL_MS = 10000;

const page = usePage();
const users = ref(page.props.users || []);

//const users = ref([]);
const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const selectedProgramId = ref("");
const snackbar = ref({
    visible: false,
    message: "",
});

const showSnackbar = (msg, duration = 3000) => {
    snackbar.value.message = msg;
    snackbar.value.visible = true;
    setTimeout(() => {
        snackbar.value.visible = false;
    }, duration);
};

const chartData = {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
    datasets: [
        {
            label: "Submitted",
            data: [5, 20, 35, 50, 70, 90],
            borderColor: "#2563EB",
            backgroundColor: "rgba(37, 99, 235, 0.2)",
            tension: 0.4,
        },
        {
            label: "Accepted",
            data: [2, 10, 15, 25, 40, 60],
            borderColor: "#10B981",
            backgroundColor: "rgba(16, 185, 129, 0.2)",
            tension: 0.4,
        },
    ],
};

const getStatusText = (user) => {
    switch (user.pipeline_status) {
        case 'for_evaluation':       return 'For Evaluation';
        case 'evaluation_returned':  return 'Returned for Revision';
        case 'evaluation_passed':    return 'Evaluation Passed';
        case 'for_interview':        return 'For Interview';
        case 'interview_returned':   return 'Returned for Revision';
        case 'interview_passed':     return 'Interview Passed';
        case 'interview_transferred':return 'Course Transferred';
        case 'for_medical':          return 'For Medical';
        case 'medical_cleared':      return 'Medical Cleared';
        case 'medical_rejected':     return 'Medical Rejected';
        case 'for_records':          return 'For Records';
        case 'officially_enrolled':  return 'Officially Enrolled';
        case 'rejected':             return 'Rejected';
        default:                     return 'Unknown';
    }
};

const getStatusClass = (user) => {
    const ps = typeof user === 'object' && user.pipeline_status ? user.pipeline_status : user;
    switch (ps) {
        case 'for_evaluation':        return 'bg-yellow-100 text-yellow-700';
        case 'evaluation_returned':   return 'bg-red-100 text-red-700';
        case 'evaluation_passed':     return 'bg-green-100 text-green-700';
        case 'for_interview':         return 'bg-yellow-100 text-yellow-700';
        case 'interview_returned':    return 'bg-red-100 text-red-700';
        case 'interview_passed':      return 'bg-green-100 text-green-700';
        case 'interview_transferred': return 'bg-purple-100 text-purple-700';
        case 'for_medical':           return 'bg-blue-100 text-blue-700';
        case 'medical_cleared':       return 'bg-teal-100 text-teal-700';
        case 'medical_rejected':      return 'bg-red-100 text-red-700';
        case 'for_records':           return 'bg-indigo-100 text-indigo-700';
        case 'officially_enrolled':   return 'bg-green-100 text-green-700 font-semibold';
        case 'rejected':              return 'bg-red-100 text-red-700';
        default:                      return 'bg-gray-100 text-gray-600';
    }
};

const fetchUsers = async () => {
    try {
        const response = await fetch("/record-dashboard/applicants", {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        if (!response.ok) throw new Error("Failed to fetch users");
        users.value = await response.json();
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const handleOutsideClick = (e) => {
    if (filterDropdownRef.value && !filterDropdownRef.value.contains(e.target)) {
        showStatusDropdown.value = false;
    }
};

const refreshApplicants = async () => {
    await fetchUsers();

    if (!selectedUser.value) {
        return;
    }

    const existsInQueue = users.value.some((u) => String(u.id) === String(selectedUser.value.id));
    if (!existsInQueue) {
        closeUserCard();
    }
};

onMounted(() => {
    fetchUsers();
    fetchPrograms();
    document.addEventListener('click', handleOutsideClick);
    autoRefreshTimer.value = setInterval(refreshApplicants, POLL_INTERVAL_MS);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick);
    if (autoRefreshTimer.value) {
        clearInterval(autoRefreshTimer.value);
        autoRefreshTimer.value = null;
    }
});

const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return users.value
        .filter((u) => {
            const fullName = `${u.firstname} ${u.lastname}`.toLowerCase();
            const matchesSearch = fullName.includes(q);
            const matchesStatus = statusFilter.value
                ? u.pipeline_status === statusFilter.value
                : true;
            return matchesSearch && matchesStatus;
        })
        .sort((a, b) => {
            let aVal, bVal;
            
            if (sortKey.value === 'program.name') {
                aVal = (a.program?.name || "").toString().toLowerCase();
                bVal = (b.program?.name || "").toString().toLowerCase();
            } else {
                aVal = (a[sortKey.value] || "").toString().toLowerCase();
                bVal = (b[sortKey.value] || "").toString().toLowerCase();
            }
            
            return sortAsc.value
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
});

const displayedUsers = computed(() => {
    if (searchQuery.value.trim()) return filteredUsers.value;
    return users.value.slice(0, 4);
});

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            `/record-dashboard/application/${user.id}`
        );

        selectedUser.value = {
            ...user,
            ...response.data.user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
                program: response.data.user.application?.program || null,
            },
            grades: response.data.user.grades || null,
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};

        // ✅ Add this line to load programs into the dropdown
        await fetchPrograms();
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
};

const formatFileKey = (key) => {
    const map = {
        file10Front: 'Grade 10 Report Front',
        file10: 'Grade 10 Report Back',
        file11Front: "Grade 11 Report Front",
        file11: "Grade 11 Report Back",
        file12Front: "Grade 12 Report Front",
        file12: "Grade 12 Report Back",
        schoolId: "School ID",
        nonEnrollCert: "Certificate of Non-Enrollment",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const getFileUrl = (file) => (typeof file === "string" ? file : file?.url || "");

const hasImagePreview = (file) =>
    Boolean(getFileUrl(file)) && (typeof file === "string" || file?.isImage !== false);

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (file) => {
    const src = getFileUrl(file);
    if (!src || !hasImagePreview(file)) {
        return;
    }

    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
};

const isEvaluating = ref(false);
const filesToReturn = ref({});

const returnNote = ref("");

const startEvaluation = () => {
    isEvaluating.value = true;
    filesToReturn.value = [];
    returnNote.value = "";
};

const cancelEvaluation = () => {
    isEvaluating.value = false;
    filesToReturn.value = [];
    returnNote.value = "";
};

const submitReturn = async () => {
    const selected = Object.keys(filesToReturn.value).filter(
        (k) => filesToReturn.value[k]
    );
    if (selected.length === 0 || !returnNote.value.trim()) {
        alert("Please select files and enter a return note.");
        return;
    }

    try {
        await axios.post(
            `/record-dashboard/return-files/${selectedUser.value.id}`,
            {
                files: selected,
                note: returnNote.value.trim(),
            }
        );

        alert("Files returned and application status logged.");

        isEvaluating.value = false;
        filesToReturn.value = {};
        returnNote.value = "";

        // ✅ Refetch updated user list & status counts
        await fetchUsers();
        await selectUser(selectedUser.value);
    } catch (error) {
        console.error(error);
        alert("Return failed.");
    }
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatStage = (stage) => {
    const map = {
        'evaluator': 'DE, GE',
        'interviewer': 'Interviewer',
        'medical': 'Medical',
        'record_staff': 'Record Staff'
    };
    return map[stage] || (stage ? stage.charAt(0).toUpperCase() + stage.slice(1).replace(/_/g, " ") : "");
};

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString(); // or .toLocaleDateString() if you prefer
};

const acceptApplication = async () => {
    try {
        const response = await axios.post(`/record-dashboard/tag/${selectedUser.value.id}`);
        showSnackbar("Tagged as officially enrolled.");

        // Update the selected user's enrollment status immediately in the UI
        if (selectedUser.value?.application) {
            selectedUser.value.application.enrollment_status = response.data.enrollment_status || 'officially_enrolled';
            selectedUser.value.enrollment_status = response.data.enrollment_status || 'officially_enrolled';
        }

        // Also update in the users list
        const idx = users.value.findIndex(u => u.id === selectedUser.value.id);
        if (idx !== -1) {
            users.value[idx] = {
                ...users.value[idx],
                enrollment_status: response.data.enrollment_status || 'officially_enrolled',
                application: {
                    ...users.value[idx].application,
                    enrollment_status: response.data.enrollment_status || 'officially_enrolled',
                }
            };
        }

        await fetchUsers();
    } catch (e) {
        console.error("Accept failed:", e);
        const msg =
            e.response?.data?.message ||
            "Failed to accept application due to an unexpected error.";
        showSnackbar(msg);
    }
};

const untagApplication = async () => {
    try {
        await axios.post(`/record-dashboard/untag/${selectedUser.value.id}`);
        showSnackbar("Reverted to temporary enrolled.");
        selectedUser.value = null;
        await fetchUsers();
    } catch (e) {
        console.error("Revert failed:", e);
        const msg =
            e.response?.data?.message ||
            "Failed to revert application due to an unexpected error.";
        showSnackbar(msg);
    }
};

const transferApplication = async () => {
    try {
        await axios.post(
            `/interviewer-dashboard/transfer/${selectedUser.value.id}`,
            {
                program_id: selectedProgramId.value,
            }
        );
        alert("Applicant transferred successfully!");
        selectedUser.value = null;
        selectedProgramId.value = "";
        fetchUsers();
    } catch (e) {
        console.error("Transfer failed", e);

        // ✅ Fix: use `e` not `error`
        if (e.response?.data?.message) {
            showSnackbar(e.response.data.message); // ✅ show server message
        } else {
            showSnackbar("Transfer failed due to an unexpected error.");
        }
    }
};

const availablePrograms = ref([]);

const fetchPrograms = async () => {
    try {
        const response = await axios.get("/record-dashboard/programs");
        availablePrograms.value = response.data.programs;
    } catch (e) {
        console.error("Failed to load programs", e);
    }
};

const totalPages = computed(() =>
    Math.ceil(filteredUsers.value.length / itemsPerPage)
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
});

// Reset page when filters/search change
watch([searchQuery, statusFilter, sortKey, sortAsc], () => {
    currentPage.value = 1;
});

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = true;
    }
};

const clearFilters = () => {
    searchQuery.value = "";
    statusFilter.value = "";
    sortKey.value = "lastname";
    sortAsc.value = true;
    currentPage.value = 1;
    showStatusDropdown.value = false;
};
</script>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.3s ease;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateX(100%);
    opacity: 0;
}
</style>
