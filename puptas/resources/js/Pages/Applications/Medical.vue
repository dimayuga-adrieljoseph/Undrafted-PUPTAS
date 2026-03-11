<template>
    <Head title="All Medical Applications" />
    <MedicalLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8 overflow-x-hidden overflow-y-auto">
            <!-- Filters and Controls -->
            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 mb-6">
                <!-- Search Input -->
                <div class="flex-1 relative">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2"
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
                        class="absolute top-full mt-2 right-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 rounded z-50 text-sm min-w-[150px]"
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
            <div v-if="loading" class="text-center text-gray-500 py-8">Loading applicants…</div>
            <div v-else-if="fetchError" class="text-center text-red-500 py-8">Error: {{ fetchError }}</div>

            <!-- Users Table -->
            <div v-else class="bg-white dark:bg-gray-800/20 rounded-xl shadow p-2 overflow-x-auto">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Showing {{ paginatedUsers.length }} of {{ filteredUsers.length }} users
                </div>
                
                <table class="min-w-full text-base">
                    <thead>
                        <tr class="text-left font-semibold text-black dark:text-white">
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C]" @click="sortBy('lastname')">
                                Name
                                <span v-if="sortKey === 'lastname'" class="ml-1">
                                    {{ sortAsc ? '↑' : '↓' }}
                                </span>
                            </th>
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C]" @click="sortBy('program.name')">
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
                            <span class="px-4 py-2 bg-[#9E122C] text-white rounded-lg font-medium">{{ currentPage }}</span>
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
                    class="mt-6 px-4 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition"
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
                        You have already evaluated this application. Actions are no longer available.
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

                <div
                    class="mt-3 p-2 border rounded bg-gray-100 dark:bg-gray-800"
                >
                    <h4
                        class="text-sm font-bold text-gray-700 dark:text-white mb-1"
                    >
                        Current Program:
                    </h4>
                    <p class="text-sm text-gray-800 dark:text-gray-300">
                        {{ selectedUser?.application?.program?.code }} -
                        {{ selectedUser?.application?.program?.name }}
                    </p>
                </div>

                <!-- Evaluate & Cancel buttons - Only show if not completed -->
                <div v-if="!isEvaluationCompleted" class="mt-3 flex space-x-2 justify-end">
                    <button
                        v-if="!isEvaluating"
                        @click="startEvaluation"
                        class="px-3 py-1 text-gray-600 text-l rounded hover:text-yellow-700"
                    >
                        <font-awesome-icon :icon="faBolt" /> Actions
                    </button>

                    <button
                        v-if="isEvaluating"
                        @click="cancelEvaluation"
                        class="px-3 py-1 bg-gray-400 text-black text-sm rounded hover:bg-gray-500"
                    >
                        Cancel
                    </button>
                </div>

                <!-- Return note textarea -->
                <div v-if="isEvaluating && !isEvaluationCompleted" class="mt-2">
                    <label
                        for="returnNote"
                        class="block text-xs font-semibold mb-1"
                    >
                        Return Reason / Note
                    </label>
                    <textarea
                        id="returnNote"
                        v-model="returnNote"
                        rows="2"
                        class="w-full border rounded p-1 text-xs"
                        placeholder="Reason for returning..."
                    ></textarea>
                </div>

                <!-- Submit button - Only show if not completed -->
                <div v-if="isEvaluating && !isEvaluationCompleted" class="mt-2 flex justify-end">
                    <button
                        @click="submitReturn"
                        class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700"
                    >
                        Return Selected Files
                    </button>
                    <button
                        @click="acceptApplication"
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                    >
                        Clear Medical
                    </button>
                </div>

                <!-- <div class="mt-4">
                        Accept and Transfer buttons
                        <div class="mt-4 flex justify-end space-x-2">
                            <button
                                @click="acceptApplication"
                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                            >
                                Clear Medical
                            </button>
                        </div>
                    </div> -->

                <section class="mt-3 text-sm">
                    <h4 class="font-semibold mb-1 text-base">
                        Uploaded Documents
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div
                            v-for="(src, key) in selectedUserFiles"
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
                                    v-if="src"
                                    :src="src"
                                    alt="Uploaded Document"
                                    class="h-16 w-full object-contain border rounded cursor-pointer"
                                    @click="openImageModal(src)"
                                />
                                <div
                                    v-else
                                    class="h-16 flex items-center justify-center text-[10px] italic text-gray-400 border rounded"
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
                        <ul class="border-l-2 border-red-400 pl-3 space-y-2">
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
                                    class="text-xs text-gray-500 italic"
                                >
                                    Note: {{ process.notes }} By:
                                    {{ process.performed_by.firstname }}
                                    {{ process.performed_by.lastname }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ formatDate(process.created_at) }}
                                </p>
                            </li>
                        </ul>
                    </section>
                </section>
            </div>
        </transition>
        <!-- Snackbar -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-4 py-2 rounded shadow-lg z-50"
            >
                {{ snackbar.message }}
            </div>
        </transition>

        <!-- Image Preview Modal -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50"
            @click.self="closeImageModal"
        >
            <img
                :src="previewImage"
                alt="Preview"
                class="max-w-full max-h-full rounded shadow-lg"
            />
            <button
                @click="closeImageModal"
                class="absolute top-5 right-5 text-white text-4xl font-bold hover:text-gray-300"
                aria-label="Close preview"
            >
                &times;
            </button>
        </div>
    </MedicalLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import MedicalLayout from "@/Layouts/MedicalLayout.vue";

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
        if (user.process_action === 'passed') return "Completed - Passed";
        if (user.process_action === 'returned') return "Completed - Returned";
        return "Completed";
    }
    if (user.process_status === 'returned') return "Needs Revision";
    if (user.process_status === 'in_progress') return "Pending Review";
    return "Unknown";
};

// Get evaluation-specific status styling
const getEvaluationStatusClass = (user) => {
    if (user.is_evaluation_completed) {
        if (user.process_action === 'passed') return "bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300";
        if (user.process_action === 'returned') return "bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300";
        return "bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300";
    }
    if (user.process_status === 'returned') return "bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300";
    if (user.process_status === 'in_progress') return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400";
};

const fetchUsers = async () => {
    try {
        const response = await fetch("/medical-dashboard/applicants", {
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
            const aVal = (a[sortKey.value] || "").toString().toLowerCase();
            const bVal = (b[sortKey.value] || "").toString().toLowerCase();
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
            `/medical-dashboard/application/${user.id}`
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
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
};

// Check if the current user's medical process is completed
const isEvaluationCompleted = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) {
        return false;
    }
    const medicalProcess = selectedUser.value.application.processes.find(
        p => p.stage === 'medical'
    );
    return medicalProcess && medicalProcess.status === 'completed';
});

const formatFileKey = (key) => {
    const map = {
        file11: "Grade 11 Report",
        file12: "Grade 12 Report",
        schoolId: "School ID",
        nonEnrollCert: "Certificate of Non-Enrollment",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (src) => {
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
        
        await axios.post(`/medical/return-files/${currentUserId}`, {
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
        
        await axios.post(`/medical-dashboard/accept/${currentUserId}`);
        showSnackbar("Application cleared.");
        
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
        const response = await axios.get("/interviewer-dashboard/programs");
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
</style>
