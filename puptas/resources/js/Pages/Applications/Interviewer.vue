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
                        <span>{{ evaluationStatusFilter ? evaluationStatusFilter.charAt(0).toUpperCase() + evaluationStatusFilter.slice(1) : 'All Status' }}</span>
                    </button>
                    <div
                        v-if="showStatusDropdown"
                        class="absolute top-full mt-2 right-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 rounded z-50 text-sm min-w-[150px] dark:border-gray-700"
                    >
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="
                                evaluationStatusFilter = '';
                                showStatusDropdown = false;
                            "
                        >
                            All
                        </button>
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="
                                evaluationStatusFilter = 'pending';
                                showStatusDropdown = false;
                            "
                        >
                            Pending Review
                        </button>
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="
                                evaluationStatusFilter = 'completed';
                                showStatusDropdown = false;
                            "
                        >
                            Already Evaluated
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

        <!-- User Info Modal -->

        <!-- User Info Modal -->
        <transition name="slide-fade">
            <div
                v-if="selectedUser"
                class="fixed top-0 right-0 w-full md:w-1/3 h-full bg-white dark:bg-gray-800 dark:bg-gray-900 p-6 z-50 shadow-xl shadow-red-200 transition duration-300 ease-in-out overflow-y-auto"
            >
                <button
                    class="mt-6 px-4 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition dark:bg-gray-900 dark:text-gray-900"
                    @click="closeUserCard"
                >
                    Close
                </button>
                
                <!-- Evaluation Complete Badge -->
                <div 
                    v-if="isEvaluationCompleted"
                    class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-500 rounded-lg"
                >
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">
                        ✓ Evaluation Completed
                    </p>
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                        Evaluation for this stage has been processed. 
                        Course management is still available below.
                    </p>
                </div>
                
                <h3
                    class="text-xl font-semibold text-gray-900 dark:text-white mb-2"
                >
                    User Information
                </h3>
                <p class="text-gray-800 dark:text-gray-200 font-medium">
                    Name: {{ selectedUser.lastname }},
                    {{ selectedUser.firstname }}
                </p>
                <p class="text-gray-700 dark:text-gray-400">
                    Email: {{ selectedUser.email }}
                </p>
                <h4
                    class="text-sm font-bold text-gray-700 dark:text-white mb-1"
                >
                    Grades
                </h4>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Math: {{ selectedUser?.grades?.mathematics ?? "—" }}
                </p>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Science: {{ selectedUser?.grades?.science ?? "—" }}
                </p>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    English: {{ selectedUser?.grades?.english ?? "—" }}
                </p>

                <div
                    class="mt-3 p-2 border rounded bg-gray-100 dark:bg-gray-800"
                >
                    <h4
                        class="text-sm font-bold text-gray-700 dark:text-white mb-1"
                    >
                        Current Program for Acceptance:
                    </h4>
                    <p class="text-sm text-gray-800 dark:text-gray-300">
                        {{ selectedUser?.application?.program?.code }} -
                        {{ selectedUser?.application?.program?.name }}
                        ({{ selectedUser?.application?.program?.slots }}
                        slots left)
                    </p>
                </div>

                <!-- Accept section — only if not yet completed and not enrolled -->
                <div v-if="!isEvaluationCompleted && selectedUser?.application?.enrollment_status !== 'officially_enrolled'" class="mt-4 flex justify-end">
                    <button
                        @click="acceptApplication"
                        class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium transition dark:text-gray-900"
                    >
                        Accept Application
                    </button>
                </div>

                <!-- Change Course Section -->
                <div
                    v-if="selectedUser?.application"
                    class="mt-5 p-3 border border-yellow-300 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-700"
                >
                    <h5 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">
                        ⚠️ Change Course
                    </h5>
                    <p class="text-xs text-yellow-700 dark:text-yellow-300 mb-3">
                        Changing the course of an officially enrolled applicant will be logged in the audit trail.
                    </p>
                    <select
                        v-model="changeCourseSelectedId"
                        id="change-course-select"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-yellow-500 focus:border-transparent mb-3"
                    >
                        <option value="" disabled>Select new program…</option>
                        <option
                            v-for="prog in availablePrograms"
                            :key="prog.id"
                            :value="prog.id"
                            :disabled="prog.id === selectedUser?.application?.program?.id"
                        >
                            {{ prog.code }} - {{ prog.name }} [Slots: {{ prog.slots }}]
                            <template v-if="prog.id === selectedUser?.application?.program?.id"> (current)</template>
                        </option>
                    </select>
                    <button
                        @click="changeCourse"
                        :disabled="!changeCourseSelectedId || changeCourseSelectedId === selectedUser?.application?.program?.id || isChangingCourse"
                        class="w-full px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed transition text-sm font-medium dark:text-gray-900"
                    >
                        <span v-if="isChangingCourse">Applying…</span>
                        <span v-else>Apply Changes</span>
                    </button>
                </div>

                <section class="mt-3 text-sm">
                    <h4 class="font-semibold mb-1 text-base">
                        Uploaded Documents
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div
                            v-for="(file, key) in selectedUserFiles"
                            :key="key"
                            class="flex flex-col items-start space-y-1"
                        >
                            <div class="flex items-center space-x-2 w-full">
                                <input
                                    v-if="isEvaluating"
                                    type="checkbox"
                                    :id="key"
                                    v-model="filesToReturn[key]"
                                    class="h-4 w-4 mt-1"
                                />
                                <label
                                    :for="key"
                                    class="text-xs font-medium truncate w-full"
                                >
                                    {{ formatFileKey(key) }}
                                </label>
                            </div>
                            <div class="w-full">
                                <img
                                    v-if="hasImagePreview(file)"
                                    :src="getFileUrl(file)"
                                    alt="Uploaded Document"
                                    class="h-16 w-full object-contain border rounded cursor-pointer"
                                    @click="openImageModal(file)"
                                />
                                <div
                                    v-else
                                    class="h-16 flex items-center justify-center text-[10px] italic text-gray-400 border rounded dark:text-gray-200"
                                >
                                    No Image
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Application History -->
                    <section
                        v-if="selectedUser?.application?.processes?.length"
                        class="mt-4"
                    >
                        <h4
                            class="font-semibold mb-2 text-base text-gray-800 dark:text-gray-200"
                        >
                            Application History
                        </h4>
                        <ul class="border-l-2 border-red-400 pl-3 space-y-2 dark:border-red-500">
                            <li
                                v-for="(process, index) in selectedUser
                                    .application.processes"
                                :key="index"
                                class="relative"
                            >
                                <div
                                    class="absolute -left-[10px] top-1 w-3 h-3 bg-red-600 rounded-full border-2 border-white"
                                ></div>
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ capitalize(process.stage) }} -
                                    <span
                                        :class="{
                                            'text-green-600':
                                                process.status === 'completed',
                                            'text-yellow-600':
                                                process.status ===
                                                'in_progress',
                                            'text-red-600':
                                                process.status === 'returned',
                                        }"
                                    >
                                        {{ capitalize(process.status) }}
                                    </span>
                                </p>
                                <p
                                    v-if="process.notes"
                                    class="text-xs text-gray-500 italic dark:text-gray-300"
                                >
                                    Note: {{ process.notes }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-200">
                                    {{ formatDate(process.created_at) }}
                                </p>
                            </li>
                        </ul>
                    </section>
                </section>
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
    if (s === "pending") return "bg-yellow-100 text-yellow-700";
    if (s === "rejected") return "bg-red-100 text-red-700";
    return "bg-gray-100 text-gray-600";
};

