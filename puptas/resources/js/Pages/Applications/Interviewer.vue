<template>
    <Head title="All Interviewer Applications" />
    <InterviewerLayout>
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
                        <span>{{ evaluationStatusFilter ? getEvaluationStatusText({ pipeline_status: evaluationStatusFilter }) : 'All Status' }}</span>
                    </button>
                    <div
                        v-if="showStatusDropdown"
                        class="absolute top-full mt-2 right-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 rounded z-50 text-sm min-w-[200px] dark:border-gray-700"
                    >
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = ''; showStatusDropdown = false;">
                            All
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'for_interview'; showStatusDropdown = false;">
                            For Interview
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'interview_returned'; showStatusDropdown = false;">
                            Returned for Revision
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'interview_passed'; showStatusDropdown = false;">
                            Interview Passed
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'for_medical'; showStatusDropdown = false;">
                            For Medical
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'officially_enrolled'; showStatusDropdown = false;">
                            Officially Enrolled
                        </button>
                    </div>
                </div>

                <!-- Sort By -->
                <select v-model="sortKey" class="px-7 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent">
                    <option value="lastname">Last Name</option>
                    <option value="firstname">First Name</option>
                    <option value="program.name">Course</option>
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
                            <th class="pb-2">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="user in paginatedUsers"
                            :key="user.id"
                            @click="selectUser(user)"
                            :class="[
                                'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/30 transition',
                                user.is_evaluation_completed ? 'opacity-60' : ''
                            ]"
                        >
                            <td class="py-3 text-gray-900 dark:text-white font-medium">
                                <div class="flex items-center gap-2">
                                    <span>{{ user.firstname }} {{ user.lastname }}</span>
                                    <span 
                                        v-if="user.is_evaluation_completed"
                                        class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs rounded-full font-semibold"
                                    >
                                        Evaluated
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">
                                {{ user.application?.program?.name || "—" }}
                            </td>
                            <td class="py-3">
                                <span
                                    :class="getEvaluationStatusClass(user)"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ getEvaluationStatusText(user) }}
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
                <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Showing <span class="font-medium">{{ paginatedUsers.length }}</span> of 
                        <span class="font-medium">{{ filteredUsers.length }}</span> applicants
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="currentPage--" 
                            :disabled="currentPage === 1"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                        >
                            Previous
                        </button>
                        
                        <div class="flex items-center space-x-2">
                            <span class="px-4 py-2 bg-[#9E122C] text-white rounded-lg font-medium dark:bg-gray-900 dark:text-gray-900">{{ currentPage }}</span>
                            <span class="text-gray-500 dark:text-gray-400">of</span>
                            <span class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 font-medium">{{ totalPages || 1 }}</span>
                        </div>
                        
                        <button 
                            @click="currentPage++" 
                            :disabled="currentPage === totalPages || totalPages === 0"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicant Details Panel -->
        <transition name="slide-fade">
            <div
                v-if="selectedUser"
                class="fixed top-0 right-0 w-full md:w-2/5 h-full bg-white dark:bg-gray-900 p-6 z-50 shadow-xl transition duration-300 ease-in-out overflow-y-auto"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Interview Details</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Application ID: {{ selectedUser.application?.id || 'N/A' }}</p>
                    </div>
                    <button @click="closeUserCard" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition min-h-[44px] min-w-[44px]">
                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Applicant Info Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Personal Info -->
                    <div class="lg:col-span-2">
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Applicant Information</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Full Name</p>
                                <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.firstname }} {{ selectedUser.lastname }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Email Address</p>
                                <p class="text-gray-900 dark:text-white">{{ selectedUser.email }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Current Program (1st Choice)</p>
                                <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.application?.program?.name || "—" }}</p>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">{{ selectedUser.application?.program?.code || "" }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">{{ selectedUser.application?.program?.slots || 0 }} slots remaining</p>
                            </div>
                            <div v-if="selectedUser.application?.second_choice">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Second Choice Program</p>
                                <p class="text-gray-900 dark:text-white font-medium">{{ selectedUser.application?.second_choice?.name || "—" }}</p>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">{{ selectedUser.application?.second_choice?.code || "" }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">{{ selectedUser.application?.second_choice?.slots || 0 }} slots remaining</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Status</p>
                                <span :class="getStatusClass(selectedUser.status)" 
                                      class="px-3 py-1 rounded-full text-sm font-semibold inline-block">
                                    {{ selectedUser.status || "Pending" }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Program Selection for Accept/Reject -->
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Select Your Program</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            Choose the program you are interviewing for:
                        </p>
                        <select
                            v-model="selectedProgramId"
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white mb-4 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                        >
                            <option disabled value="">Select Program</option>
                            <option
                                v-for="p in props.assignedPrograms"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.code }} - {{ p.name }}
                            </option>
                        </select>

                        <div class="flex space-x-2">
                            <button
                                @click="acceptApplication"
                                :class="[getButtonClass('success'), 'flex-1 px-4 py-2 rounded-lg transition font-medium min-h-[44px]']"
                                :disabled="!selectedProgramId"
                            >
                                ✓ Accept
                            </button>
                            <button
                                @click="rejectApplication"
                                :class="[getButtonClass('danger'), 'flex-1 px-4 py-2 rounded-lg transition font-medium min-h-[44px]']"
                                :disabled="!selectedProgramId"
                            >
                                ✗ Reject
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Grades Section -->
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Academic Grades</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Mathematics</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser?.grades?.mathematics || "—" }}</p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Science</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser?.grades?.science || "—" }}</p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">English</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser?.grades?.english || "—" }}</p>
                        </div>
                    </div>
                </div>

                <!-- Uploaded Documents -->
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Required Documents</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div
                            v-for="(file, key) in selectedUserFiles"
                            :key="key"
                            class="group relative"
                        >
                            <!-- Document Card -->
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-800">
                                <div 
                                    class="relative cursor-pointer"
                                    @click="hasImagePreview(file) ? openImageModal(file) : null"
                                >
                                    <img
                                        v-if="hasImagePreview(file)"
                                        :src="getFileUrl(file)"
                                        :alt="formatFileKey(key)"
                                        class="w-full h-32 object-cover hover:opacity-90 transition pointer-events-none"
                                    />
                                    <div
                                        v-else
                                        class="w-full h-32 flex items-center justify-center bg-gray-50 dark:bg-gray-800"
                                    >
                                        <svg class="w-8 h-8 text-gray-400 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- Document Label -->
                                <div class="p-2 border-t border-gray-200 dark:border-gray-700">
                                    <p class="block text-xs font-medium text-gray-700 dark:text-gray-300 truncate" :title="formatFileKey(key)">
                                        {{ formatFileKey(key) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application History -->
                <div v-if="selectedUser?.application?.processes?.length">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Application Timeline</h4>
                    <div class="space-y-3">
                        <div
                            v-for="(process, index) in selectedUser.application.processes"
                            :key="index"
                            class="flex items-start space-x-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg"
                        >
                            <div :class="[
                                'w-3 h-3 rounded-full mt-1.5 flex-shrink-0',
                                process.status === 'completed' ? 'bg-green-500' :
                                process.status === 'in_progress' ? 'bg-yellow-500' :
                                'bg-red-500'
                            ]"></div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">
                                            {{ capitalize(process.stage) }}
                                        </p>
                                        <p v-if="process.notes" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            {{ process.notes }}
                                        </p>
                                    </div>
                                    <span :class="[
                                        'px-2 py-1 rounded-full text-xs font-semibold',
                                        process.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' :
                                        process.status === 'in_progress' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' :
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                    ]">
                                        {{ capitalize(process.status) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    {{ formatDate(process.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Snackbar Notification -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                data-testid="snackbar"
                :class="[
                    'fixed bottom-8 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg text-white font-medium z-50',
                    snackbar.type === 'success' ? 'bg-green-600' : '',
                    snackbar.type === 'error' ? 'bg-red-600' : '',
                    snackbar.type === 'info' ? 'bg-blue-600' : ''
                ]"
            >
                {{ snackbar.message }}
            </div>
        </transition>
    </InterviewerLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import InterviewerLayout from "@/Layouts/InterviewerLayout.vue";

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

const props = defineProps({
    user: Object,
    assignedPrograms: Array,
});

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

const currentPage = ref(1);
const itemsPerPage = 10;
const sortKey = ref("lastname"); // default to lastname
const evaluationStatusFilter = ref(""); // Filter for evaluation completion status
const sortAsc = ref(true);
const showStatusDropdown = ref(false);
const filterDropdownRef = ref(null);

const page = usePage();
const users = ref(page.props.users || []);

//const users = ref([]);
const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const snackbar = ref({
    visible: false,
    message: "",
    type: "success",
});

const showSnackbar = (msg, type = "success", duration = 3000) => {
    snackbar.value.message = msg;
    snackbar.value.type = type;
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

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700";
    if (s === "cleared_for_enrollment" || s === "officially_enrolled") return "bg-green-100 text-green-700";
    if (s === "submitted" || s === "pending") return "bg-yellow-100 text-yellow-700";
    if (s === "rejected") return "bg-red-100 text-red-700";
    if (s === "returned") return "bg-red-100 text-red-700";
    return "bg-gray-100 text-gray-600";
};

const getButtonClass = (type) => {
    const classes = {
        primary: 'bg-[#9E122C] text-white hover:bg-[#b51834]',
        success: 'bg-green-600 text-white hover:bg-green-700',
        danger: 'bg-red-600 text-white hover:bg-red-700',
        secondary: 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
    };
    return classes[type] || classes.secondary;
};

// Get evaluation-specific status text
const getEvaluationStatusText = (user) => {
    switch (user.pipeline_status) {
        case 'for_evaluation':       return 'For Evaluation';
        case 'evaluation_returned':  return 'Returned for Revision';
        case 'evaluation_passed':    return 'Evaluation Passed';
        case 'for_interview':        return 'For Interview';
        case 'interview_returned':   return 'Returned for Revision';
        case 'interview_passed':     return 'Interview Passed';
        case 'for_medical':          return 'For Medical';
        case 'medical_cleared':      return 'Medical Cleared';
        case 'medical_rejected':     return 'Medical Rejected';
        case 'for_records':          return 'For Records';
        case 'officially_enrolled':  return 'Officially Enrolled';
        case 'rejected':             return 'Rejected';
        default:                     return 'Unknown';
    }
};

// Get evaluation-specific status styling
const getEvaluationStatusClass = (user) => {
    switch (user.pipeline_status) {
        case 'for_evaluation':        return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
        case 'evaluation_returned':   return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'evaluation_passed':     return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        case 'for_interview':         return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
        case 'interview_returned':    return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'interview_passed':      return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        case 'for_medical':           return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
        case 'medical_cleared':       return 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-300';
        case 'medical_rejected':      return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'for_records':           return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300';
        case 'officially_enrolled':   return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 font-semibold';
        case 'rejected':              return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        default:                      return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
    }
};

const fetchUsers = async () => {
    try {
        const response = await fetch("/interviewer-dashboard/applicants", {
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

onMounted(() => {
    fetchUsers();
    fetchPrograms();
    document.addEventListener('click', handleOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick);
});

const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return users.value
        .filter((u) => {
            const fullName = `${u.firstname} ${u.lastname}`.toLowerCase();
            const matchesSearch = fullName.includes(q);
            const matchesEvaluationStatus = evaluationStatusFilter.value
                ? u.pipeline_status === evaluationStatusFilter.value
                : true;
            return matchesSearch && matchesEvaluationStatus;
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
        // Open panel immediately with basic user data
        selectedUser.value = {
            ...user,
            grades: null,
            application: user.application || null,
        };
        
        // Show loading state for files
        selectedUserFiles.value = { loading: true };

        // Fetch full data in background
        const response = await axios.get(
            `/interviewer-dashboard/application/${user.id}`
        );

        // Update with full data
        selectedUser.value = {
            ...user,
            ...response.data.user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
                program: response.data.user.application?.program || null,
                second_choice: response.data.user.application?.second_choice || null,
            },
            grades: response.data.user.grades || null,
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        
        // Display user-friendly error notification
        if (error.response && error.response.status === 403) {
            showSnackbar("Unauthorized access. Application is not at the interviewer stage.", "error");
        } else {
            showSnackbar("Failed to load applicant data. Please try again.", "error");
        }
        
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
};

// Check if the current user's interviewer process is completed
const isEvaluationCompleted = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) {
        return false;
    }
    const interviewerProcess = selectedUser.value.application.processes.find(
        p => p.stage === 'interviewer'
    );
    return interviewerProcess && interviewerProcess.status === 'completed';
});

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
        const currentUserId = selectedUser.value.id;
        
        await axios.post(`/dashboard/return-files/${currentUserId}`, {
            files: selected,
            note: returnNote.value.trim(),
        });

        alert("Files returned and application status logged.");

        isEvaluating.value = false;
        filesToReturn.value = {};
        returnNote.value = "";

        // ✅ Refetch updated user list & status counts
        await fetchUsers();
        
        // Find and select the updated user from the fresh list
        const updatedUser = users.value.find(u => u.id === currentUserId);
        if (updatedUser) {
            await selectUser(updatedUser);
        }
    } catch (error) {
        console.error(error);
        alert("Return failed.");
    }
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString(); // or .toLocaleDateString() if you prefer
};

const selectedProgramId = ref("");

const acceptApplication = async () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to accept the applicant into", "error");
        return;
    }

    try {
        const currentUserId = selectedUser.value.id;
        
        await axios.post(
            `/interviewer-dashboard/accept/${currentUserId}`,
            {
                program_id: selectedProgramId.value,
            }
        );
        showSnackbar("Application accepted successfully", "success");
        selectedProgramId.value = "";
        
        await fetchUsers();
        
        // Find and select the updated user from the fresh list
        const updatedUser = users.value.find(u => u.id === currentUserId);
        if (updatedUser) {
            await selectUser(updatedUser);
        } else {
            selectedUser.value = null;
        }
    } catch (e) {
        console.error("Accept failed:", e);
        const msg =
            e.response?.data?.message ||
            "Failed to accept application due to an unexpected error.";
        showSnackbar(msg, "error");
    }
};

const rejectApplication = async () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to reject the applicant from", "error");
        return;
    }

    try {
        const currentUserId = selectedUser.value.id;
        
        await axios.post(
            `/interviewer-dashboard/reject/${currentUserId}`,
            {
                program_id: selectedProgramId.value,
            }
        );
        showSnackbar("Application rejected successfully", "success");
        selectedProgramId.value = "";
        
        await fetchUsers();
        
        // Find and select the updated user from the fresh list
        const updatedUser = users.value.find(u => u.id === currentUserId);
        if (updatedUser) {
            await selectUser(updatedUser);
        } else {
            selectedUser.value = null;
        }
    } catch (e) {
        console.error("Reject failed:", e);
        const msg =
            e.response?.data?.message ||
            "Failed to reject application due to an unexpected error.";
        showSnackbar(msg, "error");
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
// Reset page when filters/search change
watch([searchQuery, evaluationStatusFilter, sortKey, sortAsc], () => {
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
    evaluationStatusFilter.value = "";
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

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