// Get evaluation-specific status text
const getEvaluationStatusText = (user) => {
    if (user.is_evaluation_completed) {
        // Show what action was taken
        if (user.process_action === 'accepted') return "Completed - Accepted";
        if (user.process_action === 'transferred') return "Completed - Transferred";
        return "Completed";
    }
    if (user.process_status === 'in_progress') return "Pending Review";
    return "Unknown";
};

// Get evaluation-specific status styling
const getEvaluationStatusClass = (user) => {
    if (user.is_evaluation_completed) {
        if (user.process_action === 'accepted') return "bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300";
        if (user.process_action === 'transferred') return "bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300";
        return "bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300";
    }
    if (user.process_status === 'in_progress') return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400";
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
                ? (evaluationStatusFilter.value === 'completed' ? u.is_evaluation_completed : !u.is_evaluation_completed)
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
        const response = await axios.get(
            `/interviewer-dashboard/application/${user.id}`
        );

        selectedUser.value = {
            ...user,
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

const acceptApplication = async () => {
    try {
        const currentUserId = selectedUser.value.id;
        
        await axios.post(
            `/interviewer-dashboard/accept/${currentUserId}`
        );
        showSnackbar("Application accepted.");
        
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
        showSnackbar(msg);
    }
};



const availablePrograms = ref([]);

const fetchPrograms = async () => {
    try {
        const response = await axios.get("/interviewer-dashboard/programs");
        availablePrograms.value = response.data.programs;
    } catch (e) {
        console.error("Failed to load programs", e);
    }
};

// ─── Change Course ───────────────────────────────────────────────────────────
const changeCourseSelectedId = ref("");
const isChangingCourse = ref(false);

const changeCourse = async () => {
    if (!changeCourseSelectedId.value) {
        showSnackbar("Please select a program first.");
        return;
    }

    const selectedProg = availablePrograms.value.find(
        (p) => p.id === changeCourseSelectedId.value
    );
    const confirmMsg = selectedProg
        ? `Change course to "${selectedProg.code} - ${selectedProg.name}"? This action will be logged.`
        : "Change course? This action will be logged.";

    if (!confirm(confirmMsg)) return;

    isChangingCourse.value = true;
    try {
        const isEnrolled = selectedUser.value?.application?.enrollment_status === 'officially_enrolled';
        const endpoint = isEnrolled 
            ? `/record-dashboard/change-course/${selectedUser.value.id}`
            : `/interviewer-dashboard/transfer/${selectedUser.value.id}`;

        const res = await axios.post(
            endpoint,
            { program_id: changeCourseSelectedId.value }
        );
        
        showSnackbar(res.data?.message ?? (isEnrolled ? "Course updated successfully." : "Applicant transferred successfully!"));
        changeCourseSelectedId.value = "";

        await fetchUsers();
        await fetchPrograms(); // Refresh slot counters
        const refreshedUser = users.value.find((u) => u.id === selectedUser.value.id);
        if (refreshedUser) {
            await selectUser(refreshedUser);
        } else {
            selectedUser.value = null;
        }
    } catch (e) {
        console.error("Course change failed:", e);
        const msg =
            e.response?.data?.message ??
            e.response?.data?.errors?.program_id?.[0] ??
            "Failed to change course.";
        showSnackbar(msg);
    } finally {
        isChangingCourse.value = false;
    }
};

// Reset when switching applicants
watch(
    () => selectedUser.value?.id,
    () => {
        changeCourseSelectedId.value = "";
    }
);

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
